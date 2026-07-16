<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Cache;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;

/**
 * Purge the visualizer cache for products whose displayed value changed after reservations were appended.
 */
class PurgeOnReservations
{
    /**
     * @param Config $config
     * @param ResolveDisplayConfig $resolveDisplayConfig
     * @param LevelResolver $levelResolver
     * @param GetStockViewInterface $getStockView
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param FlushStockVisualizerCache $flushStockVisualizerCache
     */
    public function __construct(
        private readonly Config $config,
        private readonly ResolveDisplayConfig $resolveDisplayConfig,
        private readonly LevelResolver $levelResolver,
        private readonly GetStockViewInterface $getStockView,
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly FlushStockVisualizerCache $flushStockVisualizerCache
    ) {
    }

    /**
     * @param ReservationInterface[] $reservations
     * @return void
     */
    public function execute(array $reservations): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        $deltas = $this->groupDeltas($reservations);

        $skusToPurge = [];
        foreach ($deltas as $stockId => $bySku) {
            foreach ($bySku as $sku => $delta) {
                $displayConfig = $this->resolveDisplayConfig->forSku((string) $sku);
                if (!$displayConfig->isLevel()) {
                    $skusToPurge[$sku] = true;
                    continue;
                }
                if ($this->levelChanged((string) $sku, (int) $stockId, $displayConfig, $delta)) {
                    $skusToPurge[$sku] = true;
                }
            }
        }

        if (!$skusToPurge) {
            return;
        }

        $this->flushStockVisualizerCache->execute($this->resolveProductIds(array_keys($skusToPurge)));
    }

    /**
     * Group reservation deltas by stock and SKU, keeping the total and per-source deltas.
     *
     * @param ReservationInterface[] $reservations
     * @return array<int, array<string, array{total: float, bySource: array<string, float>}>>
     */
    private function groupDeltas(array $reservations): array
    {
        $deltas = [];
        foreach ($reservations as $reservation) {
            $quantity = (float) $reservation->getQuantity();
            if ($quantity === 0.0) {
                continue;
            }
            $stockId = (int) $reservation->getStockId();
            $sku = (string) $reservation->getSku();
            $sourceCode = (string) ($reservation->getSourceCode() ?? '');

            if (!isset($deltas[$stockId][$sku])) {
                $deltas[$stockId][$sku] = ['total' => 0.0, 'bySource' => []];
            }
            $deltas[$stockId][$sku]['total'] += $quantity;
            if ($sourceCode !== '') {
                $deltas[$stockId][$sku]['bySource'][$sourceCode] =
                    ($deltas[$stockId][$sku]['bySource'][$sourceCode] ?? 0.0) + $quantity;
            }
        }

        return $deltas;
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
