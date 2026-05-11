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
use Magento\Sales\Model\OrderRepository;
use Parc\AddressValidation\Model\AddressValidationFactory;
use Parc\AddressValidation\Service\EnderecoApi;
use Parc\AddressValidation\Model\AddressValidationRepository;

class AddressValidation
{
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
     * @param ResourceConnection          $resourceConnection
     * @param OrderRepository             $orderRepository
     * @param EnderecoApi                 $enderecoApi
     * @param AddressValidationFactory    $addressValidationFactory
     * @param CountryFactory              $countryFactory
     * @param AddressValidationRepository $addressValidationRepository
     * @param ScopeConfigInterface        $scopeConfig
     */
    public function __construct(
        ResourceConnection          $resourceConnection,
        OrderRepository             $orderRepository,
        EnderecoApi                 $enderecoApi,
        AddressValidationFactory    $addressValidationFactory,
        CountryFactory              $countryFactory,
        AddressValidationRepository $addressValidationRepository,
        ScopeConfigInterface        $scopeConfig
    ) {
        $this->resourceConnection          = $resourceConnection;
        $this->orderRepository             = $orderRepository;
        $this->enderecoApi                 = $enderecoApi;
        $this->addressValidationFactory    = $addressValidationFactory;
        $this->countryFactory              = $countryFactory;
        $this->addressValidationRepository = $addressValidationRepository;
        $this->scopeConfig                 = $scopeConfig;

        $this->overwriteOriginal   = $this->scopeConfig->getValue('parc_addressvalidation/general/overwriteoriginal');
        $this->orderStatus         = $this->scopeConfig->getValue('parc_addressvalidation/general/orderstatus');
        $this->validationStatus    = $this->scopeConfig->getValue('parc_addressvalidation/general/validationstatus');
        $this->statusCodes         = explode(
            ',',
            $this->scopeConfig->getValue('parc_addressvalidation/sharpness/statuscodes')
        );
        $this->checkAdditionalInfo = $this->scopeConfig->getValue('parc_addressvalidation/sharpness/additional_info');
    }

    /**
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function execute(): void
    {
        if (!$this->orderStatus || !$this->statusCodes) {
            return;
        }

        $relevantOrders = $this->getRelevantOrders();

        foreach ($relevantOrders as $relevantOrder) {
            $order           = $this->orderRepository->get($relevantOrder['entity_id']);
            $shippingAddress = $order->getShippingAddress();
            $zipCode         = preg_replace('/[\r\n"\\\]+/', ' ', $shippingAddress['postcode']);
            $city            = preg_replace('/[\r\n"\\\]+/', ' ', $shippingAddress['city']);
            $countryCode     = $shippingAddress['country_id'];
            $streetFull      = preg_replace('/[\r\n"\\\]+/', ' ', $shippingAddress['street']);
            // street splitter request
            $bodyStreetSplitter     = '{
                "jsonrpc":"2.0",
                "id":1,
                "method":"splitStreet",
                "params":{
                    "formatCountry":"' . $countryCode . '",
                    "language":"' . $countryCode . '",
                    "street": "' . $streetFull . '"
                }
            }';
            $responseStreetSplitter = $this->enderecoApi->execute($bodyStreetSplitter);
            if ($responseStreetSplitter) {
                $responseArrayStreetSplitter = json_decode($responseStreetSplitter, true);
                $splittedStreet              = $responseArrayStreetSplitter['result']['streetName'];
                $splittedHouseNumber         = $responseArrayStreetSplitter['result']['houseNumber'];
                $additionalInfo              = $responseArrayStreetSplitter['result']['additionalInfo'] ?? null;
                $body                        = '{
                  "jsonrpc": "2.0",
                  "id": 1,
                  "method": "addressCheck",
                  "params": {
                    "country": "' . $countryCode . '",
                    "language": "' . $countryCode . '",
                    "postCode": "' . $zipCode . '",
                    "cityName": "' . $city . '",
                    "street": "' . $splittedStreet . '",
                    "houseNumber": "' . $splittedHouseNumber . '"
                  }
                }';
                $response                    = $this->enderecoApi->execute($body);
                if ($response) {
                    $response_array = json_decode($response, true);
                    $foundAddresses = $response_array['result']['predictions'];
                    $resultStatus   = $response_array['result']['status'];
                    $criticalStatus = array_intersect($this->statusCodes, $resultStatus);
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
                            ->setStreet($foundAddresses[0]['street'] . ' ' . $foundAddresses[0]['houseNumber'] ?? null);

                        $order->addCommentToStatusHistory(
                            'original shipping address was updated to the validated address by system due to config'
                        );

                        $this->orderRepository->save($order);
                    }
                    // save address as verified shipping address
                    $verifiedAddress = $this->addressValidationFactory->create();
                    $verifiedAddress->setOrderId($order->getEntityId())
                                    ->setOrderIncrementId($order->getIncrementId())
                                    ->setOrigZipCode($zipCode)
                                    ->setOrigCity($city)
                                    ->setOrigStreetFull($streetFull)
                                    ->setApiZipCode($foundAddresses[0]['postCode'] ?? null)
                                    ->setApiCity($foundAddresses[0]['cityName'] ?? null)
                                    ->setApiStreet($foundAddresses[0]['street'] ?? null)
                                    ->setApiHouseNumber($foundAddresses[0]['houseNumber'] ?? null)
                                    ->setApiAdditionalInformation($additionalInfo)
                                    ->setStatusCodes(implode(', ', $resultStatus));
                    $this->addressValidationRepository->save($verifiedAddress);
                } else {
                    $this->setOrigData($order, $zipCode, $city, $streetFull);
                    $this->setAddressValidationStatus($order);
                }
            } else {
                $this->setOrigData($order, $zipCode, $city, $streetFull);
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
                AND status IN ($formattedOrderStatus)";

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
        $holdBeforeState  = $order->getHoldBeforeState() ?? $order->getState();
        $holdBeforeStatus = $order->getHoldBeforeStatus() ?? $order->getStatus();

        $order->setHoldBeforeState($holdBeforeState);
        $order->setHoldBeforeStatus($holdBeforeStatus);

        $order->setState('holded');
        $order->setStatus($this->validationStatus);
        $order->addCommentToStatusHistory(
            'Zurückgestellt wegen Adressprüfung <br> by System',
            $this->validationStatus
        );
        $this->orderRepository->save($order);
    }

    private function setOrigData($order, $zipCode, $city, $streetFull)
    {
        // save original address data in validation table
        $addressData = $this->addressValidationFactory->create();
        $addressData->setOrderId($order->getEntityId())
                    ->setOrderIncrementId($order->getIncrementId())
                    ->setOrigZipCode($zipCode)
                    ->setOrigCity($city)
                    ->setOrigStreetFull($streetFull);
        $this->addressValidationRepository->save($addressData);
    }
}
