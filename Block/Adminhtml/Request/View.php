<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block\Adminhtml\Request;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Panth\EuWithdrawal\Controller\Adminhtml\Request\View as ViewController;
use Panth\EuWithdrawal\Model\Request as RequestModel;
use Panth\EuWithdrawal\Model\Source\Status;

class View extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly TimezoneInterface $timezone,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getWithdrawalRequest(): ?RequestModel
    {
        $model = $this->registry->registry(ViewController::REGISTRY_KEY);
        return $model instanceof RequestModel ? $model : null;
    }

    /**
     * @return array<int, string>
     */
    public function getStatusOptions(): array
    {
        return Status::getLabels();
    }

    public function getStatusLabel(int $status): string
    {
        return Status::getLabels()[$status] ?? 'Received';
    }

    public function formatDate2(?string $value): string
    {
        if (!$value) {
            return '';
        }
        try {
            $dt = new \DateTime($value, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return (string)$value;
        }
        return $this->timezone->formatDateTime($dt, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }

    public function getSaveUrl(): string
    {
        $request = $this->getWithdrawalRequest();
        return $this->getUrl('panth_euwithdrawal/request/save', [
            'request_id' => $request ? $request->getId() : 0,
        ]);
    }

    public function getOrderViewUrl(): string
    {
        $request = $this->getWithdrawalRequest();
        if (!$request || !$request->getOrderId()) {
            return '';
        }
        return $this->getUrl('sales/order/view', ['order_id' => $request->getOrderId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('panth_euwithdrawal/request/index');
    }
}
