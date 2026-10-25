<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Test\Unit\Setup\Patch\Data;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Parc\AddressValidation\Model\Config\Backend\Enable;
use Parc\AddressValidation\Model\ValidationStart;
use Parc\AddressValidation\Setup\Patch\Data\InitValidationStart;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class InitValidationStartTest extends TestCase
{
    private const NOW = '2026-10-25 10:00:00';

    // 2026-09-01 08:00:00 and 2026-09-03 12:30:00 UTC
    private const ENABLED_AT       = 1788249600;
    private const FIRST_VALIDATION = 1788438600;

    /**
     * @var ValidationStart&MockObject
     */
    private ValidationStart $validationStart;

    /**
     * What the database returns per query: existing start, "Enable" updated_at,
     * first validation.
     *
     * @var array<string, string|false>
     */
    private array $dbValues = [];

    protected function setUp(): void
    {
        $this->validationStart = $this->createMock(ValidationStart::class);
        $this->dbValues = [
            'start'      => false,
            'enabled_at' => false,
            'validation' => false,
        ];
    }

    public function testExistingValidationStartIsKept(): void
    {
        $this->dbValues['start']      = '2026-08-15 07:00:00';
        $this->dbValues['enabled_at'] = (string)self::ENABLED_AT;

        $this->validationStart->expects($this->never())->method('set');

        $this->createPatch(true)->apply();
    }

    public function testNothingHappensWhileDisabled(): void
    {
        $this->dbValues['enabled_at'] = (string)self::ENABLED_AT;

        $this->validationStart->expects($this->never())->method('set');

        $this->createPatch(false)->apply();
    }

    public function testEarlierOfBothTracesIsUsed(): void
    {
        $this->dbValues['enabled_at'] = (string)self::FIRST_VALIDATION;
        $this->dbValues['validation'] = (string)self::ENABLED_AT;

        $this->expectSavedStart('2026-09-01 08:00:00');

        $this->createPatch(true)->apply();
    }

    public function testSingleTraceIsUsed(): void
    {
        // Validation table recreated or empty: only "Enable" updated_at is left.
        $this->dbValues['enabled_at'] = (string)self::ENABLED_AT;

        $this->expectSavedStart('2026-09-01 08:00:00');

        $this->createPatch(true)->apply();
    }

    public function testNowIsUsedWithoutAnyTrace(): void
    {
        // "Enable" only in config.php/env.php, nothing validated yet.
        $this->expectSavedStart(self::NOW);

        $this->createPatch(true)->apply();
    }

    private function expectSavedStart(string $start): void
    {
        $this->validationStart->expects($this->once())->method('set')->with($start);
    }

    private function createPatch(bool $enabled): InitValidationStart
    {
        $connection = $this->createMock(AdapterInterface::class);
        $queries    = [];

        // Each select() records the table it reads, so fetchOne() can answer per query.
        $connection->method('select')->willReturnCallback(function () use (&$queries) {
            $select = $this->createMock(Select::class);
            $id     = spl_object_id($select);
            $queries[$id] = '';
            $select->method('from')->willReturnCallback(
                function ($table) use ($select, $id, &$queries) {
                    $queries[$id] = $table;
                    return $select;
                }
            );
            $select->method('where')->willReturnSelf();
            return $select;
        });
        $connection->method('fetchOne')->willReturnCallback(function ($select) use (&$queries) {
            return $queries[spl_object_id($select)] === 'parc_addressvalidation'
                ? $this->dbValues['validation']
                : $this->dbValues['enabled_at'];
        });

        $start = $this->dbValues['start'];
        $this->validationStart->method('get')->willReturn($start === false ? null : $start);

        $setup = $this->createMock(ModuleDataSetupInterface::class);
        $setup->method('getConnection')->willReturn($connection);
        $setup->method('getTable')->willReturnCallback(static fn (string $table) => $table);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path) => $path === Enable::XML_PATH_ENABLE && $enabled
        );

        // Real formatting for timestamps, fixed "now" for the fallback.
        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturnCallback(
            static fn ($format = null, $input = null) => $input === null ? self::NOW : gmdate('Y-m-d H:i:s', $input)
        );

        return new InitValidationStart($setup, $scopeConfig, $this->validationStart, $dateTime);
    }
}
