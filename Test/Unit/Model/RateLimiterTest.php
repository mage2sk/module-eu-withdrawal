<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Magento\Framework\App\CacheInterface;
use Panth\EuWithdrawal\Model\RateLimiter;
use PHPUnit\Framework\TestCase;

class RateLimiterTest extends TestCase
{
    private array $store = [];
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->store = [];
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn ($key) => $this->store[$key] ?? false);
        $cache->method('save')->willReturnCallback(function ($data, $key) {
            $this->store[$key] = $data;
            return true;
        });
        $this->limiter = new RateLimiter($cache);
    }

    public function testReferenceLocksAfterLimitAcrossCallers(): void
    {
        for ($i = 0; $i < 4; $i++) {
            $this->assertFalse($this->limiter->isReferenceLocked('000000123', 5));
            $this->limiter->registerReferenceFailure('000000123');
        }
        $this->assertFalse($this->limiter->isReferenceLocked('000000123', 5));
        $this->limiter->registerReferenceFailure(' 000000123 ');
        $this->assertTrue($this->limiter->isReferenceLocked('000000123', 5));
        $this->assertFalse($this->limiter->isReferenceLocked('000000124', 5));
    }

    public function testReferenceLimitZeroDisablesLock(): void
    {
        $this->limiter->registerReferenceFailure('A1');
        $this->assertFalse($this->limiter->isReferenceLocked('A1', 0));
        $this->assertFalse($this->limiter->isReferenceLocked('', 1));
    }

    public function testIpLimitUsesSha256Keys(): void
    {
        $this->assertFalse($this->limiter->isLimited('10.0.0.1', 1));
        $this->assertTrue($this->limiter->isLimited('10.0.0.1', 1));
        $this->assertArrayHasKey('panth_euwithdrawal_ratelimit_' . hash('sha256', '10.0.0.1'), $this->store);
    }
}
