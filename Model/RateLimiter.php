<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Lightweight per-IP throttle for the public order-lookup step, backed by the
 * default cache. Keeps order-number guessing slow without persisting anything.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\App\CacheInterface;

class RateLimiter
{
    private const CACHE_TAG = 'panth_euwithdrawal_ratelimit';
    private const WINDOW_SECONDS = 600;

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Register a hit and report whether the caller is now over the limit.
     * A limit of 0 disables throttling entirely.
     */
    public function isLimited(string $ip, int $limit): bool
    {
        if ($limit <= 0 || $ip === '') {
            return false;
        }
        $key = self::CACHE_TAG . '_' . md5($ip);
        $count = (int)$this->cache->load($key);
        $count++;
        $this->cache->save((string)$count, $key, [self::CACHE_TAG], self::WINDOW_SECONDS);
        return $count > $limit;
    }
}
