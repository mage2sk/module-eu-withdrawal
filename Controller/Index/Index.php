<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\EuWithdrawal\Model\Config;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly ForwardFactory $forwardFactory,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('%1', $this->config->getButtonLabel()));
        return $page;
    }
}
