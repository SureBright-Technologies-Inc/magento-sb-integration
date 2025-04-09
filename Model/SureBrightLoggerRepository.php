<?php
namespace Surebright\Integration\Model;

use Surebright\Integration\Api\SureBrightLoggerInterface;
use Surebright\Integration\Model\ResourceModel\SureBrightLogger\CollectionFactory;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Api\SearchResultsInterface;

class SureBrightLoggerRepository implements SureBrightLoggerInterface
{
    protected $collectionFactory;
    protected $searchCriteriaBuilder;
    protected $searchResultsFactory;

    public function __construct(
        CollectionFactory $collectionFactory,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SearchResultsFactory $searchResultsFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function getLogs($page, $limit): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->setPageSize($limit);
        $collection->setCurPage($page);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setTotalCount($collection->getSize());

        // Convert Collection Items to Array
        $logs = [];
        foreach ($collection as $log) {
            $logs[] = $log->getData(); // Convert model object to array
        }

        $searchResults->setItems($logs);

        return $searchResults;
    }
}
