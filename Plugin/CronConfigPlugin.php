<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Plugin;

use Magento\Cron\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Psr\Log\LoggerInterface;

class CronConfigPlugin
{
    private const XML_PATH_ENABLE = 'parc_addressvalidation/general/enable';
    private const XML_PATH_CRON_EXPRESSION = 'parc_addressvalidation/general/cronschedule';

    protected ScopeConfigInterface $scopeConfig;

    protected LoggerInterface $logger;

    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param LoggerInterface      $logger
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        LoggerInterface      $logger

    ) {
        $this->scopeConfig = $scopeConfig;
        $this->logger      = $logger;
    }

    public function afterGetJobs(Config $subject, $result)
    {
        // Check if our cron job is even registered
        if (!isset($result['parcnetwork']['parc_addressvalidation'])) {
            return $result;
        }

        // Check if module is enabled
        $isEnabled = $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLE, ScopeInterface::SCOPE_STORE);

        if (!$isEnabled) {
            unset($result['parcnetwork']['parc_addressvalidation']);

            return $result;
        }

        // Get the cron expression from config
        $dynamicSchedule = $this->scopeConfig->getValue(self::XML_PATH_CRON_EXPRESSION, ScopeInterface::SCOPE_STORE);

        // Validate cron expression format (basic 5-field check)
        if ($dynamicSchedule && preg_match('/^([\*\d\/,-]+\s){4}[\*\d\/,-]+$/', $dynamicSchedule)) {
            $result['parcnetwork']['parc_addressvalidation']['schedule'] = $dynamicSchedule;
        } else {
            $this->logger->warning(sprintf(
                'Invalid or missing cron expression for parc_addressvalidation: "%s"',
                $dynamicSchedule ?? 'NULL'
            ));
        }

        return $result;
    }
}
