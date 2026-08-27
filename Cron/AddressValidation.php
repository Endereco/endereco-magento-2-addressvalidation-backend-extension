<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Cron;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Sales\Model\OrderRepository;
use Parc\AddressValidation\Model\AddressValidationFactory;
use Parc\AddressValidation\Model\RegionResolver;
use Parc\AddressValidation\Model\StreetLineBuilder;
use Parc\AddressValidation\Service\EnderecoApi;
use Parc\AddressValidation\Model\AddressValidationRepository;
use Psr\Log\LoggerInterface;

class AddressValidation
{
    private const LOCK_NAME = 'parc_addressvalidation_cron';

    protected string $overwriteOriginal;

    protected string $orderStatus;

    protected array $statusCodes;

    protected string $checkAdditionalInfo;

    protected string $validationStatus;

    /**
     * @var ResourceConnection
     */
    protected ResourceConnection $resourceConnection;

    /**
     * @var OrderRepository
     */
    protected OrderRepository $orderRepository;

    /**
     * @var EnderecoApi
     */
    protected EnderecoApi $enderecoApi;

    /**
     * @var AddressValidationFactory
     */
    protected AddressValidationFactory $addressValidationFactory;

    /**
     * @var CountryFactory
     */
    protected CountryFactory $countryFactory;

    /**
     * @var AddressValidationRepository
     */
    protected AddressValidationRepository $addressValidationRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var LockManagerInterface
     */
    protected LockManagerInterface $lockManager;

    /**
     * @var LoggerInterface
     */
    protected LoggerInterface $logger;

    /**
     * @var RegionResolver
     */
    protected RegionResolver $regionResolver;

    /**
     * @var StreetLineBuilder
     */
    protected StreetLineBuilder $streetLineBuilder;

    /**
     * @param ResourceConnection          $resourceConnection
     * @param OrderRepository             $orderRepository
     * @param EnderecoApi                 $enderecoApi
     * @param AddressValidationFactory    $addressValidationFactory
     * @param CountryFactory              $countryFactory
     * @param AddressValidationRepository $addressValidationRepository
     * @param ScopeConfigInterface        $scopeConfig
     * @param LockManagerInterface        $lockManager
     * @param LoggerInterface             $logger
     * @param RegionResolver              $regionResolver
     * @param StreetLineBuilder           $streetLineBuilder
     */
    public function __construct(
        ResourceConnection          $resourceConnection,
        OrderRepository             $orderRepository,
        EnderecoApi                 $enderecoApi,
        AddressValidationFactory    $addressValidationFactory,
        CountryFactory              $countryFactory,
        AddressValidationRepository $addressValidationRepository,
        ScopeConfigInterface        $scopeConfig,
        LockManagerInterface        $lockManager,
        LoggerInterface             $logger,
        RegionResolver              $regionResolver,
        StreetLineBuilder           $streetLineBuilder
    ) {
        $this->resourceConnection          = $resourceConnection;
        $this->orderRepository             = $orderRepository;
        $this->enderecoApi                 = $enderecoApi;
        $this->addressValidationFactory    = $addressValidationFactory;
        $this->countryFactory              = $countryFactory;
        $this->addressValidationRepository = $addressValidationRepository;
        $this->scopeConfig                 = $scopeConfig;
        $this->lockManager                 = $lockManager;
        $this->logger                      = $logger;
        $this->regionResolver              = $regionResolver;
        $this->streetLineBuilder           = $streetLineBuilder;

        $this->overwriteOriginal   = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/overwriteoriginal');
        $this->orderStatus         = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/orderstatus');
        $this->validationStatus    = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/validationstatus');
        $this->statusCodes         = array_filter(explode(
            ',',
            (string)$this->scopeConfig->getValue('parc_addressvalidation/sharpness/statuscodes')
        ));
        $this->checkAdditionalInfo = (string)$this->scopeConfig->getValue('parc_addressvalidation/sharpness/additional_info');
    }

    public function execute(): void
    {
        if (!$this->lockManager->lock(self::LOCK_NAME, 0)) {
            $this->logger->info('Address validation cron is already running; skipping this tick.');
            return;
        }

        try {
            $this->doExecute();
        } finally {
            $this->lockManager->unlock(self::LOCK_NAME);
        }
    }

    /**
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InputException
     */
    private function doExecute(): void
    {
        if (!$this->orderStatus || !$this->statusCodes) {
            return;
        }

        $relevantOrders = $this->getRelevantOrders();

        foreach ($relevantOrders as $relevantOrder) {
            $order           = $this->orderRepository->get($relevantOrder['entity_id']);
            $shippingAddress = $order->getShippingAddress();

            if ($shippingAddress === null) {
                // Should be excluded by getRelevantOrders()'s is_virtual filter already;
                // this is a safety net for any other reason an order might lack a shipping
                // address. Skipping without recording anything means it would be picked up
                // again next tick and log again - acceptable, since this is expected to be rare.
                $this->logger->warning(sprintf(
                    'Address validation cron: order #%s has no shipping address, skipping.',
                    $order->getIncrementId()
                ));
                continue;
            }

            $zipCode         = (string)$shippingAddress['postcode'];
            $city            = (string)$shippingAddress['city'];
            $countryCode     = (string)$shippingAddress['country_id'];
            $streetFull      = (string)$shippingAddress['street'];
            $regionId        = $shippingAddress->getRegionId() ? (int)$shippingAddress->getRegionId() : null;
            $subdivisionCode = $this->regionResolver->getSubdivisionCode($regionId);

            $bodyStreetSplitter = json_encode([
                'jsonrpc' => '2.0',
                'id'      => 1,
                'method'  => 'splitStreet',
                'params'  => [
                    'formatCountry' => $countryCode,
                    'language'      => $countryCode,
                    'street'        => $streetFull,
                    'additionalInfo' => '',
                ],
            ]);

            $responseStreetSplitter = $this->enderecoApi->execute($bodyStreetSplitter);
            if ($responseStreetSplitter) {
                $responseArrayStreetSplitter = json_decode($responseStreetSplitter, true);
                $splittedStreet              = $responseArrayStreetSplitter['result']['streetName'];
                $splittedHouseNumber         = $responseArrayStreetSplitter['result']['houseNumber'];
                $additionalInfo              = $responseArrayStreetSplitter['result']['additionalInfo'] ?? null;

                $body = json_encode([
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'method'  => 'addressCheck',
                    'params'  => [
                        'country'     => $countryCode,
                        'language'    => $countryCode,
                        'postCode'    => $zipCode,
                        'cityName'    => $city,
                        'street'      => $splittedStreet,
                        'houseNumber' => $splittedHouseNumber,
                        // Empty string, not omitted: like splitStreet's additionalInfo above,
                        // the API only includes subdivisionCode in the response predictions
                        // (and activates the subdivision_code_* status codes) when this key
                        // is present in the request at all - confirmed against the live API.
                        'subdivisionCode' => '',
                    ],
                ]);

                $response = $this->enderecoApi->execute($body);
                if ($response) {
                    $response_array = json_decode($response, true);
                    $foundAddresses = $response_array['result']['predictions'];
                    $resultStatus   = $response_array['result']['status'];
                    $criticalStatus = array_intersect($this->statusCodes, $resultStatus);
                    $apiSubdivisionCode = $foundAddresses[0]['subdivisionCode'] ?? null;
                    $apiRegionId        = $this->regionResolver->resolveRegionId($countryCode, $apiSubdivisionCode);
                    // address needs to be reviewed because either
                    // 1 -> response/status code is identified as critical
                    // 2 -> it contains additional infos and the config is set to always check add. infos
                    // 3 -> multiple addresses were found
                    if (count($criticalStatus) > 0 ||
                        ($additionalInfo && $this->checkAdditionalInfo == 1) ||
                        count($foundAddresses) > 1) {
                        // needs to be manually checked
                        $this->setAddressValidationStatus($order);
                    } elseif ($this->overwriteOriginal) {
                        // set validated address as orig. shipping address if configuration is enabled
                        $shippingAddress
                            ->setPostcode($foundAddresses[0]['postCode'])
                            ->setCity($foundAddresses[0]['cityName'])
                            ->setStreet($this->streetLineBuilder->build(
                                $foundAddresses[0]['street'] ?? '',
                                $foundAddresses[0]['houseNumber'] ?? '',
                                $additionalInfo
                            ));
                        $this->regionResolver->applyRegion($shippingAddress, $apiRegionId);

                        $order->addCommentToStatusHistory(__(
                            'Original shipping address was overwritten with the validated address by the system (per module configuration).'
                        ));

                        $this->orderRepository->save($order);
                    }
                    // save address as verified shipping address
                    $verifiedAddress = $this->addressValidationFactory->create();
                    $verifiedAddress->setOrderId($order->getEntityId())
                                    ->setOrderIncrementId($order->getIncrementId())
                                    ->setOrigZipCode($zipCode)
                                    ->setOrigCity($city)
                                    ->setOrigStreetFull($streetFull)
                                    ->setOrigRegionId($regionId)
                                    ->setOrigSubdivisionCode($subdivisionCode)
                                    ->setApiZipCode($foundAddresses[0]['postCode'] ?? null)
                                    ->setApiCity($foundAddresses[0]['cityName'] ?? null)
                                    ->setApiStreet($foundAddresses[0]['street'] ?? null)
                                    ->setApiHouseNumber($foundAddresses[0]['houseNumber'] ?? null)
                                    ->setApiAdditionalInformation($additionalInfo)
                                    ->setApiRegionId($apiRegionId)
                                    ->setApiSubdivisionCode($apiSubdivisionCode)
                                    ->setStatusCodes(implode(', ', $resultStatus));
                    $this->addressValidationRepository->save($verifiedAddress);
                } else {
                    $this->setOrigData($order, $zipCode, $city, $streetFull, $regionId, $subdivisionCode);
                    $this->setAddressValidationStatus($order);
                }
            } else {
                $this->setOrigData($order, $zipCode, $city, $streetFull, $regionId, $subdivisionCode);
                $this->setAddressValidationStatus($order);
            }
        }
    }

    private function getRelevantOrders()
    {
        $formattedOrderStatus = "'" . implode("','", array_map('trim', explode(',', $this->orderStatus))) . "'";
        // phpcs:disable
        $sql = "SELECT entity_id FROM sales_order
                WHERE entity_id NOT IN (
                    SELECT order_id FROM parc_addressvalidation
                )
                AND status IN ($formattedOrderStatus)
                AND is_virtual = 0";

        // phpcs:enable
        return $this->resourceConnection->getConnection()->fetchAll($sql);
    }

    /**
     * @throws NoSuchEntityException
     * @throws AlreadyExistsException
     * @throws InputException
     */
    private function setAddressValidationStatus($order): void
    {
        $holdBeforeState  = $order->getHoldBeforeState() ?: $order->getState();
        $holdBeforeStatus = $order->getHoldBeforeStatus() ?: $order->getStatus();

        $order->setHoldBeforeState($holdBeforeState);
        $order->setHoldBeforeStatus($holdBeforeStatus);

        $order->setState('holded');
        $order->setStatus($this->validationStatus);
        $order->addCommentToStatusHistory(
            __('Order put on hold for address verification by the system.'),
            $this->validationStatus
        );
        $this->orderRepository->save($order);
    }

    private function setOrigData($order, $zipCode, $city, $streetFull, $regionId = null, $subdivisionCode = null)
    {
        // save original address data in validation table
        $addressData = $this->addressValidationFactory->create();
        $addressData->setOrderId($order->getEntityId())
                    ->setOrderIncrementId($order->getIncrementId())
                    ->setOrigZipCode($zipCode)
                    ->setOrigCity($city)
                    ->setOrigStreetFull($streetFull)
                    ->setOrigRegionId($regionId)
                    ->setOrigSubdivisionCode($subdivisionCode);
        $this->addressValidationRepository->save($addressData);
    }
}
