<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_EuWithdrawal::request_manage';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly RequestResource $requestResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        try {
            $collection = $this->filter->getCollection($this->collectionFactory->create());
            $deleted = 0;
            foreach ($collection as $item) {
                $this->requestResource->delete($item);
                $deleted++;
            }
            $this->messageManager->addSuccessMessage(
                __('A total of %1 record(s) have been deleted.', $deleted)
            );
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not delete records: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
