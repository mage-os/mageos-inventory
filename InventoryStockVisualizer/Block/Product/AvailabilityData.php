<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Block\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\GetEnabledSources;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;

/**
 * Availability collaborators for the storefront panel block.
 *
 * Groups the stock, display-config and level services the block delegates to, so the block
 * itself carries only the presentation glue. Stateless — the block owns per-render memoization.
 */
class AvailabilityData
{
    /**
     * @param GetStockViewInterface $getStockView
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param GetEnabledSources $getEnabledSources
     * @param LevelResolver $levelResolver
     * @param ResolveDisplayConfig $resolveDisplayConfig
     */
    public function __construct(
        private readonly GetStockViewInterface $getStockView,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        private readonly GetEnabledSources $getEnabledSources,
        private readonly LevelResolver $levelResolver,
        private readonly ResolveDisplayConfig $resolveDisplayConfig
    ) {
    }

    /**
     * Stock id for the current website, or null when it cannot be resolved.
     *
     * @return int|null
     */
    public function resolveStockId(): ?int
    {
        try {
            return (int) $this->getStockIdForCurrentWebsite->execute();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Effective display config (per-product override merged over store defaults).
     *
     * @param ProductInterface|null $product
     * @return DisplayConfig
     */
    public function displayConfig(?ProductInterface $product): DisplayConfig
    {
        return $this->resolveDisplayConfig->forProduct($product);
    }

    /**
     * Availability view for the SKU in the stock, typed so composite products resolve by type.
     *
     * @param string $sku
     * @param int $stockId
     * @param string|null $typeId
     * @return StockViewInterface
     */
    public function view(string $sku, int $stockId, ?string $typeId): StockViewInterface
    {
        return $this->getStockView->execute($sku, $stockId, $typeId);
    }

    /**
     * Per-source scaffold rows (labels only) for the stock.
     *
     * @param int $stockId
     * @return array<int, array{code: string, name: string}>
     */
    public function enabledSources(int $stockId): array
    {
        return $this->getEnabledSources->execute($stockId);
    }

    /**
     * Resolve a quantity to its coarse level given the display config.
     *
     * @param float $qty
     * @param DisplayConfig $displayConfig
     * @return string
     */
    public function resolveLevel(float $qty, DisplayConfig $displayConfig): string
    {
        return $this->levelResolver->resolve($qty, $displayConfig);
    }

    /**
     * Availability-bar fill percentage for a level.
     *
     * @param string $level
     * @return int
     */
    public function fillPercent(string $level): int
    {
        return $this->levelResolver->fillPercent($level);
    }
}
