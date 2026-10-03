<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;

class Request extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(RequestResource::class);
    }
}
