<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Request extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('panth_eu_withdrawal_request', 'request_id');
    }
}
