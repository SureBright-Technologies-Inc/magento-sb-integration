<?php
namespace Surebright\Integration\Api;

use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\NoSuchEntityException;

interface WebhookErrorRepositoryInterface
{
    /**
     * Fetch a single webhook error row by ID.
     *
     * @param int $errorId
     * @return \Magento\Framework\Api\SearchResultsInterface  Wraps a single item; items=[] when not found.
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function get($errorId): SearchResultsInterface;

    /**
     * List webhook errors with optional filtering. All filters are AND-combined.
     * Results are ordered by created_at DESC.
     *
     * @param int $page          1-indexed page number (default 1)
     * @param int $limit         Page size, max 200 (default 50)
     * @param string|null $eventType      Exact match on event_type, e.g. "order_create_update"
     * @param string|null $errorType      One of: auth_error | http_error | exception
     * @param string|null $entityId       Exact match on entity_id (product or order id as string)
     * @param int|null    $httpStatusMin  Only include rows with http_status >= this value
     * @param string|null $since          ISO date/datetime; only rows with created_at >= this. Validated; bad input → HTTP 400.
     * @param string|null $until          ISO date/datetime; only rows with created_at <= this. Validated; bad input → HTTP 400.
     * @return \Magento\Framework\Api\SearchResultsInterface
     * @throws \Magento\Framework\Exception\InputException When since/until is not parseable, or errorType is not a recognized value.
     */
    public function getList(
        $page = 1,
        $limit = 50,
        $eventType = null,
        $errorType = null,
        $entityId = null,
        $httpStatusMin = null,
        $since = null,
        $until = null
    ): SearchResultsInterface;
}
