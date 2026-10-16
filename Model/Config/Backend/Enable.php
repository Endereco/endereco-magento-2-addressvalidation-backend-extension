<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Config\Value;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;

/**
 * Backend model of the "Enable" field: records the validation start the first
 * time the module is enabled. The cron only validates orders created from that
 * point on, so orders that already existed before the activation stay untouched.
 */
class Enable extends Value
{
    public const XML_PATH_VALIDATION_START = 'parc_addressvalidation/general/validation_start';

    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param Context               $context
     * @param Registry              $registry
     * @param ScopeConfigInterface  $config
     * @param TypeListInterface     $cacheTypeList
     * @param WriterInterface       $configWriter
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
        WriterInterface $configWriter,
        DateTime $dateTime,
        ?AbstractResource $resource = null,
        ?AbstractDb $resourceCollection = null,
        array $data = []
    ) {
        parent::__construct($context, $registry, $config, $cacheTypeList, $resource, $resourceCollection, $data);
        $this->configWriter = $configWriter;
        $this->dateTime     = $dateTime;
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
            && !$this->_config->getValue(self::XML_PATH_VALIDATION_START)
        ) {
            // UTC, same format as sales_order.created_at, so the cron can compare
            // both directly. The admin and config:set both clean the config cache
            // after saving, so the cron sees the new value on its next run.
            $this->configWriter->save(self::XML_PATH_VALIDATION_START, $this->dateTime->gmtDate());
        }

        return parent::afterSave();
    }
}
