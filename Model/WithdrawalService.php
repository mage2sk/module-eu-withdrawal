<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Math\Random;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\Source\PeriodBasis;
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
        private readonly LoggerInterface $logger,
        private readonly DeadlineCalculator $deadlines
    ) {
    }

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

    public function getWindowStart(OrderInterface $order): \DateTimeImmutable
    {
        $start = (string)$order->getCreatedAt();
        if ($this->usesShipmentBasis($order)) {
            $shipped = $this->getLatestShipmentDate($order);
            if ($shipped !== null) {
                $start = $shipped;
            }
        }

        return new \DateTimeImmutable($start ?: 'now', new \DateTimeZone('UTC'));
    }

    public function getDeadline(OrderInterface $order): \DateTimeImmutable
    {
        $storeId = (int)$order->getStoreId();
        return $this->deadlines->getDeadline(
            $this->getWindowStart($order),
            $this->config->getPeriodDays($storeId),
            $storeId
        );
    }

    public function isAwaitingShipment(OrderInterface $order): bool
    {
        return $this->usesShipmentBasis($order) && $this->getLatestShipmentDate($order) === null;
    }

    public function isWithinWindow(OrderInterface $order): bool
    {
        if ($this->isAwaitingShipment($order)) {
            return true;
        }
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        return $now <= $this->getDeadline($order);
    }

    private function usesShipmentBasis(OrderInterface $order): bool
    {
        return $this->config->getPeriodBasis((int)$order->getStoreId()) === PeriodBasis::SHIPMENT
            && method_exists($order, 'getShipmentsCollection');
    }

    private function getLatestShipmentDate(OrderInterface $order): ?string
    {
        $latest = null;
        try {
            foreach ($order->getShipmentsCollection() as $shipment) {
                $shipped = (string)$shipment->getCreatedAt();
                if ($shipped !== '' && ($latest === null || strtotime($shipped) > strtotime($latest))) {
                    $latest = $shipped;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->debug('[Panth EuWithdrawal] shipment lookup failed: ' . $e->getMessage());
        }
        return $latest;
    }

    public function getIneligibilityReason(OrderInterface $order): ?Phrase
    {
        $state = (string)$order->getState();
        if (in_array($state, [Order::STATE_CANCELED, Order::STATE_CLOSED], true)) {
            return __('This order is not eligible for withdrawal.');
        }
        if (!$this->isWithinWindow($order)) {
            return __('The withdrawal period for this order has expired.');
        }
        return null;
    }

    public function isOrderEligible(OrderInterface $order): bool
    {
        return $this->getIneligibilityReason($order) === null;
    }

    public function hasExistingRequest(OrderInterface $order): bool
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', (int)$order->getEntityId());
        return $collection->getSize() > 0;
    }

    public function getOrderIdsWithRequest(array $orderIds): array
    {
        $orderIds = array_values(array_unique(array_filter(array_map('intval', $orderIds))));
        if (!$orderIds) {
            return [];
        }
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', ['in' => $orderIds]);
        $ids = [];
        foreach ($collection as $item) {
            $ids[(int)$item->getData('order_id')] = true;
        }
        return $ids;
    }

    public function getExistingRequest(OrderInterface $order): ?Request
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('order_id', (int)$order->getEntityId())->setPageSize(1);
        $item = $collection->getFirstItem();
        return $item->getId() ? $item : null;
    }

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

    public function submit(OrderInterface $order, array $data): Request
    {
        if ($this->hasExistingRequest($order)) {
            throw new AlreadyExistsException(__('A withdrawal request for this order has already been submitted.'));
        }
        $ineligible = $this->getIneligibilityReason($order);
        if ($ineligible !== null) {
            throw new LocalizedException($ineligible);
        }

        $storeId = (int)$order->getStoreId();

        $requestedAt = gmdate('Y-m-d H:i:s');

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
