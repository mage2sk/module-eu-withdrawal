<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

class HolidayList
{
    public static function split(string $raw): array
    {
        $parts = preg_split('/[\r\n,;]+/', $raw) ?: [];
        return array_values(array_filter(array_map('trim', $parts), static fn(string $line) => $line !== ''));
    }

    public static function isValidDate(string $value): bool
    {
        if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $match)) {
            return false;
        }
        return checkdate((int)$match[2], (int)$match[3], (int)$match[1]);
    }

    public static function parse(string $raw): array
    {
        $dates = array_values(array_unique(array_filter(self::split($raw), [self::class, 'isValidDate'])));
        sort($dates);
        return $dates;
    }

    public static function getInvalid(string $raw): array
    {
        return array_values(array_filter(
            self::split($raw),
            static fn(string $line) => !self::isValidDate($line)
        ));
    }
}
