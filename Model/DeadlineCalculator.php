<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Store\Model\ScopeInterface;

class DeadlineCalculator
{
    public const REFUND_DAYS = 14;
    private const MAX_SHIFT_DAYS = 366;

    public function __construct(
        private readonly Config $config,
        private readonly TimezoneInterface $timezone
    ) {
    }

    public function getDeadline(\DateTimeInterface $start, int $days, int $storeId): \DateTimeImmutable
    {
        $lastDay = \DateTimeImmutable::createFromInterface($start)
            ->setTimezone($this->getStoreTimezone($storeId))
            ->setTime(0, 0, 0)
            ->modify('+' . max(0, $days) . ' days');

        return $this->moveToWorkingDay($lastDay, $this->config->getPublicHolidays($storeId))
            ->setTime(23, 59, 59)
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    public function getRefundDeadline(string $requestedAt, int $storeId): ?\DateTimeImmutable
    {
        if (trim($requestedAt) === '') {
            return null;
        }
        try {
            $start = new \DateTimeImmutable($requestedAt, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return null;
        }
        return $this->getDeadline($start, self::REFUND_DAYS, $storeId);
    }

    public function moveToWorkingDay(\DateTimeImmutable $day, array $holidays): \DateTimeImmutable
    {
        $closed = array_fill_keys($holidays, true);
        for ($i = 0; $i < self::MAX_SHIFT_DAYS && $this->isClosed($day, $closed); $i++) {
            $day = $day->modify('+1 day');
        }
        return $day;
    }

    public function isWorkingDay(\DateTimeInterface $day, array $holidays): bool
    {
        return !$this->isClosed($day, array_fill_keys($holidays, true));
    }

    public function getStoreTimezone(int $storeId): \DateTimeZone
    {
        try {
            $name = (string)$this->timezone->getConfigTimezone(ScopeInterface::SCOPE_STORE, $storeId);
            if ($name !== '') {
                return new \DateTimeZone($name);
            }
        } catch (\Throwable $e) {
            return new \DateTimeZone('UTC');
        }
        return new \DateTimeZone('UTC');
    }

    private function isClosed(\DateTimeInterface $day, array $closed): bool
    {
        return (int)$day->format('N') >= 6 || isset($closed[$day->format('Y-m-d')]);
    }
}
