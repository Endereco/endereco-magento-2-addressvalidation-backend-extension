<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Parc\AddressValidation\Model\Config\Backend\Enable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnableTest extends TestCase
{
    private const NOW = '2026-10-16 09:30:00';

    /**
     * @var ScopeConfigInterface&MockObject
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @var WriterInterface&MockObject
     */
    private WriterInterface $configWriter;

    private Enable $model;

    protected function setUp(): void
    {
        $this->scopeConfig  = $this->createMock(ScopeConfigInterface::class);
        $this->configWriter = $this->createMock(WriterInterface::class);

        $dateTime = $this->createMock(DateTime::class);
        $dateTime->method('gmtDate')->willReturn(self::NOW);

        // Context's getters have no native return types, so the mock would return
        // null; afterSave() needs the event and cache managers.
        $context = $this->createMock(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createMock(EventManagerInterface::class));
        $context->method('getCacheManager')->willReturn($this->createMock(CacheInterface::class));

        $this->model = new Enable(
            $context,
            $this->createMock(Registry::class),
            $this->scopeConfig,
            $this->createMock(TypeListInterface::class),
            $this->configWriter,
            $dateTime
        );
        $this->model->setPath('parc_addressvalidation/general/enable');
    }

    public function testFirstActivationSetsValidationStart(): void
    {
        $this->givenStoredValidationStart(null);

        $this->configWriter->expects($this->once())
            ->method('save')
            ->with(Enable::XML_PATH_VALIDATION_START, self::NOW);

        $this->model->setValue('1')->afterSave();
    }

    /**
     * Covers re-enabling after a deactivation as well as saving the section again
     * with "Enable" still on Yes: in both cases a start already exists.
     */
    public function testExistingValidationStartIsKept(): void
    {
        $this->givenStoredValidationStart('2026-10-01 08:00:00');

        $this->configWriter->expects($this->never())->method('save');

        $this->model->setValue('1')->afterSave();
    }

    public function testSavingDisabledDoesNotSetValidationStart(): void
    {
        $this->givenStoredValidationStart(null);

        $this->configWriter->expects($this->never())->method('save');

        $this->model->setValue('0')->afterSave();
    }

    private function givenStoredValidationStart(?string $value): void
    {
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $path === Enable::XML_PATH_VALIDATION_START ? $value : null
        );
    }
}
