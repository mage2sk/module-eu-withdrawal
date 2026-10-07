<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model\ResourceModel;

use Magento\Framework\DB\Select;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\ResourceModel\Request\Collection;
use PHPUnit\Framework\TestCase;

class CollectionTest extends TestCase
{
    private array $calls = [];

    private function collection(): Collection
    {
        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($cond, $value = null) use ($select) {
            $this->calls[] = ['where', $cond, $value];
            return $select;
        });
        $select->method('join')->willReturnCallback(function ($table, $cond, $cols) use ($select) {
            $this->calls[] = ['join', $table, $cond, $cols];
            return $select;
        });
        $resource = $this->createStub(RequestResource::class);
        $resource->method('getTable')->willReturnCallback(static fn($t) => 'pfx_' . $t);

        $collection = (new \ReflectionClass(Collection::class))->newInstanceWithoutConstructor();
        (new \ReflectionProperty($collection, '_select'))->setValue($collection, $select);
        (new \ReflectionProperty($collection, '_resource'))->setValue($collection, $resource);
        return $collection;
    }

    public function testCustomerFilterJoinsSalesOrder(): void
    {
        $collection = $this->collection();

        $this->assertSame($collection, $collection->addCustomerFilter(8));
        $this->assertSame([
            ['join', ['panth_euw_order' => 'pfx_sales_order'], 'panth_euw_order.entity_id = main_table.order_id', []],
            ['where', 'panth_euw_order.customer_id = ?', 8],
        ], $this->calls);
    }

    public function testGuestCustomerMatchesNothing(): void
    {
        $this->collection()->addCustomerFilter(0);

        $this->assertSame([['where', '1 = 0', null]], $this->calls);
    }
}
