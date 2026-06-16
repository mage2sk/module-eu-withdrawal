<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Transactional email sender for the EU withdrawal flow. Each method returns
 * true on success so callers can persist a "sent" flag and the batch cron can
 * retry failures — the directive requires the confirmation to actually reach
 * the consumer on a durable medium.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\DataObject;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\EuWithdrawal\Model\Source\Status;
use Psr\Log\LoggerInterface;

class Mail
{
    public function __construct(
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function sendCustomerConfirmation(Request $request): bool
    {
        $storeId = (int)$request->getStoreId();
        return $this->send(
            $this->config->getCustomerTemplate($storeId),
            $storeId,
            (string)$request->getCustomerEmail(),
            (string)$request->getCustomerName(),
            $this->buildVars($request, $storeId)
        );
    }

    public function sendAdminNotification(Request $request): bool
    {
        $storeId = (int)$request->getStoreId();
        $recipient = $this->config->getRecipientEmail($storeId);
        if ($recipient === '') {
            return false;
        }
        return $this->send(
            $this->config->getAdminTemplate($storeId),
            $storeId,
            $recipient,
            (string)__('Store Administrator'),
            $this->buildVars($request, $storeId)
        );
    }

    public function sendRefundReminder(Request $request): bool
    {
        $storeId = (int)$request->getStoreId();
        $recipient = $this->config->getRecipientEmail($storeId);
        if ($recipient === '') {
            return false;
        }
        return $this->send(
            'panth_euwithdrawal_refund_reminder',
            $storeId,
            $recipient,
            (string)__('Store Administrator'),
            $this->buildVars($request, $storeId)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildVars(Request $request, int $storeId): array
    {
        $labels = Status::getLabels();
        $requestedAt = (string)$request->getRequestedAt();
        $formatted = '';
        if ($requestedAt !== '') {
            try {
                $dt = new \DateTime($requestedAt, new \DateTimeZone('UTC'));
                $formatted = $this->timezone->formatDateTime($dt, \IntlDateFormatter::FULL, \IntlDateFormatter::MEDIUM);
            } catch (\Throwable $e) {
                $formatted = $requestedAt;
            }
        }

        $data = new DataObject([
            'proof_reference'    => (string)$request->getProofReference(),
            'increment_id'       => (string)$request->getIncrementId(),
            'customer_name'      => (string)$request->getCustomerName(),
            'customer_email'     => (string)$request->getCustomerEmail(),
            'reason'             => (string)$request->getReason(),
            'requested_at'       => $formatted,
            'withdrawal_content' => (string)$request->getWithdrawalContent(),
            'status_label'       => (string)__($labels[(int)$request->getStatus()] ?? 'Received'),
            'refund_policy'      => $this->config->getRefundPolicyText($storeId),
        ]);

        return [
            'data' => $data,
            'withdrawal' => $data,
        ];
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function send(string $template, int $storeId, string $toEmail, string $toName, array $vars): bool
    {
        if ($template === '' || $toEmail === '') {
            return false;
        }
        try {
            $this->inlineTranslation->suspend();
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($template)
                ->setTemplateOptions(['area' => 'frontend', 'store' => $storeId])
                ->setTemplateVars($vars)
                ->setFromByScope($this->config->getSenderIdentity($storeId), $storeId)
                ->addTo($toEmail, $toName)
                ->getTransport();
            $transport->sendMessage();
            return true;
        } catch (\Throwable $e) {
            $this->logger->error('[Panth EuWithdrawal] email send failed (' . $template . '): ' . $e->getMessage());
            return false;
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
