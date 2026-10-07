<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Panth\EuWithdrawal\Model\Config;

/**
 * Builds a real Config over an in-memory scope config.
 *
 * Keys are paths below "panth_euwithdrawal/". A key suffixed with "@<storeId>"
 * overrides the plain key for that store only.
 */
trait ConfigStubTrait
{
    protected function makeConfig(array $values = [], array $flags = []): Config
    {
        $lookup = static function (array $map, string $path, $storeId) {
            $key = substr($path, strlen('panth_euwithdrawal/'));
            if ($storeId !== null && array_key_exists($key . '@' . $storeId, $map)) {
                return $map[$key . '@' . $storeId];
            }
            return $map[$key] ?? null;
        };
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('getValue')->willReturnCallback(
            static fn(string $path, $scopeType = null, $storeId = null) => $lookup($values, $path, $storeId)
        );
        $scope->method('isSetFlag')->willReturnCallback(
            static fn(string $path, $scopeType = null, $storeId = null) => (bool)$lookup($flags, $path, $storeId)
        );
        return new Config($scope);
    }
}
