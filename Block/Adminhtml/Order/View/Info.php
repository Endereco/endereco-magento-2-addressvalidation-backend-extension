<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Block\Adminhtml\Order\View;

use Magento\Backend\Block\Template\Context;
use Magento\Customer\Api\CustomerMetadataInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Customer\Model\Metadata\ElementFactory;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\Registry;
use Magento\Sales\Helper\Admin;
use Magento\Sales\Model\Order\Address\Renderer;

class Info extends \Magento\Sales\Block\Adminhtml\Order\View\Info
{
    /**
     * Constructor
     *
     * @param CountryFactory            $countryFactory
     * @param Context                   $context
     * @param Registry                  $registry
     * @param Admin                     $adminHelper
     * @param GroupRepositoryInterface  $groupRepository
     * @param CustomerMetadataInterface $metadata
     * @param ElementFactory            $elementFactory
     * @param Renderer                  $addressRenderer
     * @param array                     $data
     */
    public function __construct(
        CountryFactory $countryFactory,
        Context $context,
        Registry $registry,
        Admin $adminHelper,
        GroupRepositoryInterface $groupRepository,
        CustomerMetadataInterface $metadata,
        ElementFactory $elementFactory,
        Renderer $addressRenderer,
        array $data = []
    ) {
        $this->_countryFactory = $countryFactory;
        parent::__construct(
            $context,
            $registry,
            $adminHelper,
            $groupRepository,
            $metadata,
            $elementFactory,
            $addressRenderer,
            $data
        );
    }

    public function getCountryname($countryCode)
    {
        $country = $this->_countryFactory->create()->loadByCode($countryCode);

        return $country->getName();
    }
}
