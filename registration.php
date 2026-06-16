<?php
/**
 * Panth EU Withdrawal Button Module Registration
 *
 * Implements the digital withdrawal function required by Directive (EU) 2023/2673.
 *
 * @category  Panth
 * @package   Panth_EuWithdrawal
 * @author    Panth Infotech
 * @copyright Copyright (c) Panth Infotech
 */
declare(strict_types=1);

use Magento\Framework\Component\ComponentRegistrar;

ComponentRegistrar::register(
    ComponentRegistrar::MODULE,
    'Panth_EuWithdrawal',
    __DIR__
);
