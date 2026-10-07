<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;
use Panth\EuWithdrawal\Model\HolidayList;

class PublicHolidays extends Value
{
    public function beforeSave()
    {
        $raw = (string)$this->getValue();
        $invalid = HolidayList::getInvalid($raw);
        if ($invalid) {
            throw new LocalizedException(__(
                'Public holidays: "%1" is not a valid date. Enter one date per line in the format YYYY-MM-DD, for example 2026-12-25.',
                implode('", "', array_slice($invalid, 0, 5))
            ));
        }
        $this->setValue(implode("\n", HolidayList::parse($raw)));
        return parent::beforeSave();
    }
}
