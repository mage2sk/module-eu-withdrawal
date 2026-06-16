<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Appends a direct, pre-filled (signed) withdrawal link below the items table
 * in order emails — satisfying checklist section 3 ("a direct link to the
 * withdrawal function in every order confirmation email") without overriding
 * any merchant email template. Order emails are theme-agnostic, so a single
 * inline-styled snippet works for both Luma and Hyva storefronts.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Plugin;

use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Sales\Block\Order\Email\Items;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Psr\Log\LoggerInterface;

class OrderEmailWithdrawalLink
{
    public function __construct(
        private readonly Config $config,
        private readonly TokenManager $tokenManager,
        private readonly WithdrawalService $service,
        private readonly UrlInterface $url,
        private readonly Escaper $escaper,
        private readonly LoggerInterface $logger
    ) {
    }

    public function afterToHtml(Items $subject, string $result): string
    {
        try {
            $order = $subject->getOrder();
            if (!$order || !$order->getIncrementId()) {
                return $result;
            }
            $storeId = (int)$order->getStoreId();
            if (!$this->config->isEnabled($storeId) || !$this->config->injectOrderEmailLink($storeId)) {
                return $result;
            }
            // Only advertise a usable link while the window is still open.
            if (!$this->service->isWithinWindow($order)) {
                return $result;
            }

            $email = (string)$order->getCustomerEmail();
            $increment = (string)$order->getIncrementId();
            $token = $this->tokenManager->generate($increment, $email);
            $href = $this->url->getUrl('withdrawal', [
                '_scope' => $storeId,
                '_nosid' => true,
                '_query' => ['o' => $increment, 'e' => $email, 't' => $token],
            ]);

            return $result . $this->render($href, $storeId);
        } catch (\Throwable $e) {
            $this->logger->warning('[Panth EuWithdrawal] order-email link failed: ' . $e->getMessage());
            return $result;
        }
    }

    private function render(string $href, int $storeId): string
    {
        $label = $this->escaper->escapeHtml($this->config->getButtonLabel($storeId));
        $href = $this->escaper->escapeUrl($href);
        $intro = $this->escaper->escapeHtml(__('Changed your mind?')->render());
        $body = $this->escaper->escapeHtml(
            __('As an EU consumer you have the right to withdraw from this purchase. You can cancel your order online below — no account needed.')->render()
        );

        return <<<HTML
<table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0;">
  <tr>
    <td style="padding:16px;border:1px solid #e0e0e0;border-radius:6px;font-family:Arial,Helvetica,sans-serif;">
      <p style="margin:0 0 6px;font-size:15px;font-weight:bold;color:#1a1a1a;">{$intro}</p>
      <p style="margin:0 0 12px;font-size:13px;color:#555;line-height:1.5;">{$body}</p>
      <a href="{$href}" style="display:inline-block;padding:10px 18px;background:#1979c3;color:#ffffff;text-decoration:none;border-radius:4px;font-size:14px;font-weight:bold;">{$label}</a>
    </td>
  </tr>
</table>
HTML;
    }
}
