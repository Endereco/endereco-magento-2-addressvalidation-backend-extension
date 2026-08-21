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
            $validatedAddress = $this->addressValidationRepository->getByOrderIdOrNull((int)$orderId);
            if ($validatedAddress === null) {
                // Order was not put on hold by this module's validation flow.
                return $result;
            }

            // Guard: resolved values are null when "Restore orig. shipping address"
            // was called before unhold (it nulls all api_* and manu_* fields).
            // Applying them would clear the shipping address to null/whitespace -
            // skip silently instead, leaving the already-restored original in place.
            if ($validatedAddress->getResolvedZipCode() === null
                && $validatedAddress->getResolvedStreet() === null
                && $validatedAddress->getResolvedCity() === null) {
                return $result;
            }

            $adminUser   = $this->authSession->getUser()->getUserName();
            $streetLines = array_filter([
                $validatedAddress->getResolvedStreet() . ' ' . $validatedAddress->getResolvedHouseNumber(),
                $validatedAddress->getResolvedAdditionalInformation() ?? '',
            ]);

            $order           = $this->orderRepository->get($orderId);
            $shippingAddress = $order->getShippingAddress();

            $shippingAddress
                ->setPostcode($validatedAddress->getResolvedZipCode())
                ->setCity($validatedAddress->getResolvedCity())
                ->setStreet(array_values($streetLines));

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
