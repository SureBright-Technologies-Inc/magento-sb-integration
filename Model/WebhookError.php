<?php
namespace Surebright\Integration\Model;

use Magento\Framework\Model\AbstractModel;
use Surebright\Integration\Model\ResourceModel\WebhookError as WebhookErrorResource;

class WebhookError extends AbstractModel
{
    public const ERROR_TYPE_AUTH = 'auth_error';
    public const ERROR_TYPE_HTTP = 'http_error';
    public const ERROR_TYPE_EXCEPTION = 'exception';

    protected function _construct()
    {
        $this->_init(WebhookErrorResource::class);
    }
}
