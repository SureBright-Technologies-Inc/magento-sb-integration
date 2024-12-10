<?php

namespace Surebright\Integration\Api;

use Surebright\Integration\Api\ApiResponse;
use Magento\Framework\Exception\NoSuchEntityException;

interface SBOAuthClientInterface
{
    /**
     * Add or update Surebright OAuth Client
     *
     * @param string $sbIntegrationClientUUID
     * @param string $consumerKey
     * @param string $sbAccessToken
     * @param string $sbSvixAccessToken
     * @param string $sbSvixAppId
     * @param bool $isActive
     * @return ApiResponse
     */
    public function insertUpdateClientAuthDetails(string $sbIntegrationClientUUID, string $consumerKey, string $sbAccessToken, string $sbSvixAccessToken, string $sbSvixAppId, bool $isActive): ApiResponse;
}