<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block\Adminhtml\Request;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panth\EuWithdrawal\Controller\Adminhtml\Request\View as ViewController;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\DeadlineCalculator;
use Panth\EuWithdrawal\Model\Request as RequestModel;
use Panth\EuWithdrawal\Model\Source\Status;
use Panth\EuWithdrawal\Model\WithdrawalService;

class View extends Template
{
    private bool $orderLoaded = false;
    private ?OrderInterface $order = null;

    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly TimezoneInterface $timezone,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly WithdrawalService $service,
        private readonly DeadlineCalculator $deadlines,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getWithdrawalRequest(): ?RequestModel
    {
        $model = $this->registry->registry(ViewController::REGISTRY_KEY);
        return $model instanceof RequestModel ? $model : null;
    }

    public function getStatusOptions(): array
    {
        return Status::getLabels();
    }

    public function getStatusLabel(int $status): string
    {
        return Status::getLabels()[$status] ?? 'Received';
    }

    public function getRejectedStatus(): int
    {
        return Status::REJECTED;
    }

    public function isStatusEmailEnabled(): bool
    {
        $request = $this->getWithdrawalRequest();
        return $request !== null && $this->config->notifyStatusChange((int)$request->getStoreId());
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

    public function getWithdrawalDeadline(): string
    {
        $order = $this->getOrder();
        if ($order === null) {
            return '';
        }
        try {
            if ($this->service->isAwaitingShipment($order)) {
                return (string)__('Not started - the period starts when the last parcel ships');
            }
            return $this->formatDay($this->service->getDeadline($order));
        } catch (\Throwable $e) {
            return '';
        }
    }

    public function getRequestedWithinPeriod(): ?bool
    {
        $request = $this->getWithdrawalRequest();
        $order = $this->getOrder();
        if ($request === null || $order === null || !$request->getRequestedAt()) {
            return null;
        }
        try {
            if ($this->service->isAwaitingShipment($order)) {
                return true;
            }
            $requestedAt = new \DateTimeImmutable((string)$request->getRequestedAt(), new \DateTimeZone('UTC'));
            return $requestedAt <= $this->service->getDeadline($order);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getRefundDeadline(): string
    {
        $request = $this->getWithdrawalRequest();
        if ($request === null) {
            return '';
        }
        $deadline = $this->deadlines->getRefundDeadline(
            (string)$request->getRequestedAt(),
            (int)$request->getStoreId()
        );
        return $deadline === null ? '' : $this->formatDay($deadline);
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

    private function getOrder(): ?OrderInterface
    {
        if ($this->orderLoaded) {
            return $this->order;
        }
        $this->orderLoaded = true;
        $request = $this->getWithdrawalRequest();
        if ($request === null || !(int)$request->getOrderId()) {
            return null;
        }
        try {
            $this->order = $this->orderRepository->get((int)$request->getOrderId());
        } catch (\Throwable $e) {
            $this->order = null;
        }
        return $this->order;
    }

    private function formatDay(\DateTimeImmutable $utc): string
    {
        return (string)$this->timezone->formatDateTime(
            new \DateTime($utc->format('Y-m-d H:i:s'), new \DateTimeZone('UTC')),
            \IntlDateFormatter::LONG,
            \IntlDateFormatter::NONE
        );
    }
}
