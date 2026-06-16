<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\ResourceModel\Request;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\EuWithdrawal\Model\Request as RequestModel;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;

class Collection extends AbstractCollection
{
    /**
     * @var string
     */
    protected $_idFieldName = 'request_id';

    protected function _construct()
    {
        $this->_init(RequestModel::class, RequestResource::class);
    }
}
