<?php
declare(strict_types=1);

namespace Parc\AddressValidation\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Parc\AddressValidation\Model\Config\Backend\Enable;
use Parc\AddressValidation\Model\ValidationStart;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class EnableTest extends TestCase
{
    private const NOW = '2026-10-16 09:30:00';

    /**
     * @var ValidationStart&MockObject
     */
    private ValidationStart $validationStart;

    private Enable $model;

    protected function setUp(): void
    {
        $this->validationStart = $this->createMock(ValidationStart::class);

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
            $this->createMock(ScopeConfigInterface::class),
            $this->createMock(TypeListInterface::class),
            $this->validationStart,
            $dateTime
        );
        $this->model->setPath(Enable::XML_PATH_ENABLE);
    }

    public function testFirstActivationSetsValidationStart(): void
    {
        $this->validationStart->method('get')->willReturn(null);

        $this->validationStart->expects($this->once())->method('set')->with(self::NOW);

        $this->model->setValue('1')->afterSave();
    }

    /**
     * Covers re-enabling after a deactivation as well as saving the section again
     * with "Enable" still on Yes: in both cases a start already exists.
     */
    public function testExistingValidationStartIsKept(): void
    {
        $this->validationStart->method('get')->willReturn('2026-10-01 08:00:00');

        $this->validationStart->expects($this->never())->method('set');

        $this->model->setValue('1')->afterSave();
    }

    public function testSavingDisabledDoesNotSetValidationStart(): void
    {
        $this->validationStart->method('get')->willReturn(null);

        $this->validationStart->expects($this->never())->method('set');

        $this->model->setValue('0')->afterSave();
    }
}
