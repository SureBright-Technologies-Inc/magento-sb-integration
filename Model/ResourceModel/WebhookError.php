<?php
namespace Surebright\Integration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WebhookError extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('surebrightWebhookErrors', 'error_id');
    }
}
