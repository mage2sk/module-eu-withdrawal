<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Panth\EuWithdrawal\Model\DeadlineCalculator;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DeadlineCalculatorTest extends TestCase
{
    use ConfigStubTrait;

    private ?string $zone = null;
    private ?\Throwable $zoneError = null;

    private function calculator(array $values = []): DeadlineCalculator
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('getConfigTimezone')->willReturnCallback(function () {
            if ($this->zoneError) {
                throw $this->zoneError;
            }
            return (string)$this->zone;
        });
        return new DeadlineCalculator($this->makeConfig($values), $timezone);
    }

    private function utc(string $value): \DateTimeImmutable
    {
        return new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
    }

    public static function weekdayCases(): array
    {
        return [
            'friday stays' => ['2026-10-02 10:00:00', '2026-10-16 23:59:59'],
            'saturday moves to monday' => ['2026-10-03 10:00:00', '2026-10-19 23:59:59'],
            'sunday moves to monday' => ['2026-10-04 10:00:00', '2026-10-19 23:59:59'],
            'monday stays' => ['2026-10-05 10:00:00', '2026-10-19 23:59:59'],
        ];
    }

    #[DataProvider('weekdayCases')]
    public function testWeekendDeadlinesMoveToMonday(string $start, string $expected): void
    {
        $deadline = $this->calculator()->getDeadline($this->utc($start), 14, 1);

        $this->assertSame($expected, $deadline->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $deadline->getTimezone()->getName());
    }

    public function testHolidayOnTheLastDayMovesToTheNextWorkingDay(): void
    {
        $calculator = $this->calculator(['general/public_holidays' => '2026-10-16']);

        $this->assertSame(
            '2026-10-19 23:59:59',
            $calculator->getDeadline($this->utc('2026-10-02 10:00:00'), 14, 1)->format('Y-m-d H:i:s')
        );
    }

    public function testHolidayAfterAWeekendMovesFurther(): void
    {
        $calculator = $this->calculator(['general/public_holidays' => "2026-10-19\n2026-10-20"]);

        $this->assertSame(
            '2026-10-21 23:59:59',
            $calculator->getDeadline($this->utc('2026-10-03 10:00:00'), 14, 1)->format('Y-m-d H:i:s')
        );
    }

    public function testHolidayOnAWorkingDayBeforeTheDeadlineChangesNothing(): void
    {
        $calculator = $this->calculator(['general/public_holidays' => '2026-10-15']);

        $this->assertSame(
            '2026-10-16 23:59:59',
            $calculator->getDeadline($this->utc('2026-10-02 10:00:00'), 14, 1)->format('Y-m-d H:i:s')
        );
    }

    public function testYearBoundaryWithHolidaysAndWeekend(): void
    {
        $calculator = $this->calculator(['general/public_holidays' => "2026-12-31\n2027-01-01"]);

        $deadline = $calculator->getDeadline($this->utc('2026-12-17 15:00:00'), 14, 1);

        $this->assertSame('2027-01-04 23:59:59', $deadline->format('Y-m-d H:i:s'));
    }

    public function testYearBoundaryWithoutHolidays(): void
    {
        $deadline = $this->calculator()->getDeadline($this->utc('2026-12-19 15:00:00'), 14, 1);

        $this->assertSame('2027-01-04 23:59:59', $deadline->format('Y-m-d H:i:s'));
    }

    public function testWeekdayIsCheckedInTheStoreTimezone(): void
    {
        $this->zone = 'Europe/Berlin';

        $deadline = $this->calculator()->getDeadline($this->utc('2026-10-01 23:30:00'), 14, 1);

        $this->assertSame('2026-10-16 21:59:59', $deadline->format('Y-m-d H:i:s'));
        $berlin = $deadline->setTimezone(new \DateTimeZone('Europe/Berlin'));
        $this->assertSame('2026-10-16 23:59:59', $berlin->format('Y-m-d H:i:s'));
    }

    public function testTimezoneErrorsFallBackToUtc(): void
    {
        $this->zoneError = new \RuntimeException('no zone');

        $this->assertSame('UTC', $this->calculator()->getStoreTimezone(1)->getName());
        $this->assertSame(
            '2026-10-16 23:59:59',
            $this->calculator()->getDeadline($this->utc('2026-10-02 10:00:00'), 14, 1)->format('Y-m-d H:i:s')
        );
    }

    public function testRefundDeadlineIsFourteenDaysAfterTheNoticeOnAWorkingDay(): void
    {
        $calculator = $this->calculator(['general/public_holidays' => '2026-10-19']);

        $this->assertSame(
            '2026-10-20 23:59:59',
            $calculator->getRefundDeadline('2026-10-03 08:00:00', 1)->format('Y-m-d H:i:s')
        );
        $this->assertSame(
            '2026-10-16 23:59:59',
            $calculator->getRefundDeadline('2026-10-02 08:00:00', 1)->format('Y-m-d H:i:s')
        );
    }

    public function testRefundDeadlineNeedsAValidDate(): void
    {
        $calculator = $this->calculator();

        $this->assertNull($calculator->getRefundDeadline('', 1));
        $this->assertNull($calculator->getRefundDeadline('   ', 1));
        $this->assertNull($calculator->getRefundDeadline('not a date', 1));
    }

    public function testWorkingDayHelpers(): void
    {
        $calculator = $this->calculator();
        $holidays = ['2026-12-25'];

        $this->assertTrue($calculator->isWorkingDay($this->utc('2026-12-24'), $holidays));
        $this->assertFalse($calculator->isWorkingDay($this->utc('2026-12-25'), $holidays));
        $this->assertFalse($calculator->isWorkingDay($this->utc('2026-12-26'), $holidays));
        $this->assertFalse($calculator->isWorkingDay($this->utc('2026-12-27'), $holidays));
        $this->assertSame(
            '2026-12-28',
            $calculator->moveToWorkingDay($this->utc('2026-12-25'), $holidays)->format('Y-m-d')
        );
    }

    public function testNegativePeriodIsTreatedAsZero(): void
    {
        $this->assertSame(
            '2026-10-02 23:59:59',
            $this->calculator()->getDeadline($this->utc('2026-10-02 10:00:00'), -5, 1)->format('Y-m-d H:i:s')
        );
    }
}
