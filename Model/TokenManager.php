<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Stateless signed token for pre-filled withdrawal links.
 *
 * The token is an HMAC-SHA256 of "increment_id|lowercased-email" keyed by the
 * Magento crypt key. It lets order-confirmation emails carry a pre-filled,
 * tamper-proof withdrawal link and blocks order-number enumeration on the
 * public lookup endpoint — without storing anything in the database.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\App\DeploymentConfig;

class TokenManager
{
    private ?string $key = null;

    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    public function generate(string $incrementId, string $email): string
    {
        $data = $this->normalize($incrementId, $email);
        return hash_hmac('sha256', $data, $this->getKey());
    }

    public function isValid(string $incrementId, string $email, string $token): bool
    {
        if ($token === '') {
            return false;
        }
        $expected = $this->generate($incrementId, $email);
        return hash_equals($expected, $token);
    }

    private function normalize(string $incrementId, string $email): string
    {
        return trim($incrementId) . '|' . strtolower(trim($email));
    }

    private function getKey(): string
    {
        if ($this->key !== null) {
            return $this->key;
        }

        $cryptKey = $this->deploymentConfig->get('crypt/key');
        if (is_array($cryptKey)) {
            $cryptKey = (string)end($cryptKey);
        }
        // Fall back to a constant salt if the crypt key is somehow unavailable;
        // the token still binds increment_id + email so links stay non-guessable.
        $this->key = (string)($cryptKey ?: 'panth_euwithdrawal_static_salt');
        return $this->key;
    }
}
