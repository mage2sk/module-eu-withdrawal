<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Shows the linked EU withdrawal request on the admin order view, with a link
 * to the request detail. Renders nothing if the order has no withdrawal request.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block\Adminhtml\Order;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Panth\EuWithdrawal\Model\Request as RequestModel;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\Source\Status;

class WithdrawalInfo extends Template
{
    private bool $loaded = false;
    private ?RequestModel $request = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getWithdrawalRequest(): ?RequestModel
    {
        if ($this->loaded) {
            return $this->request;
        }
        $this->loaded = true;

        $order = $this->registry->registry('current_order') ?: $this->registry->registry('sales_order');
        if (!$order || !$order->getId()) {
            return null;
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', (int)$order->getId())->setPageSize(1);
        $item = $collection->getFirstItem();
        $this->request = $item->getId() ? $item : null;
        return $this->request;
    }

    public function getStatusLabel(int $status): string
    {
        return (string)__(Status::getLabels()[$status] ?? 'Received');
    }

    public function getStatusClass(int $status): string
    {
        return 'panth-euw-badge panth-euw-badge--' . $status;
    }

    public function formatRequestedAt(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            $dt = new \DateTime($utc, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return (string)$utc;
        }
        return $this->timezone->formatDateTime($dt, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }

    public function getRequestViewUrl(int $requestId): string
    {
        return $this->getUrl('panth_euwithdrawal/request/view', ['request_id' => $requestId]);
    }

    protected function _toHtml(): string
    {
        return $this->getWithdrawalRequest() ? parent::_toHtml() : '';
    }
}
