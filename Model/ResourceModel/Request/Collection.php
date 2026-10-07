<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\ResourceModel\Request;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\EuWithdrawal\Model\Request as RequestModel;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'request_id';

    protected function _construct()
    {
        $this->_init(RequestModel::class, RequestResource::class);
    }

    public function addCustomerFilter(int $customerId): self
    {
        if ($customerId <= 0) {
            $this->getSelect()->where('1 = 0');
            return $this;
        }
        $this->getSelect()->join(
            ['panth_euw_order' => $this->getTable('sales_order')],
            'panth_euw_order.entity_id = main_table.order_id',
            []
        )->where('panth_euw_order.customer_id = ?', $customerId);
        return $this;
    }
}
