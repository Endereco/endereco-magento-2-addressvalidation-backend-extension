<?php

declare(strict_types=1);

namespace Parc\AddressValidation\Model;

use Magento\Framework\App\Cache\Type\Config as ConfigCache;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Parc\AddressValidation\Model\Config\Backend\Enable;

/**
 * Reads and writes the validation start (UTC "Y-m-d H:i:s").
 *
 * Reads go to core_config_data directly, not through the config cache: the
 * config writer does not clean that cache, so right after the update patch or
 * the cron fallback has written a start, the cache can still say there is none -
 * and whoever trusted it would overwrite the real start with "now".
 */
class ValidationStart
{
    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var WriterInterface
     */
    private WriterInterface $configWriter;

    /**
     * @var TypeListInterface
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @param ResourceConnection $resourceConnection
     * @param WriterInterface    $configWriter
     * @param TypeListInterface  $cacheTypeList
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        WriterInterface $configWriter,
        TypeListInterface $cacheTypeList
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->configWriter       = $configWriter;
        $this->cacheTypeList      = $cacheTypeList;
    }

    /**
     * The stored start, or null if none is set.
     */
    public function get(): ?string
    {
        $connection = $this->resourceConnection->getConnection();
        $value = $connection->fetchOne(
            $connection->select()
                ->from($this->resourceConnection->getTableName('core_config_data'), ['value'])
                ->where('path = ?', Enable::XML_PATH_VALIDATION_START)
                ->where('scope = ?', ScopeConfigInterface::SCOPE_TYPE_DEFAULT)
                ->where('scope_id = ?', 0)
        );

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Stores the start and cleans the config cache, so other processes (the next
     * cron run, the admin) see it right away.
     */
    public function set(string $start): void
    {
        $this->configWriter->save(Enable::XML_PATH_VALIDATION_START, $start);
        $this->cacheTypeList->cleanType(ConfigCache::TYPE_IDENTIFIER);
    }
}
