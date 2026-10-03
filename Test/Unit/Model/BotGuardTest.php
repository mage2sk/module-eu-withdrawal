<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Magento\Framework\App\RequestInterface;
use Panth\EuWithdrawal\Model\BotGuard;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class BotGuardTest extends TestCase
{
    use ConfigStubTrait;

    private function request(array $params): RequestInterface
    {
        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn($key, $default = null) => $params[$key] ?? $default);
        return $request;
    }

    private function guard(bool $honeypot): BotGuard
    {
        return new BotGuard($this->makeConfig([], ['form/enable_honeypot' => $honeypot]));
    }

    public function testDisabledHoneypotNeverFlags(): void
    {
        $this->assertFalse($this->guard(false)->isBot($this->request(['contact_url' => 'http://spam.test'])));
    }

    public function testFilledHoneypotFieldIsBot(): void
    {
        $this->assertTrue($this->guard(true)->isBot($this->request(['contact_url' => 'x'])));
        $this->assertFalse($this->guard(true)->isBot($this->request(['contact_url' => '   '])));
    }

    public function testTooFastSubmissionWithJsIsBot(): void
    {
        $guard = $this->guard(true);

        $this->assertTrue($guard->isBot($this->request(['panth_js' => '1', 'panth_dt' => '500'])));
        $this->assertFalse($guard->isBot($this->request(['panth_js' => '1', 'panth_dt' => '1200'])));
        $this->assertFalse($guard->isBot($this->request(['panth_js' => '1', 'panth_dt' => '0'])));
    }

    public function testTimingIsIgnoredWhenJsDidNotRun(): void
    {
        $this->assertFalse($this->guard(true)->isBot($this->request(['panth_dt' => '10'])));
    }
}
