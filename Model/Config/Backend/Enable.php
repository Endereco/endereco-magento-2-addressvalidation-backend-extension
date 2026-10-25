<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Parc\AddressValidation\Model\ValidationStart;

/**
 * Backend model of the "Enable" field: records the validation start the first
 * time the module is enabled. The cron only validates orders created from that
 * point on, so orders that already existed before the activation stay untouched.
 */
class Enable extends Value
{
    public const XML_PATH_ENABLE = 'parc_addressvalidation/general/enable';

    public const XML_PATH_VALIDATION_START = 'parc_addressvalidation/general/validation_start';

    /**
     * @var ValidationStart
     */
    private ValidationStart $validationStart;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context               $context
     * @param Registry              $registry
     * @param ScopeConfigInterface  $config
     * @param TypeListInterface     $cacheTypeList
     * @param ValidationStart       $validationStart
     * @param DateTime              $dateTime
     * @param AbstractResource|null $resource
     * @param AbstractDb|null       $resourceCollection
     * @param array<string, mixed>  $data
     */
    public function __construct(
        Context $context,
        Registry $registry,
        ScopeConfigInterface $config,
        TypeListInterface $cacheTypeList,
        ValidationStart $validationStart,
        DateTime $dateTime,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->validationStart = $validationStart;
        $this->dateTime        = $dateTime;
    }

    /**
     * @return $this
     */
    public function afterSave()
    {
        // Set only once: re-enabling after a deactivation, or saving the section
        // again without touching "Enable", must keep the original start.
        if (
            (string)$this->getValue() === '1'
            && $this->validationStart->get() === null
        ) {
            // UTC, same format as sales_order.created_at, so the cron can compare
            // both directly.
            $this->validationStart->set((string)$this->dateTime->gmtDate());
        }

        return parent::afterSave();
    }
}
