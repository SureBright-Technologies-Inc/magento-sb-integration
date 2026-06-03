<?php

namespace Surebright\Integration\Service;

use Magento\Indexer\Model\Indexer\CollectionFactory;
use Psr\Log\LoggerInterface;

/**
 * Rebuilds Magento's indexers from the SureBright admin panel — the equivalent of
 * `bin/magento indexer:reindex`. Each indexer is reindexed independently and its
 * outcome captured, so one failure does not abort the rest.
 */
class Reindexer
{
    /** @var CollectionFactory */
    private $indexerCollectionFactory;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CollectionFactory $indexerCollectionFactory,
        LoggerInterface $logger
    ) {
        $this->indexerCollectionFactory = $indexerCollectionFactory;
        $this->logger = $logger;
    }

    /**
     * Reindex every indexer, reporting a per-indexer result.
     *
     * @return array{success: bool, message: string, steps: array<int, array{step: string, ok: bool, detail: string}>}
     */
    public function reindexAll(): array
    {
        // A full reindex can exceed the default time limit on large catalogs.
        // phpcs:ignore Magento2.Functions.DiscouragedFunction
        @set_time_limit(0);

        $steps = [];
        $indexers = $this->indexerCollectionFactory->create()->getItems();

        foreach ($indexers as $indexer) {
            $title = $indexer->getTitle() ?: $indexer->getId();
            try {
                $indexer->reindexAll();
                $steps[] = ['step' => $title, 'ok' => true, 'detail' => 'Reindexed.'];
            } catch (\Throwable $e) {
                $this->logger->error('SureBright reindex [' . $title . ']: ' . $e->getMessage());
                $steps[] = ['step' => $title, 'ok' => false, 'detail' => $e->getMessage()];
            }
        }

        if (empty($steps)) {
            return ['success' => false, 'message' => 'No indexers found to reindex.', 'steps' => []];
        }

        $failed = 0;
        foreach ($steps as $step) {
            if (!$step['ok']) {
                $failed++;
            }
        }

        $success = $failed === 0;
        $message = $success
            ? sprintf('Reindexed %d indexer(s) successfully.', count($steps))
            : sprintf('%d of %d indexer(s) failed — see details below.', $failed, count($steps));

        return ['success' => $success, 'message' => $message, 'steps' => $steps];
    }
}
