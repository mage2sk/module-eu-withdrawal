<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Single ViewModel backing every storefront template (form / confirm / success
 * on both Luma and Hyva). Theme-agnostic: templates ask isHyva() only to pick
 * markup, never to change behaviour.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\ViewModel;

use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Panth\Core\Helper\Theme;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalContext;
use Panth\EuWithdrawal\Model\WithdrawalService;

class Withdrawal implements ArgumentInterface
{
    private const FORM_KEY = 'panth_euwithdrawal_form';

    public function __construct(
        private readonly Config $config,
        private readonly Theme $theme,
        private readonly UrlInterface $url,
        private readonly RequestInterface $request,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly TimezoneInterface $timezone,
        private readonly TokenManager $tokenManager,
        private readonly WithdrawalService $service,
        private readonly WithdrawalContext $context,
        private readonly FormKey $formKey
    ) {
    }

    public function getFormKey(): string
    {
        return $this->formKey->getFormKey();
    }

    public function isHyva(): bool
    {
        return $this->theme->isHyva();
    }

    public function isEnabled(): bool
    {
        return $this->config->isEnabled();
    }

    public function getButtonLabel(): string
    {
        return $this->config->getButtonLabel();
    }

    public function getIntroText(): string
    {
        return $this->config->getIntroText();
    }

    public function getExcludedProductsText(): string
    {
        return $this->config->getExcludedProductsText();
    }

    public function getReturnShippingText(): string
    {
        return $this->config->getReturnShippingText();
    }

    public function getRefundPolicyText(): string
    {
        return $this->config->getRefundPolicyText();
    }

    public function askReason(): bool
    {
        return $this->config->askReason();
    }

    public function isHoneypotEnabled(): bool
    {
        return $this->config->isHoneypotEnabled();
    }

    public function getPeriodDays(): int
    {
        return $this->config->getPeriodDays();
    }

    public function getFormUrl(): string
    {
        return $this->url->getUrl('withdrawal');
    }

    public function getLookupUrl(): string
    {
        return $this->url->getUrl('withdrawal/index/lookup');
    }

    public function getSubmitUrl(): string
    {
        return $this->url->getUrl('withdrawal/index/submit');
    }

    public function getCustomerOrdersUrl(): string
    {
        return $this->url->getUrl('withdrawal/customer/orders');
    }

    public function getPageTitle(): string
    {
        return $this->config->getButtonLabel();
    }

    /* ---------- Step 1 prefill ---------- */

    /**
     * @return array{increment_id:string, email:string, name:string, reason:string, token:string}
     */
    public function getPrefill(): array
    {
        $retained = $this->dataPersistor->get(self::FORM_KEY);
        $this->dataPersistor->clear(self::FORM_KEY);
        if (is_array($retained)) {
            return [
                'increment_id' => (string)($retained['increment_id'] ?? ''),
                'email' => (string)($retained['email'] ?? ''),
                'name' => (string)($retained['name'] ?? ''),
                'reason' => (string)($retained['reason'] ?? ''),
                'token' => '',
            ];
        }

        // Signed deep link from an order email: ?o=<increment>&e=<email>&t=<token>
        $increment = trim((string)$this->request->getParam('o'));
        $email = trim((string)$this->request->getParam('e'));
        $token = trim((string)$this->request->getParam('t'));
        if ($increment !== '' && $email !== '' && $this->tokenManager->isValid($increment, $email, $token)) {
            return [
                'increment_id' => $increment,
                'email' => $email,
                'name' => '',
                'reason' => '',
                'token' => $token,
            ];
        }

        return ['increment_id' => '', 'email' => '', 'name' => '', 'reason' => '', 'token' => ''];
    }

    /* ---------- Step 2 confirm ---------- */

    public function getOrder(): ?OrderInterface
    {
        return $this->context->getOrder();
    }

    public function getContextName(): string
    {
        return $this->context->getName();
    }

    public function getContextEmail(): string
    {
        return $this->context->getEmail();
    }

    public function getContextReason(): string
    {
        return $this->context->getReason();
    }

    public function getContextToken(): string
    {
        return $this->context->getToken();
    }

    /**
     * @return \Magento\Sales\Api\Data\OrderItemInterface[]
     */
    public function getOrderItems(): array
    {
        $order = $this->getOrder();
        return $order ? $order->getAllVisibleItems() : [];
    }

    public function formatPrice(float $value): string
    {
        $order = $this->getOrder();
        if ($order && method_exists($order, 'formatPrice')) {
            // formatPrice() returns HTML (<span class="price">…</span>); strip
            // it to plain text so templates can escape it safely.
            return trim(strip_tags((string)$order->formatPrice($value)));
        }
        return number_format($value, 2);
    }

    public function getDeadlineFormatted(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return '';
        }
        $deadline = $this->service->getDeadline($order)->format('Y-m-d H:i:s');
        return $this->timezone->formatDateTime($deadline, \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
    }

    /* ---------- Success / proof ---------- */

    public function getProofReference(): string
    {
        return $this->context->getProofReference();
    }

    public function getProofIncrementId(): string
    {
        return $this->context->getProofIncrementId();
    }

    public function getProofEmail(): string
    {
        return $this->context->getProofEmail();
    }

    /* ---------- Status (existing request) ---------- */

    public function getStatusRequest(): ?\Panth\EuWithdrawal\Model\Request
    {
        return $this->context->getStatusRequest();
    }

    public function getStatusLabel(int $status): string
    {
        $labels = \Panth\EuWithdrawal\Model\Source\Status::getLabels();
        return (string)__($labels[$status] ?? 'Received');
    }

    public function formatStoredDate(?string $utc): string
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
}
