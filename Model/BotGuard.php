<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Layered, FPC-safe bot detection for the public withdrawal form:
 *   1. Honeypot — a hidden field bots tend to fill.
 *   2. JS speed-trap — JS stamps how long the form was on screen; an
 *      implausibly fast submit from a JS-running client is a bot. (No-JS
 *      clients are NOT blocked here, so the form stays accessible — the
 *      honeypot + per-IP rate limit still cover them.)
 *
 * No server-rendered timestamp/nonce is used, so the form markup is safe to
 * cache in Full Page Cache.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\App\RequestInterface;

class BotGuard
{
    /** Minimum plausible fill time, in milliseconds, for a JS-capable client. */
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

        // 1) Honeypot must stay empty.
        if (trim((string)$request->getParam('contact_url')) !== '') {
            return true;
        }

        // 2) JS speed-trap — only judges clients that actually ran our JS.
        $jsRan = (string)$request->getParam('panth_js') === '1';
        $elapsedMs = (int)$request->getParam('panth_dt');
        if ($jsRan && $elapsedMs > 0 && $elapsedMs < self::MIN_FILL_MS) {
            return true;
        }

        return false;
    }
}
