<?php
namespace Surebright\Integration\Model\ResourceModel\WebhookError;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Surebright\Integration\Model\WebhookError;
use Surebright\Integration\Model\ResourceModel\WebhookError as WebhookErrorResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'error_id';

    protected function _construct()
    {
        $this->_init(WebhookError::class, WebhookErrorResource::class);
    }
}
