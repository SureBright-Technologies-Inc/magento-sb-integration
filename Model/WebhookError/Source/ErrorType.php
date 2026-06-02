<?php

namespace Surebright\Integration\Model\WebhookError\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Surebright\Integration\Model\WebhookError;

class ErrorType implements OptionSourceInterface
{
    /**
     * Allowed enum values for error_type. Used both as a filter source for
     * the admin grid and as a whitelist for the API getList() endpoint so
     * the same values can't drift between UI and API.
     */
    public const ALLOWED_VALUES = [
        WebhookError::ERROR_TYPE_AUTH,
        WebhookError::ERROR_TYPE_HTTP,
        WebhookError::ERROR_TYPE_EXCEPTION,
    ];

    public function toOptionArray(): array
    {
        return [
            ['value' => WebhookError::ERROR_TYPE_AUTH,      'label' => __('Auth Error')],
            ['value' => WebhookError::ERROR_TYPE_HTTP,      'label' => __('HTTP Error')],
            ['value' => WebhookError::ERROR_TYPE_EXCEPTION, 'label' => __('Exception')],
        ];
    }
}
