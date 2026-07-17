<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;

/**
 * Decide which SKUs actually need a fragment purge given the quantity deltas that just occurred.
 *
 * Shared by the demand path (reservations) and the supply path (source-item writes) so both
 * apply the same granularity: quantity display purges every touched SKU, while level display
 * purges only when the appended deltas moved the aggregate or a per-source level across a
 * threshold. The aggregate check reads the current salable quantity, which is index-based and
 * therefore best-effort under scheduled indexing; the per-source check is exact.
 */
class ResolveSkusToPurge
{
    /**
     * @param Config $config
     * @param ResolveDisplayConfig $resolveDisplayConfig
     * @param LevelResolver $levelResolver
     * @param GetStockViewInterface $getStockView
     */
    public function __construct(
        private readonly Config $config,
        private readonly ResolveDisplayConfig $resolveDisplayConfig,
        private readonly LevelResolver $levelResolver,
        private readonly GetStockViewInterface $getStockView
    ) {
    }

    /**
     * Reduce the grouped deltas to the distinct SKUs whose displayed value changed.
     *
     * @param array<int, array<string, array{total: float, bySource: array<string, float>}>> $deltas
     * @return string[]
     */
    public function execute(array $deltas): array
    {
        if (!$this->config->isEnabled()) {
            return [];
        }

        $skus = [];
        foreach ($deltas as $stockId => $bySku) {
            foreach ($bySku as $sku => $delta) {
                $sku = (string) $sku;
                $displayConfig = $this->resolveDisplayConfig->forSku($sku);
                if (!$displayConfig->isLevel()) {
                    $skus[$sku] = true;
                    continue;
                }
                if ($this->levelChanged($sku, (int) $stockId, $displayConfig, $delta)) {
                    $skus[$sku] = true;
                }
            }
        }

        return array_keys($skus);
    }

    /**
     * Whether the appended deltas moved the aggregate or any per-source level.
     *
     * @param string $sku
     * @param int $stockId
     * @param DisplayConfig $displayConfig
     * @param array{total: float, bySource: array<string, float>} $delta
     * @return bool
     */
    private function levelChanged(string $sku, int $stockId, DisplayConfig $displayConfig, array $delta): bool
    {
        try {
            $view = $this->getStockView->execute($sku, $stockId);
        } catch (\Throwable $e) {
            return false;
        }

        $afterAgg = $view->getSalableQty();
        $beforeAgg = $afterAgg - $delta['total'];
        if ($this->levelResolver->resolve($beforeAgg, $displayConfig)
            !== $this->levelResolver->resolve($afterAgg, $displayConfig)
        ) {
            return true;
        }

        foreach ($view->getSources() as $source) {
            $sourceDelta = $delta['bySource'][$source->getSourceCode()] ?? 0.0;
            if ($sourceDelta === 0.0) {
                continue;
            }
            $afterSource = $source->getQty();
            $beforeSource = $afterSource - $sourceDelta;
            if ($this->levelResolver->resolve($beforeSource, $displayConfig)
                !== $this->levelResolver->resolve($afterSource, $displayConfig)
            ) {
                return true;
            }
        }

        return false;
    }
}
