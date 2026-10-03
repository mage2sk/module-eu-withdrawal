<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Block\Adminhtml;

use Magento\Backend\Block\Template\Context;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\DataObject;
use Magento\Framework\ObjectManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Panth\EuWithdrawal\Block\Adminhtml\Order\WithdrawalInfo;
use Panth\EuWithdrawal\Block\Adminhtml\Request\View;
use Panth\EuWithdrawal\Controller\Adminhtml\Request\View as ViewController;
use Panth\EuWithdrawal\Model\ResourceModel\Request\Collection;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Test\Unit\RequestModelTrait;
use PHPUnit\Framework\TestCase;

class AdminBlocksTest extends TestCase
{
    use RequestModelTrait;

    private ?ObjectManagerInterface $previousObjectManager = null;
    private array $registry = [];
    private array $urlCalls = [];
    private array $filters = [];

    protected function setUp(): void
    {
        try {
            $this->previousObjectManager = ObjectManager::getInstance();
        } catch (\RuntimeException $e) {
            $this->previousObjectManager = null;
        }
        // The backend Template constructor pulls optional helpers from the static object manager.
        ObjectManager::setInstance($this->createStub(ObjectManagerInterface::class));
    }

    protected function tearDown(): void
    {
        $property = new \ReflectionProperty(ObjectManager::class, '_instance');
        $property->setValue(null, $this->previousObjectManager);
    }

    private function context(): Context
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(function ($route = '', $params = []) {
            $this->urlCalls[] = [$route, $params];
            return 'https://admin.test/' . $route;
        });
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($url);
        return $context;
    }

    private function registry(): Registry
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn($key) => $this->registry[$key] ?? null);
        return $registry;
    }

    private function timezone(): TimezoneInterface
    {
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(
            static fn(\DateTimeInterface $dt) => 'ADM ' . $dt->format('Y-m-d H:i')
        );
        return $timezone;
    }

    private function view(): View
    {
        return new View($this->context(), $this->registry(), $this->timezone());
    }

    public function testViewReadsRegisteredRequestOnly(): void
    {
        $this->assertNull($this->view()->getWithdrawalRequest());

        $this->registry[ViewController::REGISTRY_KEY] = new DataObject(['request_id' => 1]);
        $this->assertNull($this->view()->getWithdrawalRequest(), 'non-Request objects are ignored');

        $request = $this->makeRequest(['request_id' => 1]);
        $this->registry[ViewController::REGISTRY_KEY] = $request;
        $this->assertSame($request, $this->view()->getWithdrawalRequest());
    }

    public function testViewStatusHelpers(): void
    {
        $view = $this->view();

        $this->assertCount(4, $view->getStatusOptions());
        $this->assertSame('Refunded', $view->getStatusLabel(3));
        $this->assertSame('Received', $view->getStatusLabel(77));
    }

    public function testViewDateFormatting(): void
    {
        $view = $this->view();

        $this->assertSame('', $view->formatDate2(null));
        $this->assertSame('nope', $view->formatDate2('nope'));
        $this->assertSame('ADM 2026-07-01 13:45', $view->formatDate2('2026-07-01 13:45:00'));
    }

    public function testViewUrls(): void
    {
        $view = $this->view();
        $this->assertSame('', $view->getOrderViewUrl());
        $view->getSaveUrl();
        $this->assertSame(['panth_euwithdrawal/request/save', ['request_id' => 0]], end($this->urlCalls));

        $this->registry[ViewController::REGISTRY_KEY] = $this->makeRequest(['request_id' => 6, 'order_id' => 99]);
        $view->getSaveUrl();
        $this->assertSame(['panth_euwithdrawal/request/save', ['request_id' => 6]], end($this->urlCalls));
        $this->assertSame('https://admin.test/sales/order/view', $view->getOrderViewUrl());
        $this->assertSame(['sales/order/view', ['order_id' => 99]], end($this->urlCalls));
        $this->assertSame('https://admin.test/panth_euwithdrawal/request/index', $view->getBackUrl());
    }

    private function info(array $items, int &$creates): WithdrawalInfo
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function ($f, $v) use ($collection) {
            $this->filters[] = [$f, $v];
            return $collection;
        });
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($items[0] ?? $this->makeRequest());
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use (&$creates, $collection) {
            $creates++;
            return $collection;
        });
        return new WithdrawalInfo($this->context(), $this->registry(), $factory, $this->timezone());
    }

    public function testInfoFindsRequestForCurrentOrderOnce(): void
    {
        $creates = 0;
        $request = $this->makeRequest(['request_id' => 3]);
        $this->registry['current_order'] = new DataObject(['id' => '40']);
        $info = $this->info([$request], $creates);

        $this->assertSame($request, $info->getWithdrawalRequest());
        $this->assertSame($request, $info->getWithdrawalRequest());
        $this->assertSame(1, $creates);
        $this->assertSame([['order_id', 40]], $this->filters);
    }

    public function testInfoFallsBackToSalesOrderKeyAndReturnsNullWithoutMatch(): void
    {
        $creates = 0;
        $this->registry['sales_order'] = new DataObject(['id' => 41]);

        $this->assertNull($this->info([], $creates)->getWithdrawalRequest());
        $this->assertSame([['order_id', 41]], $this->filters);
    }

    public function testInfoWithoutOrderSkipsQuery(): void
    {
        $creates = 0;
        $info = $this->info([], $creates);

        $this->assertNull($info->getWithdrawalRequest());
        $this->assertSame(0, $creates);
    }

    public function testInfoHelpers(): void
    {
        $creates = 0;
        $info = $this->info([], $creates);

        $this->assertSame('Rejected', $info->getStatusLabel(4));
        $this->assertSame('panth-euw-badge panth-euw-badge--2', $info->getStatusClass(2));
        $this->assertSame('', $info->formatRequestedAt(''));
        $this->assertSame('ADM 2026-01-31 23:59', $info->formatRequestedAt('2026-01-31 23:59:00'));
        $info->getRequestViewUrl(12);
        $this->assertSame(['panth_euwithdrawal/request/view', ['request_id' => 12]], end($this->urlCalls));
    }
}
