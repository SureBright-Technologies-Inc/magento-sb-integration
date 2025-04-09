<?php
namespace Surebright\Integration\Api;

use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Api\SearchResultsInterface;

interface SureBrightLoggerInterface
{
    /**
     * Get logs with pagination support
     * 
     * @param int $page
     * @param int $limit
     * @return \Magento\Framework\Api\SearchResultsInterface
     */
    public function getLogs($page, $limit);
}
