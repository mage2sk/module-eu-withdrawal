<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\Model\AbstractModel;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;

/**
 * EU withdrawal request entity.
 *
 * @method int|null getOrderId()
 * @method string getIncrementId()
 * @method int getStoreId()
 * @method string getCustomerName()
 * @method string getCustomerEmail()
 * @method string|null getReason()
 * @method int getStatus()
 * @method string|null getWithdrawalContent()
 * @method string getProofReference()
 * @method string|null getRequestedAt()
 * @method int getConfirmationSent()
 * @method int getReminderSent()
 */
class Request extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(RequestResource::class);
    }
}
