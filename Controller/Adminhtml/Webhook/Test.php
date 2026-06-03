<?php

namespace Surebright\Integration\Controller\Adminhtml\Webhook;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Surebright\Integration\Service\SBEventsDispatchService;

/**
 * AJAX endpoint that runs a live connectivity check against the SureBright
 * webhook endpoint and returns the result as JSON. Backs the "Test webhook"
 * button on the SureBright admin page.
 */
class Test extends Action
{
    public const ADMIN_RESOURCE = 'Surebright_Integration::manage';

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var SBEventsDispatchService */
    private $dispatchService;

    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        SBEventsDispatchService $dispatchService
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->dispatchService = $dispatchService;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        return $result->setData($this->dispatchService->testConnectivity());
    }
}
