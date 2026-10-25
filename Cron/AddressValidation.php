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
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\OrderRepository;
use Parc\AddressValidation\Model\AddressValidationFactory;
use Parc\AddressValidation\Model\Config\Backend\Enable;
use Parc\AddressValidation\Model\RegionResolver;
use Parc\AddressValidation\Model\StreetLineBuilder;
use Parc\AddressValidation\Model\ValidationStart;
use Parc\AddressValidation\Service\EnderecoApi;
use Parc\AddressValidation\Service\EnderecoApiUnavailableException;
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
     * UTC "Y-m-d H:i:s"; only orders created at or after it are validated.
     */
    protected string $validationStart;

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
     * @var ValidationStart
     */
    protected ValidationStart $validationStartStorage;

    /**
     * @var DateTime
     */
    protected DateTime $dateTime;

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
     * @param ValidationStart             $validationStartStorage
     * @param DateTime                    $dateTime
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
        StreetLineBuilder           $streetLineBuilder,
        ValidationStart $validationStartStorage,
        DateTime $dateTime
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
        $this->validationStartStorage      = $validationStartStorage;
        $this->dateTime                    = $dateTime;

        $this->overwriteOriginal   = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/overwriteoriginal');
        $this->orderStatus         = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/orderstatus');
        $this->validationStatus    = (string)$this->scopeConfig->getValue('parc_addressvalidation/general/validationstatus');
        $this->statusCodes         = array_filter(explode(
            ',',
            (string)$this->scopeConfig->getValue('parc_addressvalidation/sharpness/statuscodes')
        ));
        $this->checkAdditionalInfo = (string)$this->scopeConfig->getValue('parc_addressvalidation/sharpness/additional_info');
        $this->validationStart     = (string)$this->scopeConfig->getValue(Enable::XML_PATH_VALIDATION_START);
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

    private function doExecute(): void
    {
        if (!$this->validationStart && $this->scopeConfig->isSetFlag(Enable::XML_PATH_ENABLE)) {
            $this->initValidationStart();
        }

        if (!$this->orderStatus || !$this->statusCodes || !$this->validationStart) {
            return;
        }

        $relevantOrders = $this->getRelevantOrders();

        foreach ($relevantOrders as $index => $relevantOrder) {
            try {
                $order = $this->orderRepository->get($relevantOrder['entity_id']);
                $this->processOrder($order);
            } catch (EnderecoApiUnavailableException $exception) {
                // Unlike a per-order failure below, this isn't specific to the current
                // order: a rejected key or a connection failure affects every remaining
                // order in $relevantOrders identically, and won't resolve itself mid-run.
                // Stop here instead of repeating (and logging) the same failure once per
                // remaining order - they stay unprocessed and are picked up again next tick.
                $remaining = count($relevantOrders) - $index;
                $this->logger->error(sprintf(
                    'Address validation cron: %s - aborting this run, %d order(s) '
                    . '(including entity_id %s) will be retried next tick.',
                    $exception->getMessage(),
                    $remaining,
                    $relevantOrder['entity_id']
                ));
                break;
            } catch (\Throwable $exception) {
                // A failure processing one order (a save conflict, anything unexpected) must
                // not take the rest of the batch down with it - otherwise a single problematic
                // order blocks the queue on every following tick, since orders after it in
                // $relevantOrders never get reached this run either.
                $this->logger->error(sprintf(
                    'Address validation cron: failed to process order entity_id %s, skipping: %s',
                    $relevantOrder['entity_id'],
                    $exception->getMessage()
                ));
            }
        }
    }

    /**
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InputException
     */
    private function processOrder($order): void
    {
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
            return;
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
                } elseif ($this->overwriteOriginal && count($foundAddresses) === 1) {
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
                } elseif ($this->overwriteOriginal && count($foundAddresses) === 0) {
                    // Auto-overwrite is on, but the API returned no candidate at all - there is
                    // nothing to write back (unlike the count()>1 case above, none of the
                    // returned status codes need to be "critical" for this to happen; sharpness
                    // config alone can't be relied on to catch it). Hold for manual review
                    // instead of leaving the order to proceed with a wiped shipping address.
                    $this->setAddressValidationStatus($order);
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

    /**
     * Fallback for an active module without a validation start. Normally the
     * first save of "Enable" or the update patch sets it; this covers the rest
     * (e.g. "Enable" set in config.php/env.php, or the value deleted later), so
     * validation starts now instead of silently never running. Older orders stay
     * untouched either way.
     */
    private function initValidationStart(): void
    {
        // The config cache this class was built from can lag behind a start that
        // was just written (e.g. by the update patch) - never overwrite that one.
        $stored = $this->validationStartStorage->get();
        if ($stored !== null) {
            $this->validationStart = $stored;
            return;
        }

        $this->validationStart = (string)$this->dateTime->gmtDate();
        $this->validationStartStorage->set($this->validationStart);
        $this->logger->info(sprintf(
            'Address validation cron: no validation start was set; set it to %s (UTC). '
            . 'Only orders placed from then on are validated.',
            $this->validationStart
        ));
    }

    /**
     * Orders in one of the configured statuses, placed at or after the validation
     * start and not validated yet.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getRelevantOrders(): array
    {
        $connection = $this->resourceConnection->getConnection();
        $statuses   = array_values(array_filter(array_map('trim', explode(',', $this->orderStatus))));

        $select = $connection->select()
            ->from(['so' => $this->resourceConnection->getTableName('sales_order')], ['entity_id'])
            // LEFT JOIN ... IS NULL instead of NOT IN (subselect): quote() wraps a
            // Select in its own parentheses, so "NOT IN (?)" would render as
            // "NOT IN ((SELECT ...))" and fail as soon as it returns several rows.
            ->joinLeft(
                ['pav' => $this->resourceConnection->getTableName('parc_addressvalidation')],
                'pav.order_id = so.entity_id',
                []
            )
            ->where('pav.order_id IS NULL')
            ->where('so.status IN (?)', $statuses)
            ->where('so.is_virtual = ?', 0)
            // Both values are UTC "Y-m-d H:i:s", so they compare directly.
            ->where('so.created_at >= ?', $this->validationStart);

        return $connection->fetchAll($select);
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
