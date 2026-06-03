<?php

namespace Surebright\Integration\Controller\Adminhtml\Access;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Surebright\Integration\Service\AccessTester;

/**
 * AJAX endpoint that runs a representative read test for a single access group
 * and returns the result as JSON. Backs the "Run test" buttons on the SureBright
 * admin page.
 */
class Test extends Action
{
    public const ADMIN_RESOURCE = 'Surebright_Integration::manage';

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var AccessTester */
    private $accessTester;

    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        AccessTester $accessTester
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->accessTester = $accessTester;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();
        $group = (string)$this->getRequest()->getParam('group', '');

        if ($group === '') {
            return $result->setData(['success' => false, 'message' => 'No access group specified.']);
        }

        return $result->setData($this->accessTester->test($group));
    }
}
