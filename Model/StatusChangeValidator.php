<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\Phrase;
use Panth\EuWithdrawal\Model\Source\Status;

class StatusChangeValidator
{
    public function requiresComment(int $previousStatus, int $newStatus): bool
    {
        return $newStatus === Status::REJECTED && $previousStatus !== Status::REJECTED;
    }

    public function validate(int $previousStatus, int $newStatus, string $comment): ?Phrase
    {
        if ($this->requiresComment($previousStatus, $newStatus) && trim($comment) === '') {
            return __('Enter the reason for the rejection before setting the status to Rejected. The reason is included in the status email to the customer.');
        }
        return null;
    }
}
