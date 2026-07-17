<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;

/**
 * Resolve product ids for the given SKUs and purge their visualizer fragment.
 *
 * Shared terminal step for both the synchronous dispatch and the queue consumer.
 */
class PurgeBySkus
{
    /**
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param FlushStockVisualizerCache $flushStockVisualizerCache
     */
    public function __construct(
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly FlushStockVisualizerCache $flushStockVisualizerCache
    ) {
    }

    /**
     * @param string[] $skus
     * @return void
     */
    public function execute(array $skus): void
    {
        if (!$skus) {
            return;
        }
        $this->flushStockVisualizerCache->execute($this->resolveProductIds($skus));
    }

    /**
     * Resolve product ids for the given SKUs, skipping any that no longer exist.
     *
     * @param string[] $skus
     * @return int[]
     */
    private function resolveProductIds(array $skus): array
    {
        try {
            return array_map('intval', array_values($this->getProductIdsBySkus->execute($skus)));
        } catch (NoSuchEntityException $e) {
            $ids = [];
            foreach ($skus as $sku) {
                try {
                    $ids[] = (int) ($this->getProductIdsBySkus->execute([$sku])[$sku] ?? 0);
                } catch (NoSuchEntityException $inner) {
                    continue;
                }
            }

            return array_values(array_filter($ids));
        }
    }
}
