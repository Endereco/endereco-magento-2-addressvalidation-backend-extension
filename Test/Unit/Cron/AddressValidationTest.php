<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Test\Unit\Cron;

use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Sales\Model\OrderRepository;
use Parc\AddressValidation\Cron\AddressValidation;
use Parc\AddressValidation\Model\AddressValidationFactory;
use Parc\AddressValidation\Model\AddressValidationRepository;
use Parc\AddressValidation\Model\Config\Backend\Enable;
use Parc\AddressValidation\Model\RegionResolver;
use Parc\AddressValidation\Model\StreetLineBuilder;
use Parc\AddressValidation\Model\ValidationStart;
use Parc\AddressValidation\Service\EnderecoApi;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AddressValidationTest extends TestCase
{
    private const VALIDATION_START = '2026-10-16 09:30:00';

    private const NOW = '2026-10-25 10:00:00';

    /**
     * @var ResourceConnection&MockObject
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var AdapterInterface&MockObject
     */
    private AdapterInterface $connection;

    /**
     * @var OrderRepository&MockObject
     */
    private OrderRepository $orderRepository;

    /**
     * @var EnderecoApi&MockObject
     */
    private EnderecoApi $enderecoApi;

    /**
     * @var ValidationStart&MockObject
     */
    private ValidationStart $validationStartStorage;

    /**
     * Every where() call made on the order query, as [condition, value].
     *
     * @var array<int, array{0: string, 1: mixed}>
     */
    private array $whereCalls = [];

    /**
     * @var array<string, string>|null
     */
    private ?array $fromTable = null;

    protected function setUp(): void
    {
        $this->connection         = $this->createMock(AdapterInterface::class);
        $this->resourceConnection = $this->createMock(ResourceConnection::class);
        $this->resourceConnection->method('getConnection')->willReturn($this->connection);
        // Simulates an installation with table prefix "m2_".
        $this->resourceConnection->method('getTableName')
            ->willReturnCallback(static fn (string $table) => 'm2_' . $table);

        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnCallback(function ($name) use ($select) {
            $this->fromTable = $name;
            return $select;
        });
        $select->method('joinLeft')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($condition, $value = null) use ($select) {
            $this->whereCalls[] = [$condition, $value];
            return $select;
        });
        $this->connection->method('select')->willReturn($select);

        $this->orderRepository = $this->createMock(OrderRepository::class);
        $this->enderecoApi     = $this->createMock(EnderecoApi::class);
        $this->validationStartStorage = $this->createMock(ValidationStart::class);
    }

    public function testOnlyOrdersFromValidationStartOnAreSelected(): void
    {
        $this->connection->expects($this->once())->method('fetchAll')->willReturn([]);

        $this->createCron(self::VALIDATION_START)->execute();

        // Old orders - including ones that move into a configured status later -
        // are excluded by their creation date alone.
        $this->assertContains(['so.created_at >= ?', self::VALIDATION_START], $this->whereCalls);
        $this->assertContains(['so.status IN (?)', ['pending', 'processing']], $this->whereCalls);
        $this->assertContains(['so.is_virtual = ?', 0], $this->whereCalls);
        $this->assertContains(['pav.order_id IS NULL', null], $this->whereCalls);
        $this->assertSame(['so' => 'm2_sales_order'], $this->fromTable);
    }

    public function testExistingValidationStartIsNotTouched(): void
    {
        $this->validationStartStorage->expects($this->never())->method('set');
        $this->connection->method('fetchAll')->willReturn([]);

        $this->createCron(self::VALIDATION_START)->execute();
    }

    /**
     * Active module without a start (e.g. "Enable" set in config.php): start now
     * instead of skipping every order, and still leave older orders alone.
     */
    public function testMissingValidationStartIsSetToNow(): void
    {
        $this->validationStartStorage->method('get')->willReturn(null);
        $this->validationStartStorage->expects($this->once())->method('set')->with(self::NOW);
        $this->connection->expects($this->once())->method('fetchAll')->willReturn([]);

        $this->createCron('')->execute();

        $this->assertContains(['so.created_at >= ?', self::NOW], $this->whereCalls);
    }

    /**
     * Right after setup:upgrade the config cache can still lack the start the
     * update patch has just stored: use the stored one, never overwrite it.
     */
    public function testStartStoredBehindAStaleConfigCacheIsKept(): void
    {
        $this->validationStartStorage->method('get')->willReturn(self::VALIDATION_START);
        $this->validationStartStorage->expects($this->never())->method('set');
        $this->connection->expects($this->once())->method('fetchAll')->willReturn([]);

        $this->createCron('')->execute();

        $this->assertContains(['so.created_at >= ?', self::VALIDATION_START], $this->whereCalls);
    }

    public function testMissingValidationStartIsNotSetWhileDisabled(): void
    {
        $this->validationStartStorage->expects($this->never())->method('set');
        $this->connection->expects($this->never())->method('fetchAll');
        $this->orderRepository->expects($this->never())->method('get');
        $this->enderecoApi->expects($this->never())->method('execute');

        $this->createCron('', false)->execute();
    }

    private function createCron(string $validationStart, bool $enabled = true): AddressValidation
    {
        $config = [
            'parc_addressvalidation/general/orderstatus' => 'pending, processing',
            'parc_addressvalidation/general/validationstatus' => 'address_validation_pending',
            'parc_addressvalidation/sharpness/statuscodes' => 'address_not_found',
            Enable::XML_PATH_VALIDATION_START => $validationStart,
        ];
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $config[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path) => $path === Enable::XML_PATH_ENABLE && $enabled
        );

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn(self::NOW);

        $lockManager = $this->createMock(LockManagerInterface::class);
        $lockManager->method('lock')->willReturn(true);

        return new AddressValidation(
            $this->resourceConnection,
            $this->orderRepository,
            $this->enderecoApi,
            $this->createMock(AddressValidationFactory::class),
            $this->createMock(CountryFactory::class),
            $this->createMock(AddressValidationRepository::class),
            $scopeConfig,
            $lockManager,
            $this->createMock(LoggerInterface::class),
            $this->createMock(RegionResolver::class),
            $this->createMock(StreetLineBuilder::class),
            $this->validationStartStorage,
            $dateTime
        );
    }
}
