<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Cron;

use Panth\EuWithdrawal\Service\InstallReporter;

class SendHeartbeat
{
    public function __construct(
        private readonly InstallReporter $reporter
    ) {
    }

    public function execute(): void
    {
        $this->reporter->reportHeartbeat();
    }
}
