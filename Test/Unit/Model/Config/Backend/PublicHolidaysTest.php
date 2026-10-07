<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Model\ResourceModel\AbstractResource;
use Magento\Framework\Registry;
use Panth\EuWithdrawal\Model\Config\Backend\PublicHolidays;
use PHPUnit\Framework\TestCase;

class PublicHolidaysTest extends TestCase
{
    private function model(string $value): PublicHolidays
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));

        $model = new PublicHolidays(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class),
            $this->createStub(AbstractResource::class)
        );
        $model->setValue($value);
        return $model;
    }

    public function testValidListIsNormalised(): void
    {
        $model = $this->model(" 2026-12-26\r\n2026-12-25\n\n2026-12-25 ");

        $model->beforeSave();

        $this->assertSame("2026-12-25\n2026-12-26", $model->getValue());
    }

    public function testEmptyValueIsAllowed(): void
    {
        $model = $this->model('');

        $model->beforeSave();

        $this->assertSame('', $model->getValue());
    }

    public function testInvalidDateIsRefused(): void
    {
        $model = $this->model("2026-12-25\n25.12.2026\n2026-02-30");

        try {
            $model->beforeSave();
            $this->fail('An invalid date must be refused.');
        } catch (LocalizedException $e) {
            $this->assertStringContainsString('"25.12.2026", "2026-02-30"', $e->getMessage());
            $this->assertStringContainsString('YYYY-MM-DD', $e->getMessage());
        }
        $this->assertSame("2026-12-25\n25.12.2026\n2026-02-30", $model->getValue());
    }
}
