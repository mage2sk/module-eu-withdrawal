<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Controller\Customer;

use Magento\Customer\Model\Customer;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\EuWithdrawal\Controller\Customer\Orders;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class OrdersTest extends TestCase
{
    use ConfigStubTrait;

    private array $json = [];
    private array $filters = [];
    private array $orders = [];
    private array $withRequest = [];
    private array $ineligible = [];
    private array $requestedIds = [];
    private bool $loggedIn = true;

    private function controller(array $values = [], array $flags = ['general/enabled' => true]): Orders
    {
        $result = $this->createStub(Json::class);
        $result->method('setData')->willReturnCallback(function ($data) use ($result) {
            $this->json = $data;
            return $result;
        });
        $jsonFactory = $this->createStub(JsonFactory::class);
        $jsonFactory->method('create')->willReturn($result);

        $customer = (new \ReflectionClass(Customer::class))->newInstanceWithoutConstructor();
        $customer->setIdFieldName('entity_id');
        $customer->setData(['entity_id' => 21, 'email' => 'jane@example.test', 'firstname' => 'Jane', 'lastname' => '']);

        $session = $this->createStub(CustomerSession::class);
        $session->method('isLoggedIn')->willReturnCallback(fn() => $this->loggedIn);
        $session->method('getCustomer')->willReturn($customer);

        $collection = $this->createStub(OrderCollection::class);
        $collection->method('addFieldToSelect')->willReturnSelf();
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('addFieldToFilter')->willReturnCallback(function ($field, $cond) use ($collection) {
            $this->filters[$field] = $cond;
            return $collection;
        });
        $collection->method('getColumnValues')->willReturnCallback(
            fn() => array_map(static fn($o) => $o->getEntityId(), $this->orders)
        );
        $collection->method('getIterator')->willReturnCallback(fn() => new \ArrayIterator($this->orders));
        $collectionFactory = $this->createStub(OrderCollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);

        $store = $this->createStub(StoreInterface::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createStub(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($store);

        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(
            static fn(\DateTimeInterface $dt) => $dt->format('M j, Y')
        );

        $service = $this->createStub(WithdrawalService::class);
        $service->method('getOrderIdsWithRequest')->willReturnCallback(function ($ids) {
            $this->requestedIds = $ids;
            return $this->withRequest;
        });
        $service->method('isOrderEligible')->willReturnCallback(
            fn($order) => !in_array((int)$order->getEntityId(), $this->ineligible, true)
        );

        return new Orders(
            $jsonFactory,
            $session,
            $collectionFactory,
            $storeManager,
            $timezone,
            $this->makeConfig($values, $flags),
            $service
        );
    }

    private function order(int $id, string $increment, string $createdAt = '2026-05-04 10:00:00'): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getEntityId')->willReturn($id);
        $order->method('getIncrementId')->willReturn($increment);
        $order->method('getCreatedAt')->willReturn($createdAt);
        $order->method('getGrandTotal')->willReturn('1999.9');
        $order->method('getOrderCurrencyCode')->willReturn('EUR');
        return $order;
    }

    public function testGuestsGetEmptyPayload(): void
    {
        $this->loggedIn = false;
        $this->controller()->execute();

        $this->assertSame(['loggedIn' => false, 'orders' => []], $this->json);
    }

    public function testDisabledModuleGetsEmptyPayload(): void
    {
        $this->controller([], [])->execute();

        $this->assertSame(['loggedIn' => false, 'orders' => []], $this->json);
    }

    public function testListsEligibleOrdersWithoutExistingRequests(): void
    {
        $this->orders = [
            $this->order(1, '000000001'),
            $this->order(2, '000000002'),
            $this->order(3, '000000003', ''),
            $this->order(4, '000000004'),
        ];
        $this->withRequest = [2 => true];
        $this->ineligible = [4];

        $before = time();
        $this->controller(['general/period_days' => '14', 'general/period_basis' => 'order'])->execute();

        $this->assertTrue($this->json['loggedIn']);
        $this->assertSame('jane@example.test', $this->json['email']);
        $this->assertSame('Jane', $this->json['name']);
        $this->assertSame([
            ['id' => '000000001', 'label' => '#000000001 - May 4, 2026 - 1,999.90 EUR'],
            ['id' => '000000003', 'label' => '#000000003 -  - 1,999.90 EUR'],
        ], $this->json['orders']);
        $this->assertSame([1, 2, 3, 4], $this->requestedIds);

        $this->assertSame(21, $this->filters['customer_id']);
        $this->assertSame(['nin' => ['canceled', 'closed']], $this->filters['state']);
        $threshold = strtotime($this->filters['created_at']['gteq'] . ' UTC');
        $this->assertEqualsWithDelta($before - 45 * 86400, $threshold, 5);
    }

    public function testShipmentBasisDoesNotFilterByOrderDate(): void
    {
        $this->controller(['general/period_basis' => 'shipment'])->execute();

        $this->assertArrayNotHasKey('created_at', $this->filters);
        $this->assertSame([], $this->json['orders']);
    }
}
