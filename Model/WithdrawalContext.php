<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Request-scoped holder that carries the validated order context from the
 * lookup controller to the confirmation block (rendered in the same request),
 * so neither registry nor session global state is needed.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Sales\Api\Data\OrderInterface;
use Panth\EuWithdrawal\Model\Request;

class WithdrawalContext
{
    private ?OrderInterface $order = null;
    private string $name = '';
    private string $email = '';
    private string $reason = '';
    private string $token = '';
    private string $proofReference = '';
    private string $proofIncrementId = '';
    private string $proofEmail = '';
    private ?Request $statusRequest = null;

    public function set(OrderInterface $order, string $name, string $email, string $token = '', string $reason = ''): void
    {
        $this->order = $order;
        $this->name = $name;
        $this->email = $email;
        $this->token = $token;
        $this->reason = $reason;
    }

    public function getOrder(): ?OrderInterface
    {
        return $this->order;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setProof(string $proofReference, string $incrementId, string $email): void
    {
        $this->proofReference = $proofReference;
        $this->proofIncrementId = $incrementId;
        $this->proofEmail = $email;
    }

    public function getProofReference(): string
    {
        return $this->proofReference;
    }

    public function getProofIncrementId(): string
    {
        return $this->proofIncrementId;
    }

    public function getProofEmail(): string
    {
        return $this->proofEmail;
    }

    public function setStatusRequest(Request $request): void
    {
        $this->statusRequest = $request;
    }

    public function getStatusRequest(): ?Request
    {
        return $this->statusRequest;
    }
}
