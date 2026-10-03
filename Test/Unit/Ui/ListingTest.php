<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Ui;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\System\Store as SystemStore;
use Panth\EuWithdrawal\Ui\Component\Listing\Column\Actions;
use Panth\EuWithdrawal\Ui\Component\Listing\Column\OrderLink;
use Panth\EuWithdrawal\Ui\Component\Listing\Column\StoreView;
use Panth\EuWithdrawal\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\TestCase;

class ListingTest extends TestCase
{
    private function url(): UrlInterface
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(
            static fn($route, $params = []) => 'https://admin.test/' . $route . '/id/' . reset($params)
        );
        return $url;
    }

    private function escaper(): Escaper
    {
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn($v) => htmlspecialchars((string)$v));
        $escaper->method('escapeUrl')->willReturnCallback(static fn($v) => htmlspecialchars((string)$v));
        return $escaper;
    }

    public function testActionsAddViewAndPostDeleteLinks(): void
    {
        $column = new Actions(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->url(),
            [],
            ['name' => 'actions']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [['request_id' => 4], ['request_id' => null]]]]);

        $actions = $result['data']['items'][0]['actions'];
        $this->assertSame('https://admin.test/panth_euwithdrawal/request/view/id/4', $actions['view']['href']);
        $this->assertSame('https://admin.test/panth_euwithdrawal/request/delete/id/4', $actions['delete']['href']);
        $this->assertTrue($actions['delete']['post']);
        $this->assertSame('Delete withdrawal request', (string)$actions['delete']['confirm']['title']);
        $this->assertArrayNotHasKey('actions', $result['data']['items'][1]);
    }

    public function testColumnsLeaveSourcesWithoutItemsUntouched(): void
    {
        $context = $this->createStub(ContextInterface::class);
        $factory = $this->createStub(UiComponentFactory::class);
        $source = ['data' => ['totalRecords' => 0]];

        $this->assertSame($source, (new Actions($context, $factory, $this->url()))->prepareDataSource($source));
        $this->assertSame($source, (new OrderLink($context, $factory, $this->url(), $this->escaper()))->prepareDataSource($source));
    }

    public function testOrderLinkLinksWhenOrderIdKnown(): void
    {
        $column = new OrderLink(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->url(),
            $this->escaper(),
            [],
            ['name' => 'increment_id']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [
            ['increment_id' => '000000001', 'order_id' => 11],
            ['increment_id' => '000000002', 'order_id' => null],
            ['increment_id' => ''],
        ]]]);

        $items = $result['data']['items'];
        $this->assertSame('<a href="https://admin.test/sales/order/view/id/11">000000001</a>', $items[0]['increment_id']);
        $this->assertSame('000000002', $items[1]['increment_id']);
        $this->assertSame('', $items[2]['increment_id']);
    }

    public function testOrderLinkEscapesTheOrderNumber(): void
    {
        $column = new OrderLink(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $this->url(),
            $this->escaper(),
            [],
            ['name' => 'increment_id']
        );

        $result = $column->prepareDataSource(['data' => ['items' => [
            ['increment_id' => '<b>1</b>', 'order_id' => 11],
            ['increment_id' => 'A&B', 'order_id' => null],
        ]]]);

        $items = $result['data']['items'];
        $this->assertSame(
            '<a href="https://admin.test/sales/order/view/id/11">&lt;b&gt;1&lt;/b&gt;</a>',
            $items[0]['increment_id']
        );
        $this->assertSame('A&amp;B', $items[1]['increment_id']);
    }

    private function storeColumn(): StoreView
    {
        $systemStore = $this->createStub(SystemStore::class);
        $systemStore->method('getStoresStructure')->willReturnCallback(static function ($root, $ids) {
            $children = [];
            foreach ($ids as $id) {
                $children[$id] = ['label' => 'Store <' . $id . '>'];
            }
            return [1 => ['children' => [1 => ['children' => $children]]]];
        });
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn($v) => htmlspecialchars((string)$v));

        return new StoreView(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            $systemStore,
            $escaper,
            [],
            ['name' => 'store_id']
        );
    }

    public function testStoreViewRendersLabelsAllStoresAndEmpty(): void
    {
        $result = $this->storeColumn()->prepareDataSource(['data' => ['items' => [
            ['store_id' => '1,2'],
            ['store_id' => [0, 3]],
            ['store_id' => '0'],
            ['store_id' => ''],
            [],
        ]]]);

        $items = $result['data']['items'];
        $this->assertSame('Store &lt;1&gt;<br/>Store &lt;2&gt;', $items[0]['store_id']);
        $this->assertSame('All Store Views', $items[1]['store_id']);
        $this->assertSame('All Store Views', $items[2]['store_id']);
        $this->assertSame('', $items[3]['store_id']);
        $this->assertSame('', $items[4]['store_id']);
    }

    private array $where = [];

    private function dbCollection(): AbstractDb
    {
        $select = $this->createStub(Select::class);
        $select->method('where')->willReturnCallback(function ($cond) use ($select) {
            $this->where[] = $cond;
            return $select;
        });
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')->willReturnCallback(static fn($c) => '`' . $c . '`');
        $connection->method('quoteInto')->willReturnCallback(
            static fn($text, $value) => str_replace('?', "'" . $value . "'", $text)
        );
        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($connection);
        $collection->method('getSelect')->willReturn($select);
        return $collection;
    }

    private function filter($value): Filter
    {
        $filter = new Filter();
        $filter->setValue($value);
        return $filter;
    }

    public function testFulltextFilterOrsLikeConditionsAndEscapesWildcards(): void
    {
        $filter = new LikeFulltextFilter(['proof_reference', 'customer_email', 7]);

        $filter->apply($this->dbCollection(), $this->filter(' 50%_off '));

        $this->assertSame(
            ["`proof_reference` LIKE '%50\\%\\_off%' OR `customer_email` LIKE '%50\\%\\_off%'"],
            $this->where
        );
    }

    public function testFulltextFilterTruncatesLongTerms(): void
    {
        (new LikeFulltextFilter(['c']))->apply($this->dbCollection(), $this->filter(str_repeat('a', 300)));

        $this->assertSame("`c` LIKE '%" . str_repeat('a', 200) . "%'", $this->where[0]);
    }

    public function testFulltextFilterIgnoresBlankValuesNonDbCollectionsAndNoColumns(): void
    {
        (new LikeFulltextFilter(['c']))->apply($this->dbCollection(), $this->filter('   '));
        (new LikeFulltextFilter(['c']))->apply($this->dbCollection(), $this->filter(['array']));
        (new LikeFulltextFilter([]))->apply($this->dbCollection(), $this->filter('term'));
        (new LikeFulltextFilter(['c']))->apply($this->createStub(Collection::class), $this->filter('term'));

        $this->assertSame([], $this->where);
    }
}
