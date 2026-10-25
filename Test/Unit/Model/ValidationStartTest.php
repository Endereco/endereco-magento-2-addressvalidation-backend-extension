<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Test\Unit\Model;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Parc\AddressValidation\Model\Config\Backend\Enable;
use Parc\AddressValidation\Model\ValidationStart;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ValidationStartTest extends TestCase
{
    /**
     * @var AdapterInterface&MockObject
     */
    private AdapterInterface $connection;

    /**
     * @var WriterInterface&MockObject
     */
    private WriterInterface $configWriter;

    /**
     * @var TypeListInterface&MockObject
     */
    private TypeListInterface $cacheTypeList;

    /**
     * @var array<int, array{0: string, 1: mixed}>
     */
    private array $whereCalls = [];

    private ValidationStart $validationStart;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(AdapterInterface::class);
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function ($condition, $value = null) use ($select) {
            $this->whereCalls[] = [$condition, $value];
            return $select;
        });
        $this->connection->method('select')->willReturn($select);

        $resourceConnection = $this->createMock(ResourceConnection::class);
        $resourceConnection->method('getConnection')->willReturn($this->connection);
        $resourceConnection->method('getTableName')->willReturnArgument(0);

        $this->configWriter  = $this->createMock(WriterInterface::class);
        $this->cacheTypeList = $this->createMock(TypeListInterface::class);

        $this->validationStart = new ValidationStart(
            $resourceConnection,
            $this->configWriter,
            $this->cacheTypeList
        );
    }

    /**
     * The value comes from core_config_data itself, so a stale config cache
     * cannot hide it.
     */
    public function testStoredValueIsReadFromTheDatabase(): void
    {
        $this->connection->method('fetchOne')->willReturn('2026-10-25 07:27:07');

        $this->assertSame('2026-10-25 07:27:07', $this->validationStart->get());
        $this->assertContains(['path = ?', Enable::XML_PATH_VALIDATION_START], $this->whereCalls);
        $this->assertContains(['scope = ?', 'default'], $this->whereCalls);
    }

    public function testMissingValueIsNull(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        $this->assertNull($this->validationStart->get());
    }

    public function testSettingCleansTheConfigCache(): void
    {
        $this->configWriter->expects($this->once())
            ->method('save')
            ->with(Enable::XML_PATH_VALIDATION_START, '2026-10-25 07:27:07');
        $this->cacheTypeList->expects($this->once())->method('cleanType')->with('config');

        $this->validationStart->set('2026-10-25 07:27:07');
    }
}
