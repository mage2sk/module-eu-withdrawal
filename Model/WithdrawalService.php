<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Core domain logic for the EU right-of-withdrawal flow:
 *  - resolve an order from (increment id + email), store-safe and enumeration-safe;
 *  - compute the withdrawal window from config (period + basis = order/shipment date);
 *  - build the durable "content of the withdrawal" snapshot;
 *  - persist the request, record an order status-history comment, fan out emails.
 *
 * Record-only: it never auto-cancels or auto-refunds the order (the merchant
 * processes the refund within 14 days, per the directive).
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\Source\Status;
use Psr\Log\LoggerInterface;

class WithdrawalService
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly SearchCriteriaBuilder $searchCriteriaBuilder,
        private readonly RequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly CollectionFactory $collectionFactory,
        private readonly Config $config,
        private readonly Mail $mail,
        private readonly TimezoneInterface $timezone,
        private readonly Random $random,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Resolve an order by increment id + email. Returns null when there is no
     * exact (case-insensitive email) match — the caller MUST surface the same
     * generic error in every failure path to avoid order enumeration.
     */
    public function findOrder(string $incrementId, string $email): ?OrderInterface
    {
        $incrementId = trim($incrementId);
        $email = trim($email);
        if ($incrementId === '' || $email === '') {
            return null;
        }

        try {
            $criteria = $this->searchCriteriaBuilder
                ->addFilter('increment_id', $incrementId)
                ->setPageSize(1)
                ->create();
            $orders = $this->orderRepository->getList($criteria)->getItems();
            $order = $orders ? reset($orders) : null;
            if (!$order) {
                return null;
            }
            if (strcasecmp(trim((string)$order->getCustomerEmail()), $email) !== 0) {
                return null;
            }
            return $order;
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth EuWithdrawal] order lookup failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Start of the withdrawal window (date of receipt or order date per config).
     */
    public function getWindowStart(OrderInterface $order): \DateTimeImmutable
    {
        $basis = $this->config->getPeriodBasis((int)$order->getStoreId());
        $start = (string)$order->getCreatedAt();

        if ($basis === \Panth\EuWithdrawal\Model\Source\PeriodBasis::SHIPMENT && method_exists($order, 'getShipmentsCollection')) {
            try {
                foreach ($order->getShipmentsCollection() as $shipment) {
                    $shipped = (string)$shipment->getCreatedAt();
                    if ($shipped !== '') {
                        $start = $shipped;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->debug('[Panth EuWithdrawal] shipment lookup failed: ' . $e->getMessage());
            }
        }

        return new \DateTimeImmutable($start ?: 'now', new \DateTimeZone('UTC'));
    }

    public function getDeadline(OrderInterface $order): \DateTimeImmutable
    {
        $days = $this->config->getPeriodDays((int)$order->getStoreId());
        return $this->getWindowStart($order)->modify('+' . $days . ' days');
    }

    public function isWithinWindow(OrderInterface $order): bool
    {
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $now <= $this->getDeadline($order);
    }

    public function hasExistingRequest(OrderInterface $order): bool
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', (int)$order->getEntityId());
        return $collection->getSize() > 0;
    }

    public function getExistingRequest(OrderInterface $order): ?Request
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', (int)$order->getEntityId())->setPageSize(1);
        $item = $collection->getFirstItem();
        return $item->getId() ? $item : null;
    }

    /**
     * Build the durable "content of the withdrawal" snapshot (items + totals).
     */
    public function buildContentSnapshot(OrderInterface $order): string
    {
        $lines = [];
        $lines[] = (string)__('Order #%1', $order->getIncrementId());
        $lines[] = (string)__('Order date: %1', $this->formatUtc((string)$order->getCreatedAt()));
        $lines[] = '';
        $lines[] = (string)__('Items withdrawn:');
        foreach ($order->getAllVisibleItems() as $item) {
            $lines[] = sprintf(
                '- %s (SKU: %s) x %s',
                (string)$item->getName(),
                (string)$item->getSku(),
                (string)(int)$item->getQtyOrdered()
            );
        }
        $lines[] = '';
        $lines[] = (string)__('Order total: %1 %2', number_format((float)$order->getGrandTotal(), 2), (string)$order->getOrderCurrencyCode());

        return implode("\n", $lines);
    }

    /**
     * Persist a confirmed withdrawal, comment the order, and send notifications.
     *
     * @param array{name:string, email:string, reason?:string, ip?:string, user_agent?:string} $data
     * @throws AlreadyExistsException
     * @throws LocalizedException
     */
    public function submit(OrderInterface $order, array $data): Request
    {
        if ($this->hasExistingRequest($order)) {
            throw new AlreadyExistsException(__('A withdrawal request for this order has already been submitted.'));
        }
        if (!$this->isWithinWindow($order)) {
            throw new LocalizedException(__('The withdrawal period for this order has expired.'));
        }

        $storeId = (int)$order->getStoreId();
        // Store in UTC so formatDateTime(DateTime, UTC) converts correctly for display.
        $requestedAt = gmdate('Y-m-d H:i:s');

        /** @var Request $request */
        $request = $this->requestFactory->create();
        $request->setData([
            'order_id'           => (int)$order->getEntityId(),
            'increment_id'       => (string)$order->getIncrementId(),
            'store_id'           => $storeId,
            'customer_name'      => trim((string)($data['name'] ?? '')),
            'customer_email'     => trim((string)($data['email'] ?? '')),
            'reason'             => isset($data['reason']) ? trim((string)$data['reason']) : null,
            'status'             => Status::RECEIVED,
            'withdrawal_content' => $this->buildContentSnapshot($order),
            'proof_reference'    => $this->generateProofReference(),
            'requested_at'       => $requestedAt,
            'ip_address'         => $data['ip'] ?? null,
            'user_agent'         => isset($data['user_agent']) ? substr((string)$data['user_agent'], 0, 512) : null,
            'confirmation_sent'  => 0,
            'reminder_sent'      => 0,
        ]);
        $this->requestResource->save($request);

        $this->recordOnOrder($order, $request);
        $this->dispatchEmails($request, $order);

        return $request;
    }

    private function recordOnOrder(OrderInterface $order, Request $request): void
    {
        try {
            $comment = (string)__(
                'EU right of withdrawal exercised by the customer on %1. Proof reference: %2.',
                $this->formatUtc((string)$request->getRequestedAt()),
                $request->getProofReference()
            );
            $status = $this->config->getOrderStatus((int)$order->getStoreId());
            if (method_exists($order, 'addCommentToStatusHistory')) {
                $order->addCommentToStatusHistory($comment, $status !== '' ? $status : false, false);
            }
            $this->orderRepository->save($order);
        } catch (\Throwable $e) {
            // Recording on the order must never block the consumer's withdrawal.
            $this->logger->warning('[Panth EuWithdrawal] could not annotate order: ' . $e->getMessage());
        }
    }

    private function dispatchEmails(Request $request, OrderInterface $order): void
    {
        $storeId = (int)$order->getStoreId();
        if ($this->config->sendCustomerConfirmation($storeId)) {
            if ($this->mail->sendCustomerConfirmation($request)) {
                $request->setData('confirmation_sent', 1);
                try {
                    $this->requestResource->save($request);
                } catch (\Throwable $e) {
                    $this->logger->warning('[Panth EuWithdrawal] flag save failed: ' . $e->getMessage());
                }
            }
        }
        if ($this->config->sendAdminNotification($storeId)) {
            $this->mail->sendAdminNotification($request);
        }
    }

    private function generateProofReference(): string
    {
        return 'WDR-' . strtoupper($this->random->getRandomString(16, Random::CHARS_DIGITS . 'ABCDEFGHJKLMNPQRSTUVWXYZ'));
    }

    /**
     * Format a UTC datetime string in the configured scope timezone.
     * A \DateTime carrying UTC is passed so the converter applies the offset.
     */
    private function formatUtc(string $utc): string
    {
        if ($utc === '') {
            return '';
        }
        try {
            $dt = new \DateTime($utc, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return $utc;
        }
        return $this->timezone->formatDateTime($dt, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::SHORT);
    }
}
