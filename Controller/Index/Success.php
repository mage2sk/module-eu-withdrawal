<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Proof page shown after a confirmed withdrawal. Reads the one-shot proof
 * reference from the data persistor (cleared on read) so a refresh or a
 * direct visit cannot replay it.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\WithdrawalContext;

class Success implements HttpGetActionInterface
{
    private const PROOF_KEY = 'panth_euwithdrawal_proof';

    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly DataPersistorInterface $dataPersistor,
        private readonly Config $config,
        private readonly WithdrawalContext $context
    ) {
    }

    public function execute()
    {
        $proof = $this->dataPersistor->get(self::PROOF_KEY);
        $this->dataPersistor->clear(self::PROOF_KEY);

        if (!$this->config->isEnabled() || !is_array($proof) || empty($proof['proof_reference'])) {
            return $this->redirectFactory->create()->setPath('withdrawal');
        }

        // Hand the proof data to the success block via the request-scoped context.
        $this->context->setProof(
            (string)$proof['proof_reference'],
            (string)($proof['increment_id'] ?? ''),
            (string)($proof['email'] ?? '')
        );

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('Withdrawal received'));
        return $page;
    }
}
