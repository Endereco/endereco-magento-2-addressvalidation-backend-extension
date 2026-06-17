<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Controller\Adminhtml\Address;

use Exception;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;
use Parc\AddressValidation\Model\AddressValidationRepository;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Backend\Model\Auth\Session as BackendAuthSession;
use Magento\Sales\Api\OrderRepositoryInterface;

class Save extends Action implements HttpPostActionInterface
{
    /**
     * @var AddressValidationRepository
     */
    protected AddressValidationRepository $addressValidationRepository;

    /**
     * @var BackendAuthSession
     */
    protected BackendAuthSession $backendAuthSession;

    /**
     * @var OrderRepositoryInterface
     */
    protected OrderRepositoryInterface $orderRepository;

    /**
     * @param AddressValidationRepository $addressValidationRepository
     * @param ManagerInterface            $messageManager
     * @param RedirectFactory             $resultRedirectFactory
     * @param Context                     $context
     * @param BackendAuthSession          $backendAuthSession
     * @param OrderRepositoryInterface    $orderRepository
     */
    public function __construct(
        AddressValidationRepository $addressValidationRepository,
        ManagerInterface            $messageManager,
        RedirectFactory             $resultRedirectFactory,
        Context                     $context,
        BackendAuthSession          $backendAuthSession,
        OrderRepositoryInterface    $orderRepository
    ) {
        $this->addressValidationRepository = $addressValidationRepository;
        $this->messageManager              = $messageManager;
        $this->resultRedirectFactory       = $resultRedirectFactory;
        $this->backendAuthSession          = $backendAuthSession;
        $this->orderRepository             = $orderRepository;
        parent::__construct($context);
    }

    /**
     * Determines whether current user is allowed to access Action
     *
     * @return bool
     */
    protected function _isAllowed(): bool
    {
        return $this->_authorization->isAllowed('Parc_AddressValidation::save');
    }

    /**
     * @return ResultInterface|ResponseInterface|Redirect
     */
    public function execute(): ResultInterface|ResponseInterface|Redirect
    {
        $params              = $this->getRequest()->getParams();
        $actionType          = $params['action'] ?? 'save';
        $username            = $this->backendAuthSession->getUser()->getUserName();
        $params['edited_by'] = $username;

        if ($actionType === 'save') {
            if (!$this->isAddressValid($params)) {
                $this->messageManager->addErrorMessage(__('Please fill out all the address fields!'));
            } else {
                try {
                    $this->addressValidationRepository->saveNewValues($params);
                    $this->messageManager->addSuccessMessage(__('The address has been saved successfully.'));
                } catch (Exception) {
                    $this->messageManager->addErrorMessage(__('An error occurred while saving the address.'));
                }
            }
        } elseif ($actionType === 'save_as_shipping') {
            if (!$this->isAddressValid($params)) {
                $this->messageManager->addErrorMessage(__('Please fill out all the address fields!'));
            } else {
                try {
                    // Persist manu_* on the validation record first so the
                    // unhold plugin and CSV export see the same values.
                    $this->addressValidationRepository->saveNewValues($params);
                    $this->updateShippingAddress($params);
                    $this->messageManager
                        ->addSuccessMessage(__('The validated address was successfully saved as shipping address.'));
                } catch (Exception) {
                    $this->messageManager->addErrorMessage(__('An error occurred while saving the address.'));
                }
            }
        }

        if ($actionType === 'restore_original') {
            try {
                $this->restoreOriginalAddress($params);
                $this->messageManager
                    ->addSuccessMessage(__('The original shipping address was successfully restored.'));
            } catch (Exception) {
                $this->messageManager->addErrorMessage(__('An error occurred while saving the address.'));
            }
        }

        // Redirect to the same page
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setRefererOrBaseUrl();

        return $resultRedirect;
    }

    /**
     * @param array $params
     *
     * @return bool
     */
    private function isAddressValid(array $params): bool
    {
        return !empty($params['houseNumber']) &&
               !empty($params['street']) &&
               !empty($params['zipCode']) &&
               !empty($params['city']);
    }

    /**
     * @param $values
     *
     * @return void
     * @throws Exception
     */
    private function updateShippingAddress($values): void
    {
        $orderId         = $values['orderId'];
        $order           = $this->orderRepository->get($orderId);
        $shippingAddress = $order->getShippingAddress();

        if (!$shippingAddress) {
            throw new LocalizedException(__('Shipping address not found.'));
        }

        $shippingAddress
            ->setPostcode($values['zipCode'])
            ->setCity($values['city'])
            ->setStreet($values['street'] . ' ' . $values['houseNumber']);

        $order->addCommentToStatusHistory(
            __('Original shipping address was updated by %1.', $values['edited_by'])
        );

        $this->orderRepository->save($order);
    }

    /**
     * @throws LocalizedException
     */
    private function restoreOriginalAddress($values): void
    {
        $addressValidation = $this->addressValidationRepository->get((int)$values['addressValidationId']);

        $addressValidation
            ->setApiZipCode(null)
            ->setApiCity(null)
            ->setApiStreet(null)
            ->setApiHouseNumber(null)
            ->setApiAdditionalInformation(null)
            ->setManuZipCode(null)
            ->setManuCity(null)
            ->setManuStreet(null)
            ->setManuHouseNumber(null)
            ->setManuAdditionalInformation(null)
            ->setEditedBy($values['edited_by'])
            ->setEditedAt(date('Y-m-d H:i:s'));

        $this->addressValidationRepository->save($addressValidation);

        $orderId         = $values['orderId'];
        $order           = $this->orderRepository->get($orderId);
        $shippingAddress = $order->getShippingAddress();

        $shippingAddress
            ->setPostcode($addressValidation->getOrigZipCode())
            ->setCity($addressValidation->getOrigCity())
            ->setStreet($addressValidation->getOrigStreetFull());

        $order->addCommentToStatusHistory(
            __('Original shipping address was restored by %1.', $values['edited_by'])
        );

        $this->orderRepository->save($order);
    }
}
