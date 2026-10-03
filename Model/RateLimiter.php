<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\App\CacheInterface;

class RateLimiter
{
    private const CACHE_TAG = 'panth_euwithdrawal_ratelimit';
    private const WINDOW_SECONDS = 600;
    private const REFERENCE_WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function isLimited(string $ip, int $limit): bool
    {
        if ($limit <= 0 || $ip === '') {
            return false;
        }
        $key = self::CACHE_TAG . '_' . hash('sha256', $ip);
        $count = (int)$this->cache->load($key);
        $count++;
        $this->cache->save((string)$count, $key, [self::CACHE_TAG], self::WINDOW_SECONDS);
        return $count > $limit;
    }

    public function isReferenceLocked(string $reference, int $limit): bool
    {
        $key = $this->getReferenceKey($reference);
        if ($limit <= 0 || $key === null) {
            return false;
        }
        return (int)$this->cache->load($key) >= $limit;
    }

    public function registerReferenceFailure(string $reference): void
    {
        $key = $this->getReferenceKey($reference);
        if ($key === null) {
            return;
        }
        $count = (int)$this->cache->load($key) + 1;
        $this->cache->save((string)$count, $key, [self::CACHE_TAG], self::REFERENCE_WINDOW_SECONDS);
    }

    private function getReferenceKey(string $reference): ?string
    {
        $reference = strtoupper(trim($reference));
        if ($reference === '') {
            return null;
        }
        return self::CACHE_TAG . '_ref_' . hash('sha256', $reference);
    }
}
