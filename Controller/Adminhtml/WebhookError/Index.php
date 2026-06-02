<?php

namespace Surebright\Integration\Controller\Adminhtml\WebhookError;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Surebright_Integration::webhook_errors';

    private PageFactory $resultPageFactory;

    public function __construct(
        Action\Context $context,
        PageFactory $resultPageFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Surebright_Integration::webhook_errors');
        $resultPage->getConfig()->getTitle()->prepend(__('SureBright Webhook Errors'));
        return $resultPage;
    }
}
