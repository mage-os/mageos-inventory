<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\EntityManager\EventManager;
use Magento\Framework\Indexer\CacheContextFactory;

/**
 * Purge the dedicated stock-visualizer tag for the given product ids.
 *
 * Registers the entities against the dedicated tag and dispatches
 * clean_cache_by_tags so both the built-in full page cache and an external cache
 * (e.g. Varnish) drop the tagged fragment, without touching the product page tag.
 */
class FlushStockVisualizerCache
{
    /**
     * @param CacheContextFactory $cacheContextFactory
     * @param EventManager $eventManager
     * @param CacheInterface $appCache
     */
    public function __construct(
        private readonly CacheContextFactory $cacheContextFactory,
        private readonly EventManager $eventManager,
        private readonly CacheInterface $appCache
    ) {
    }

    /**
     * @param int[] $productIds
     * @return void
     */
    public function execute(array $productIds): void
    {
        if (!$productIds) {
            return;
        }
        $cacheContext = $this->cacheContextFactory->create();
        $cacheContext->registerEntities(CacheTag::CACHE_TAG, $productIds);
        $this->eventManager->dispatch('clean_cache_by_tags', ['object' => $cacheContext]);
        $tags = $cacheContext->getIdentities();
        if ($tags) {
            $this->appCache->clean($tags);
        }
    }
}
