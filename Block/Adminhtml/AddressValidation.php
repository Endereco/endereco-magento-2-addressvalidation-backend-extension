<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Block\Adminhtml;

use Magento\Directory\Model\ResourceModel\Region\CollectionFactory as RegionCollectionFactory;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Block\Adminhtml\Order\View\Info;
use Magento\Sales\Model\Order;
use Magento\Framework\View\Element\Template\Context;
use Parc\AddressValidation\Api\Data\AddressValidationInterface;
use Parc\AddressValidation\Model\AddressValidationRepository;
use Magento\Framework\Data\Form\FormKey;

class AddressValidation extends Template
{
    protected Order $order;

    /**
     * @var AddressValidationRepository
     */
    protected AddressValidationRepository $addressValidationRepository;

    /**
     * @var FormKey
     */
    protected FormKey $formKey;

    /**
     * @var RegionCollectionFactory
     */
    protected RegionCollectionFactory $regionCollectionFactory;

    /**
     * Constructor
     *
     * @param AddressValidationRepository $addressValidationRepository
     * @param FormKey                     $formKey
     * @param RegionCollectionFactory     $regionCollectionFactory
     * @param Template\Context            $context
     * @param array                       $data
     */
    public function __construct(
        AddressValidationRepository $addressValidationRepository,
        FormKey                     $formKey,
        RegionCollectionFactory     $regionCollectionFactory,
        Context                     $context,
        array                       $data = []
    ) {
        $this->addressValidationRepository = $addressValidationRepository;
        $this->formKey                     = $formKey;
        $this->regionCollectionFactory     = $regionCollectionFactory;
        parent::__construct($context, $data);
    }

    public function afterToHtml(Info $subject, $result)
    {
        /*same as layout block name */
        $customBlock = $subject->getLayout()->getBlock('address_validation');
        if ($customBlock !== false && $subject->getNameInLayout() == 'order_info') {
            $order = $subject->getOrder();
            $customBlock->setOrder($order);
            $result = $result . $customBlock->toHtml();
        }

        return $result;
    }

    public function getValidatedAddress(): ?AddressValidationInterface
    {
        $order   = $this->getOrder();
        $orderId = (int)$order->getEntityId();

        return $this->addressValidationRepository->getByOrderIdOrNull($orderId);
    }

    public function setOrder(Order $order): static
    {
        $this->order = $order;

        return $this;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getFormActionUrl(): string
    {
        return $this->getUrl('addressvalidation/address/save');
    }

    /**
     * get form key
     *
     * @return string
     * @throws LocalizedException
     */
    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    /**
     * @param string $countryId
     *
     * @return array<int, string> region_id => name, sorted by name
     */
    public function getRegionOptions(string $countryId): array
    {
        $options = [];
        $regions = $this->regionCollectionFactory->create()->addCountryFilter($countryId);
        foreach ($regions as $region) {
            /** @var \Magento\Directory\Model\Region $region */
            $options[(int)$region->getRegionId()] = $region->getDefaultName();
        }
        asort($options);

        return $options;
    }
}
