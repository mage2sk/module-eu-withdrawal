<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Status implements OptionSourceInterface
{
    public const RECEIVED = 1;
    public const ACKNOWLEDGED = 2;
    public const REFUNDED = 3;
    public const REJECTED = 4;

    /**
     * @return array<int, string>
     */
    public static function getLabels(): array
    {
        return [
            self::RECEIVED => 'Received',
            self::ACKNOWLEDGED => 'Acknowledged',
            self::REFUNDED => 'Refunded',
            self::REJECTED => 'Rejected',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach (self::getLabels() as $value => $label) {
            $options[] = ['value' => (string)$value, 'label' => __($label)];
        }
        return $options;
    }
}
