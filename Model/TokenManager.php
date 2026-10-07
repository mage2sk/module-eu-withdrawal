<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\App\DeploymentConfig;

class TokenManager
{
    public function __construct(
        private readonly DeploymentConfig $deploymentConfig
    ) {
    }

    public function generate(string $incrementId, string $email): string
    {
        $keys = $this->getKeys();
        if ($keys === []) {
            return '';
        }
        return hash_hmac('sha256', $this->normalize($incrementId, $email), (string)end($keys));
    }

    public function isValid(string $incrementId, string $email, string $token): bool
    {
        if ($token === '') {
            return false;
        }
        $data = $this->normalize($incrementId, $email);
        foreach ($this->getKeys() as $key) {
            if (hash_equals(hash_hmac('sha256', $data, $key), $token)) {
                return true;
            }
        }
        return false;
    }

    private function normalize(string $incrementId, string $email): string
    {
        return trim($incrementId) . '|' . strtolower(trim($email));
    }

    private function getKeys(): array
    {
        $raw = $this->deploymentConfig->get('crypt/key');
        if (is_array($raw)) {
            $raw = implode("\n", array_map('strval', $raw));
        }
        $raw = trim((string)$raw);
        if ($raw === '') {
            return [];
        }

        $keys = [$raw];
        foreach (preg_split('/\s+/', $raw) ?: [] as $key) {
            if ($key !== '') {
                $keys[] = $key;
            }
        }
        return array_values(array_unique($keys));
    }
}
