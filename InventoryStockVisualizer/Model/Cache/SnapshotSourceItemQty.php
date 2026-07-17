<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;

/**
 * Snapshot the current per-source quantity for the SKUs about to be written.
 *
 * Taken before a source-item save or delete so the after-plugin can compute the exact per-source
 * delta against the freshly written values.
 */
class SnapshotSourceItemQty
{
    /**
     * @param GetSourceItemsBySkuInterface $getSourceItemsBySku
     */
    public function __construct(
        private readonly GetSourceItemsBySkuInterface $getSourceItemsBySku
    ) {
    }

    /**
     * @param SourceItemInterface[] $sourceItems
     * @return array<string, float> old quantity keyed by "sku|source"
     */
    public function execute(array $sourceItems): array
    {
        $skus = [];
        foreach ($sourceItems as $item) {
            $sku = (string) $item->getSku();
            if ($sku !== '') {
                $skus[$sku] = true;
            }
        }

        $snapshot = [];
        foreach (array_keys($skus) as $sku) {
            foreach ($this->getSourceItemsBySku->execute($sku) as $existing) {
                $snapshot[$sku . '|' . (string) $existing->getSourceCode()] = (float) $existing->getQuantity();
            }
        }

        return $snapshot;
    }
}
