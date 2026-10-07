<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Controller;

use Magento\Backend\Model\View\Result\Page as BackendPage;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\EuWithdrawal\Model\RateLimiter;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use Panth\EuWithdrawal\Test\Unit\RequestModelTrait;
use PHPUnit\Framework\TestCase;

/**
 * Shared frontend plumbing: request params, captured redirects, messages,
 * page handles/titles, an in-memory persistor and an in-memory cache.
 */
abstract class ControllerTestCase extends TestCase
{
    use ConfigStubTrait;
    use RequestModelTrait;

    protected array $params = [];
    protected array $server = [];
    protected ?string $redirectPath = null;
    protected array $messages = [];
    protected array $handles = [];
    protected ?string $title = null;
    protected array $persisted = [];
    protected array $cache = [];
    protected ?Page $page = null;

    protected function setUp(): void
    {
        $this->params = [];
        $this->server = [];
        $this->redirectPath = null;
        $this->messages = [];
        $this->handles = [];
        $this->title = null;
        $this->persisted = [];
        $this->cache = [];
    }

    protected function httpRequest(): Http
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(
            fn($key, $default = null) => $this->params[$key] ?? $default
        );
        $request->method('getServer')->willReturnCallback(fn($key = null) => $this->server[$key] ?? null);
        return $request;
    }

    protected function redirectFactory(): RedirectFactory
    {
        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path) use ($redirect) {
            $this->redirectPath = $path;
            return $redirect;
        });
        $factory = $this->createStub(RedirectFactory::class);
        $factory->method('create')->willReturn($redirect);
        return $factory;
    }

    protected function messageManager(): ManagerInterface
    {
        $manager = $this->createStub(ManagerInterface::class);
        foreach (['Error', 'Notice', 'Success', 'Warning'] as $type) {
            $manager->method('add' . $type . 'Message')->willReturnCallback(
                function ($message) use ($manager, $type) {
                    $this->messages[] = [strtolower($type), (string)$message];
                    return $manager;
                }
            );
        }
        return $manager;
    }

    protected function pageFactory(): PageFactory
    {
        $title = $this->createStub(Title::class);
        $title->method('set')->willReturnCallback(function ($value) {
            $this->title = (string)$value;
        });
        $title->method('prepend')->willReturnCallback(function ($value) {
            $this->title = (string)$value;
        });
        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($title);

        // The backend page subclass also offers setActiveMenu() for admin actions.
        $page = $this->createStub(BackendPage::class);
        $page->method('getConfig')->willReturn($config);
        $page->method('addHandle')->willReturnCallback(function ($handle) use ($page) {
            $this->handles[] = $handle;
            return $page;
        });
        $page->method('setActiveMenu')->willReturnSelf();
        $this->page = $page;

        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);
        return $factory;
    }

    protected function persistor(): DataPersistorInterface
    {
        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('set')->willReturnCallback(function ($key, $value) {
            $this->persisted[$key] = $value;
        });
        $persistor->method('get')->willReturnCallback(fn($key) => $this->persisted[$key] ?? null);
        $persistor->method('clear')->willReturnCallback(function ($key) {
            unset($this->persisted[$key]);
        });
        return $persistor;
    }

    protected function rateLimiter(): RateLimiter
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('load')->willReturnCallback(fn($key) => $this->cache[$key] ?? false);
        $cache->method('save')->willReturnCallback(function ($data, $key) {
            $this->cache[$key] = $data;
            return true;
        });
        return new RateLimiter($cache);
    }

    protected function tokenManager(): TokenManager
    {
        $deployment = $this->createStub(DeploymentConfig::class);
        $deployment->method('get')->willReturn('unit-key');
        return new TokenManager($deployment);
    }

    protected function token(string $increment, string $email): string
    {
        return hash_hmac('sha256', $increment . '|' . strtolower($email), 'unit-key');
    }

    protected function lastMessage(): array
    {
        return $this->messages[count($this->messages) - 1] ?? [];
    }
}
