<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Plugin;

use Exception;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Api\OrderManagementInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Parc\AddressValidation\Model\AddressValidationRepository;
use Magento\Framework\Message\ManagerInterface;

class OrderUnholdPlugin
{
    private const XML_PATH_ENABLE_COMMENT = 'parc_addressvalidation/general/overwriteoriginal';

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @var ScopeConfigInterface
     */
    protected ScopeConfigInterface $scopeConfig;

    /**
     * @var BackendAuthSession
     */
    protected BackendAuthSession $authSession;

    /**
     * @var AddressValidationRepository
     */
    protected AddressValidationRepository $addressValidationRepository;

    /**
     * @var ManagerInterface
     */
    protected ManagerInterface $messageManager;

    /**
     * @param OrderRepositoryInterface    $orderRepository
     * @param ScopeConfigInterface        $scopeConfig
     * @param BackendAuthSession          $authSession
     * @param AddressValidationRepository $addressValidationRepository
     * @param ManagerInterface            $messageManager
     */
    public function __construct(
        OrderRepositoryInterface    $orderRepository,
        ScopeConfigInterface        $scopeConfig,
        BackendAuthSession          $authSession,
        AddressValidationRepository $addressValidationRepository,
        ManagerInterface            $messageManager,
    ) {
        $this->orderRepository             = $orderRepository;
        $this->scopeConfig                 = $scopeConfig;
        $this->authSession                 = $authSession;
        $this->addressValidationRepository = $addressValidationRepository;
        $this->messageManager              = $messageManager;
    }

    public function afterUnHold(OrderManagementInterface $subject, $result, $orderId)
    {
        $overwriteOriginalIsEnabled = $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLE_COMMENT,
            ScopeInterface::SCOPE_STORE
        );

        if (!$overwriteOriginalIsEnabled) {
            return $result;
        }

        try {
            $validatedAddress = $this->addressValidationRepository->getByOrderIdOrNull($orderId);
            if ($validatedAddress === null) {
                // Order was not put on hold by this module's validation flow.
                return $result;
            }

            $adminUser   = $this->authSession->getUser()->getUserName();
            $zipCode     = $validatedAddress->getManuZipCode() ?? $validatedAddress->getApiZipCode();
            $city        = $validatedAddress->getManuCity() ?? $validatedAddress->getApiCity();
            $street      = $validatedAddress->getManuStreet() ?? $validatedAddress->getApiStreet();
            $houseNumber = $validatedAddress->getManuHouseNumber() ?? $validatedAddress->getApiHouseNumber();
            $streetFull  = $street . ' ' . $houseNumber;

            $order           = $this->orderRepository->get($orderId);
            $shippingAddress = $order->getShippingAddress();

            $shippingAddress
                ->setPostcode($zipCode)
                ->setCity($city)
                ->setStreet($streetFull);

            $order->addCommentToStatusHistory(__(
                'Order was resumed by %1 and the validated address was set as shipping address (due to module config value)',
                $adminUser
            ));

            $this->orderRepository->save($order);
        } catch (Exception) {
            $this->messageManager
                ->addErrorMessage(__('An error occurred while saving the validated address as shipping address.'));
        }

        return $result;
    }
}
