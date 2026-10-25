<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Parc\AddressValidation\Model\Config\Backend\Enable;
use Parc\AddressValidation\Model\ValidationStart;

/**
 * Sets the validation start for installations that were already active before
 * it existed (updates from 1.0.0). Without it the cron would skip every order
 * until someone happens to save the configuration again.
 *
 * Version 1.0.0 stored no activation date, so the patch takes the earliest of
 * two traces that can only be at or after the real first activation: when
 * "Enable" was last switched on, and when the first order was validated.
 *
 * Keep this patch in future versions: Magento runs it once per installation,
 * so a shop updating from 1.0.0 straight to any later version still needs it.
 */
class InitValidationStart implements DataPatchInterface
{
    /**
     * @var ModuleDataSetupInterface
     */
    private ModuleDataSetupInterface $moduleDataSetup;

    /**
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var ValidationStart
     */
    private ValidationStart $validationStart;

    /**
     * @var DateTime
     */
    private DateTime $dateTime;

    /**
     * @param ModuleDataSetupInterface $moduleDataSetup
     * @param ScopeConfigInterface     $scopeConfig
     * @param ValidationStart          $validationStart
     * @param DateTime                 $dateTime
     */
    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup,
        ScopeConfigInterface $scopeConfig,
        ValidationStart $validationStart,
        DateTime $dateTime
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
        $this->scopeConfig     = $scopeConfig;
        $this->validationStart = $validationStart;
        $this->dateTime        = $dateTime;
    }

    /**
     * @return $this
     */
    public function apply()
    {
        // Fresh installations end here: "Enable" is not set yet, and the first
        // save of it sets the start (see the Enable backend model).
        if ($this->validationStart->get() !== null || !$this->scopeConfig->isSetFlag(Enable::XML_PATH_ENABLE)) {
            return $this;
        }

        $traces = array_filter(
            [$this->getEnabledAt(), $this->getFirstValidationAt()],
            static fn (?int $timestamp): bool => $timestamp !== null
        );

        // No trace when "Enable" comes from config.php/env.php and nothing has
        // been validated yet: start now.
        $start = $traces ? $this->dateTime->gmtDate(null, min($traces)) : $this->dateTime->gmtDate();

        $this->validationStart->set((string)$start);

        return $this;
    }

    /**
     * When "Enable" was last switched on. Saving it again with the same value
     * leaves updated_at untouched.
     *
     * UNIX_TIMESTAMP() because both columns read here are TIMESTAMP columns,
     * which MySQL converts to the connection's time zone when read directly.
     */
    private function getEnabledAt(): ?int
    {
        $connection = $this->moduleDataSetup->getConnection();

        return $this->toTimestamp($connection->fetchOne(
            $connection->select()
                ->from(
                    $this->moduleDataSetup->getTable('core_config_data'),
                    [new \Zend_Db_Expr('UNIX_TIMESTAMP(updated_at)')]
                )
                ->where('path = ?', Enable::XML_PATH_ENABLE)
                ->where('scope = ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
                ->where('scope_id = ?', 0)
        ));
    }

    /**
     * When the first order was validated. Missing if nothing has been validated
     * yet, or if the table was recreated (module:disable + setup:upgrade drops it).
     */
    private function getFirstValidationAt(): ?int
    {
        $connection = $this->moduleDataSetup->getConnection();

        return $this->toTimestamp($connection->fetchOne(
            $connection->select()
                ->from(
                    $this->moduleDataSetup->getTable('parc_addressvalidation'),
                    [new \Zend_Db_Expr('UNIX_TIMESTAMP(MIN(created_at))')]
                )
        ));
    }

    /**
     * @param mixed $value
     */
    private function toTimestamp($value): ?int
    {
        return is_numeric($value) && (int)$value > 0 ? (int)$value : null;
    }

    /**
     * @return string[]
     */
    public static function getDependencies()
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases()
    {
        return [];
    }
}
