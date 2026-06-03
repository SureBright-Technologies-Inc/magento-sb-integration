<?php

namespace Surebright\Integration\Controller\Adminhtml\Integration;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Surebright\Integration\Service\IntegrationUninstaller;

/**
 * AJAX endpoint that disconnects / revokes the SureBright integration and returns
 * a per-step JSON report. Backs the "Uninstall" button on the SureBright admin page.
 *
 * POST only — guarded by the admin form key.
 */
class Uninstall extends Action
{
    public const ADMIN_RESOURCE = 'Surebright_Integration::manage';

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var IntegrationUninstaller */
    private $uninstaller;

    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        IntegrationUninstaller $uninstaller
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->uninstaller = $uninstaller;
    }

    public function execute()
    {
        $result = $this->resultJsonFactory->create();

        if (!$this->getRequest()->isPost()) {
            return $result->setData([
                'success' => false,
                'message' => 'Invalid request method; expected POST.',
            ]);
        }

        return $result->setData($this->uninstaller->uninstall());
    }
}
