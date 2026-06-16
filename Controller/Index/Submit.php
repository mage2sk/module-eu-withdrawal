<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Step 2 — the consumer confirms. Re-validates the order/email (and token,
 * if present), records the withdrawal, then redirects to the proof page.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Panth\EuWithdrawal\Model\BotGuard;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Psr\Log\LoggerInterface;

class Submit implements HttpPostActionInterface
{
    private const PROOF_KEY = 'panth_euwithdrawal_proof';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly ManagerInterface $messageManager,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly RemoteAddress $remoteAddress,
        private readonly Config $config,
        private readonly WithdrawalService $service,
        private readonly TokenManager $tokenManager,
        private readonly BotGuard $botGuard,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $redirect = $this->redirectFactory->create();
        if (!$this->config->isEnabled()) {
            return $redirect->setPath('noroute');
        }

        $incrementId = trim((string)$this->request->getParam('increment_id'));
        $email = trim((string)$this->request->getParam('email'));
        $name = trim((string)$this->request->getParam('name'));
        $reason = $this->config->askReason() ? trim((string)$this->request->getParam('reason')) : '';
        $token = trim((string)$this->request->getParam('token'));

        if ($this->botGuard->isBot($this->request)) {
            return $redirect->setPath('withdrawal');
        }
        if ($incrementId === '' || $email === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->messageManager->addErrorMessage(__('Please complete all required fields.'));
            return $redirect->setPath('withdrawal');
        }
        if ($token !== '' && !$this->tokenManager->isValid($incrementId, $email, $token)) {
            $this->messageManager->addErrorMessage(
                __('We could not verify your request. Please start again.')
            );
            return $redirect->setPath('withdrawal');
        }

        $order = $this->service->findOrder($incrementId, $email);
        if ($order === null) {
            $this->messageManager->addErrorMessage(
                __('We could not find an order matching those details.')
            );
            return $redirect->setPath('withdrawal');
        }

        try {
            $request = $this->service->submit($order, [
                'name' => $name,
                'email' => $email,
                'reason' => $reason,
                'ip' => (string)$this->remoteAddress->getRemoteAddress(),
                'user_agent' => (string)$this->request->getServer('HTTP_USER_AGENT'),
            ]);
        } catch (AlreadyExistsException $e) {
            $this->messageManager->addNoticeMessage($e->getMessage());
            return $redirect->setPath('withdrawal');
        } catch (LocalizedException $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $redirect->setPath('withdrawal');
        } catch (\Throwable $e) {
            $this->logger->error('[Panth EuWithdrawal] submit failed: ' . $e->getMessage());
            $this->messageManager->addErrorMessage(
                __('Something went wrong while processing your withdrawal. Please try again.')
            );
            return $redirect->setPath('withdrawal');
        }

        $this->dataPersistor->set(self::PROOF_KEY, [
            'proof_reference' => $request->getProofReference(),
            'increment_id' => $request->getIncrementId(),
            'email' => $request->getCustomerEmail(),
        ]);
        return $redirect->setPath('withdrawal/index/success');
    }
}
