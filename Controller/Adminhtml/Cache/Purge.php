<?php

namespace Surebright\Integration\Controller\Adminhtml\Cache;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Surebright\Integration\Service\CachePurger;

/**
 * AJAX endpoint that cleans the SureBright-relevant Magento cache types and
 * returns a per-type JSON report. Backs the "Purge caches" button on the
 * SureBright admin page. POST only.
 */
class Purge extends Action
{
    public const ADMIN_RESOURCE = 'Surebright_Integration::manage';

    /** @var JsonFactory */
    private $resultJsonFactory;

    /** @var CachePurger */
    private $cachePurger;

    public function __construct(
        Action\Context $context,
        JsonFactory $resultJsonFactory,
        CachePurger $cachePurger
    ) {
        parent::__construct($context);
        $this->resultJsonFactory = $resultJsonFactory;
        $this->cachePurger = $cachePurger;
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

        return $result->setData($this->cachePurger->purge());
    }
}
