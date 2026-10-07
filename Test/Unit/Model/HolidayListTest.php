<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Panth\EuWithdrawal\Model\HolidayList;
use PHPUnit\Framework\TestCase;

class HolidayListTest extends TestCase
{
    public function testSplitAcceptsNewlinesCommasAndSemicolons(): void
    {
        $this->assertSame(
            ['2026-12-25', '2026-12-26', '2027-01-01', '2027-04-05'],
            HolidayList::split(" 2026-12-25\r\n2026-12-26,2027-01-01 ;2027-04-05\n\n")
        );
        $this->assertSame([], HolidayList::split(''));
    }

    public function testDateValidation(): void
    {
        $this->assertTrue(HolidayList::isValidDate('2028-02-29'));
        $this->assertFalse(HolidayList::isValidDate('2027-02-29'));
        $this->assertFalse(HolidayList::isValidDate('2026-13-01'));
        $this->assertFalse(HolidayList::isValidDate('25.12.2026'));
        $this->assertFalse(HolidayList::isValidDate('2026-1-5'));
        $this->assertFalse(HolidayList::isValidDate('2026-12-25x'));
    }

    public function testParseKeepsValidDatesSortedAndUnique(): void
    {
        $this->assertSame(
            ['2026-12-25', '2027-01-01'],
            HolidayList::parse("2027-01-01\n2026-12-25\nnope\n2026-12-25")
        );
    }

    public function testGetInvalidListsOnlyBadLines(): void
    {
        $this->assertSame(['25/12/2026', '2026-02-30'], HolidayList::getInvalid("2026-12-25\n25/12/2026\n2026-02-30"));
        $this->assertSame([], HolidayList::getInvalid("2026-12-25\n"));
    }
}
