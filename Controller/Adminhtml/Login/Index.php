<?php

namespace Surebright\Integration\Controller\Adminhtml\Login;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected $resultPageFactory;

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
        $resultPage->setActiveMenu('Surebright_Integration::merchant_login');
        $resultPage->getConfig()->getTitle()->prepend(__('SureBright Product Protection'));
        return $resultPage;
    }
}
