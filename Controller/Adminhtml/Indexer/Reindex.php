<?php

namespace Surebright\Integration\Controller\Adminhtml\Indexer;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Surebright\Integration\Service\Reindexer;

/**
 * AJAX endpoint that reindexes all Magento indexers and returns a per-indexer
 * JSON report. Backs the "Reindex" button on the SureBright admin page. POST only.
 */
class Reindex extends Action
{
    public const ADMIN_RESOURCE = 'Surebright_Integration::manage';

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var Reindexer */
    private $reindexer;

    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        Reindexer $reindexer
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->reindexer = $reindexer;
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

        return $result->setData($this->reindexer->reindexAll());
    }
}
