<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Indexer\IndexerRegistry;
use Magento\Framework\MessageQueue\PublisherInterface;
use Magento\InventoryIndexer\Indexer\InventoryIndexer;
use Magento\InventoryStockVisualizer\Model\Config;

/**
 * Route a purge to the synchronous flush or the coalescing queue, following the configured strategy.
 *
 * Under on-save indexing the fragment must refresh immediately, so the flush runs inline. Under
 * scheduled indexing the site already runs background workers, so the purge is offloaded to a queue
 * and coalesced: a short-lived guard collapses a burst of writes for the same SKU into a single
 * queued message, and the consumer clears the guard when it runs to reopen the window. The last
 * message always wins because the consumer recomputes availability from the live state at run time.
 */
class DispatchPurge
{
    public const TOPIC = 'inventory.stockvisualizer.purge';

    public const GUARD_PREFIX = 'stockviz_purge_pending_';

    /**
     * Upper bound on how long a coalescing guard survives without a consumer clearing it, so a
     * stalled worker can never suppress purges indefinitely.
     */
    private const GUARD_TTL = 60;

    /**
     * @param Config $config
     * @param IndexerRegistry $indexerRegistry
     * @param PublisherInterface $publisher
     * @param CacheInterface $cache
     * @param PurgeBySkus $purgeBySkus
     */
    public function __construct(
        private readonly Config $config,
        private readonly IndexerRegistry $indexerRegistry,
        private readonly PublisherInterface $publisher,
        private readonly CacheInterface $cache,
        private readonly PurgeBySkus $purgeBySkus
    ) {
    }

    /**
     * @param string[] $skus
     * @return void
     */
    public function execute(array $skus): void
    {
        $skus = array_map('strval', $skus);
        $skus = array_values(array_unique(array_filter($skus, static fn (string $sku): bool => $sku !== '')));
        if (!$skus) {
            return;
        }

        if (!$this->isAsync()) {
            $this->purgeBySkus->execute($skus);
            return;
        }

        foreach ($skus as $sku) {
            $this->enqueue($sku);
        }
    }

    /**
     * Whether purges should be offloaded to the queue rather than flushed inline.
     *
     * @return bool
     */
    private function isAsync(): bool
    {
        $mode = $this->config->getAsyncPurge();
        if ($mode === Config::ASYNC_PURGE_ON) {
            return true;
        }
        if ($mode === Config::ASYNC_PURGE_OFF) {
            return false;
        }

        try {
            return $this->indexerRegistry->get(InventoryIndexer::INDEXER_ID)->isScheduled();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Publish a purge message for the SKU unless one is already pending (coalescing).
     *
     * @param string $sku
     * @return void
     */
    private function enqueue(string $sku): void
    {
        $key = self::GUARD_PREFIX . $sku;
        if ($this->cache->load($key)) {
            return;
        }
        $this->publisher->publish(self::TOPIC, $sku);
        $this->cache->save('1', $key, [CacheTag::CACHE_TAG], self::GUARD_TTL);
    }
}
