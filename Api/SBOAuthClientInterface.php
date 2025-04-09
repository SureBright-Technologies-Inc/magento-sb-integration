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
     * @return string
     */
    public function insertUpdateClientAuthDetails(string $sbIntegrationClientUUID, string $consumerKey, string $sbAccessToken, string $sbSvixAccessToken, string $sbSvixAppId, bool $isActive): string;

    /**
     * List all integration auth details
     * @return string
     */
    public function listIntegrationAuthDetails(): string;

    /**
     * Enabel/Disable Surebright Logger
     *
     * @param bool $isActive
     * @return string
     */
    public function toggleLogger($isActive): string;
}