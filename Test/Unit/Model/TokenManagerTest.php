<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Magento\Framework\App\DeploymentConfig;
use Panth\EuWithdrawal\Model\TokenManager;
use PHPUnit\Framework\TestCase;

class TokenManagerTest extends TestCase
{
    private function manager($key): TokenManager
    {
        $config = $this->createStub(DeploymentConfig::class);
        $config->method('get')->willReturn($key);
        return new TokenManager($config);
    }

    public function testNoKeyMeansNoTokenAndNothingValid(): void
    {
        $manager = $this->manager(null);
        $this->assertSame('', $manager->generate('100', 'a@example.test'));
        $forged = hash_hmac('sha256', '100|a@example.test', 'panth_euwithdrawal_static_salt');
        $this->assertFalse($manager->isValid('100', 'a@example.test', $forged));
    }

    public function testSingleKeyTokensAreStable(): void
    {
        $manager = $this->manager('abc');
        $token = $manager->generate('100', 'A@Example.test ');
        $this->assertSame(hash_hmac('sha256', '100|a@example.test', 'abc'), $token);
        $this->assertTrue($manager->isValid('100', 'a@example.test', $token));
        $this->assertFalse($manager->isValid('101', 'a@example.test', $token));
    }

    public function testRotatedKeysSignWithNewestAndAcceptOlder(): void
    {
        $old = $this->manager('k1')->generate('100', 'a@example.test');
        $rotated = $this->manager("k1\nk2");
        $this->assertSame(hash_hmac('sha256', '100|a@example.test', 'k2'), $rotated->generate('100', 'a@example.test'));
        $this->assertTrue($rotated->isValid('100', 'a@example.test', $old));
    }
}
