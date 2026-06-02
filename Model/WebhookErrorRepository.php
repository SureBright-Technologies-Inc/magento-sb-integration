<?php
namespace Surebright\Integration\Model;

use Magento\Framework\Api\SearchResultsFactory;
use Magento\Framework\Api\SearchResultsInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Surebright\Integration\Api\WebhookErrorRepositoryInterface;
use Surebright\Integration\Model\ResourceModel\WebhookError\CollectionFactory;
use Surebright\Integration\Model\WebhookError\Source\ErrorType as ErrorTypeSource;

class WebhookErrorRepository implements WebhookErrorRepositoryInterface
{
    public const MAX_PAGE_SIZE = 200;
    public const DEFAULT_PAGE_SIZE = 50;

    private CollectionFactory $collectionFactory;
    private SearchResultsFactory $searchResultsFactory;

    public function __construct(
        CollectionFactory $collectionFactory,
        SearchResultsFactory $searchResultsFactory
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->searchResultsFactory = $searchResultsFactory;
    }

    public function get($errorId): SearchResultsInterface
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('error_id', (int) $errorId);
        $collection->setPageSize(1);

        /** @var \Surebright\Integration\Model\WebhookError $row */
        $row = $collection->getFirstItem();
        if (!$row->getId()) {
            throw new NoSuchEntityException(__('Webhook error with ID "%1" does not exist.', $errorId));
        }

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setItems([$row->getData()]);
        $searchResults->setTotalCount(1);

        return $searchResults;
    }

    public function getList(
        $page = 1,
        $limit = 50,
        $eventType = null,
        $errorType = null,
        $entityId = null,
        $httpStatusMin = null,
        $since = null,
        $until = null
    ): SearchResultsInterface {
        $page  = max(1, (int) $page);
        $limit = (int) $limit;
        if ($limit <= 0) {
            $limit = self::DEFAULT_PAGE_SIZE;
        }
        $limit = min($limit, self::MAX_PAGE_SIZE);

        // Validate user-supplied inputs BEFORE pushing them into SQL filters.
        // Bad date strings would otherwise coerce to '0000-00-00 00:00:00' on
        // some MySQL configs and silently return wrong data. A free-text
        // errorType typo would silently return zero rows with no hint.
        $since = $this->normalizeDateOrThrow($since, 'since');
        $until = $this->normalizeDateOrThrow($until, 'until');
        if ($errorType !== null && $errorType !== ''
            && !in_array($errorType, ErrorTypeSource::ALLOWED_VALUES, true)
        ) {
            throw new InputException(__(
                'Invalid errorType "%1". Allowed values: %2.',
                $errorType,
                implode(', ', ErrorTypeSource::ALLOWED_VALUES)
            ));
        }

        $collection = $this->collectionFactory->create();

        if ($eventType !== null && $eventType !== '') {
            $collection->addFieldToFilter('event_type', (string) $eventType);
        }
        if ($errorType !== null && $errorType !== '') {
            $collection->addFieldToFilter('error_type', (string) $errorType);
        }
        if ($entityId !== null && $entityId !== '') {
            $collection->addFieldToFilter('entity_id', (string) $entityId);
        }
        if ($httpStatusMin !== null) {
            $collection->addFieldToFilter('http_status', ['gteq' => (int) $httpStatusMin]);
        }
        if ($since !== null) {
            $collection->addFieldToFilter('created_at', ['gteq' => $since]);
        }
        if ($until !== null) {
            $collection->addFieldToFilter('created_at', ['lteq' => $until]);
        }

        $collection->setOrder('created_at', 'DESC');
        $collection->setPageSize($limit);
        $collection->setCurPage($page);

        $searchResults = $this->searchResultsFactory->create();
        $searchResults->setTotalCount($collection->getSize());
        $items = [];
        foreach ($collection as $row) {
            $items[] = $row->getData();
        }
        $searchResults->setItems($items);

        return $searchResults;
    }

    /**
     * Accept ISO date ("2026-05-01") or full datetime ("2026-05-01 14:00:00" /
     * "2026-05-01T14:00:00Z"). Anything else throws InputException so the
     * client gets a clear 400 instead of mysteriously wrong rows.
     */
    private function normalizeDateOrThrow(?string $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $ts = strtotime($value);
        if ($ts === false || $ts <= 0) {
            throw new InputException(__(
                'Invalid date for "%1": "%2". Use YYYY-MM-DD or ISO 8601 (e.g. 2026-05-01T14:00:00Z).',
                $field,
                $value
            ));
        }
        return date('Y-m-d H:i:s', $ts);
    }
}
