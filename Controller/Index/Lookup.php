<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Index;

use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\PageFactory;
use Panth\EuWithdrawal\Model\BotGuard;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\RateLimiter;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalContext;
use Panth\EuWithdrawal\Model\WithdrawalService;

class Lookup implements HttpPostActionInterface
{
    private const FORM_KEY = 'panth_euwithdrawal_form';

    public function __construct(
        private readonly RequestInterface $request,
        private readonly RedirectFactory $redirectFactory,
        private readonly PageFactory $pageFactory,
        private readonly ManagerInterface $messageManager,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly RemoteAddress $remoteAddress,
        private readonly Config $config,
        private readonly WithdrawalService $service,
        private readonly TokenManager $tokenManager,
        private readonly RateLimiter $rateLimiter,
        private readonly BotGuard $botGuard,
        private readonly WithdrawalContext $context
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
            return $this->fail($redirect, $incrementId, $email, $name, $reason);
        }

        $ip = (string)$this->remoteAddress->getRemoteAddress();
        if ($this->rateLimiter->isLimited($ip, $this->config->getRateLimit())) {
            $this->messageManager->addErrorMessage(
                __('Too many attempts. Please wait a few minutes and try again.')
            );
            return $this->retain($redirect, $incrementId, $email, $name, $reason);
        }

        if ($incrementId === '' || $email === '' || $name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->fail($redirect, $incrementId, $email, $name, $reason);
        }

        if ($this->rateLimiter->isReferenceLocked($incrementId, $this->config->getReferenceAttemptLimit())) {
            $this->messageManager->addErrorMessage(
                __('Too many attempts for this order number. Please try again later or contact us.')
            );
            return $this->retain($redirect, $incrementId, $email, $name, $reason);
        }

        if ($token !== '' && !$this->tokenManager->isValid($incrementId, $email, $token)) {
            $this->rateLimiter->registerReferenceFailure($incrementId);
            return $this->fail($redirect, $incrementId, $email, $name, $reason);
        }

        $order = $this->service->findOrder($incrementId, $email);
        if ($order === null) {
            $this->rateLimiter->registerReferenceFailure($incrementId);
            return $this->fail($redirect, $incrementId, $email, $name, $reason);
        }

        if ($this->service->hasExistingRequest($order)) {
            $existing = $this->service->getExistingRequest($order);
            if ($existing) {
                $this->context->setStatusRequest($existing);
                $statusPage = $this->pageFactory->create();
                $statusPage->addHandle('withdrawal_index_status');
                $statusPage->getConfig()->getTitle()->set(__('Withdrawal status'));
                return $statusPage;
            }
            $this->messageManager->addNoticeMessage(
                __('A withdrawal request for this order has already been received.')
            );
            return $redirect->setPath('withdrawal');
        }
        $ineligible = $this->service->getIneligibilityReason($order);
        if ($ineligible !== null) {
            $this->messageManager->addErrorMessage($ineligible);
            return $redirect->setPath('withdrawal');
        }

        $this->context->set($order, $name, $email, $token, $reason);
        $page = $this->pageFactory->create();
        $page->addHandle('withdrawal_index_confirm');
        $page->getConfig()->getTitle()->set(__('Confirm your withdrawal'));
        return $page;
    }

    private function fail($redirect, string $incrementId, string $email, string $name, string $reason)
    {
        $this->messageManager->addErrorMessage(
            __('We could not find an order matching those details. Please check your order number and email address.')
        );
        return $this->retain($redirect, $incrementId, $email, $name, $reason);
    }

    private function retain($redirect, string $incrementId, string $email, string $name, string $reason)
    {
        $this->dataPersistor->set(self::FORM_KEY, [
            'increment_id' => $incrementId,
            'email' => $email,
            'name' => $name,
            'reason' => $reason,
        ]);
        return $redirect->setPath('withdrawal');
    }
}
