<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * Build the grouped deltas the decider expects from a set of saved or deleted source items.
 *
 * The new quantity comes from the written items (zero for a delete); the old quantity comes from a
 * snapshot taken before the write. Each source is expanded to every stock it is linked to so the
 * decider can evaluate the level change on the right stock.
 */
class SourceItemDeltaBuilder
{
    /**
     * @param ResolveStockIdsBySourceCodes $resolveStockIdsBySourceCodes
     */
    public function __construct(
        private readonly ResolveStockIdsBySourceCodes $resolveStockIdsBySourceCodes
    ) {
    }

    /**
     * @param SourceItemInterface[] $sourceItems items being written (or removed)
     * @param array<string, float> $snapshot old quantity keyed by "sku|source"
     * @param bool $removed whether the items are being deleted (new quantity is zero)
     * @return array<int, array<string, array{total: float, bySource: array<string, float>}>>
     */
    public function build(array $sourceItems, array $snapshot, bool $removed = false): array
    {
        $bySkuSource = [];
        $sources = [];
        foreach ($sourceItems as $item) {
            $sku = (string) $item->getSku();
            $source = (string) $item->getSourceCode();
            if ($sku === '' || $source === '') {
                continue;
            }
            $new = $removed ? 0.0 : (float) $item->getQuantity();
            $old = $snapshot[$sku . '|' . $source] ?? 0.0;
            $delta = $new - $old;
            if ($delta === 0.0) {
                continue;
            }
            $bySkuSource[$sku][$source] = $delta;
            $sources[$source] = true;
        }
        if (!$bySkuSource) {
            return [];
        }

        $stockIdsBySource = $this->resolveStockIdsBySourceCodes->execute(array_keys($sources));

        $deltas = [];
        foreach ($bySkuSource as $sku => $sourceDeltas) {
            foreach ($sourceDeltas as $source => $delta) {
                foreach ($stockIdsBySource[$source] ?? [] as $stockId) {
                    if (!isset($deltas[$stockId][$sku])) {
                        $deltas[$stockId][$sku] = ['total' => 0.0, 'bySource' => []];
                    }
                    $deltas[$stockId][$sku]['total'] += $delta;
                    $deltas[$stockId][$sku]['bySource'][$source] =
                        ($deltas[$stockId][$sku]['bySource'][$source] ?? 0.0) + $delta;
                }
            }
        }

        return $deltas;
    }
}
