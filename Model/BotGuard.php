<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\App\RequestInterface;

class BotGuard
{
    private const MIN_FILL_MS = 1200;

    public function __construct(
        private readonly Config $config
    ) {
    }

    public function isBot(RequestInterface $request, ?int $storeId = null): bool
    {
        if (!$this->config->isHoneypotEnabled($storeId)) {
            return false;
        }

        if (trim((string)$request->getParam('contact_url')) !== '') {
            return true;
        }

        $jsRan = (string)$request->getParam('panth_js') === '1';
        $elapsedMs = (int)$request->getParam('panth_dt');
        if ($jsRan && $elapsedMs > 0 && $elapsedMs < self::MIN_FILL_MS) {
            return true;
        }

        return false;
    }
}
