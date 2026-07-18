<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;

/**
 * Default availability-quantity provider.
 */
class GetStockView implements GetStockViewInterface
{
    /**
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     * @param GetSourceItemQuantityBySkusAndSources $getSourceItemQuantity
     * @param GetReservationsQuantityBySkusAndSources $getSourceReservations
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param Config $config
     * @param StockViewInterfaceFactory $stockViewFactory
     * @param SourceViewInterfaceFactory $sourceViewFactory
     * @param EventManagerInterface $eventManager
     */
    public function __construct(
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock,
        private readonly GetSourceItemQuantityBySkusAndSources $getSourceItemQuantity,
        private readonly GetReservationsQuantityBySkusAndSources $getSourceReservations,
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly Config $config,
        private readonly StockViewInterfaceFactory $stockViewFactory,
        private readonly SourceViewInterfaceFactory $sourceViewFactory,
        private readonly EventManagerInterface $eventManager
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(string $sku, int $stockId): StockViewInterface
    {
        $slrEnabled = $this->sourceReservationsConfig->isEnabled();

        try {
            $salableQty = (float) $this->getProductSalableQty->execute($sku, $stockId);
        } catch (LocalizedException $e) {
            $salableQty = 0.0;
        }

        $sources = $this->config->getScope() === Config::SCOPE_PER_SOURCE
            ? $this->buildSources($sku, $stockId, $slrEnabled)
            : [];

        /** @var StockViewInterface $view */
        $view = $this->stockViewFactory->create([
            'sku' => $sku,
            'stockId' => $stockId,
            'salableQty' => $salableQty,
            'sourceReservationsEnabled' => $slrEnabled,
            'sources' => $sources,
        ]);

        $this->eventManager->dispatch(
            'inventory_stock_visualizer_view_load_after',
            ['stock_view' => $view, 'sku' => $sku, 'stock_id' => $stockId]
        );

        return $view;
    }

    /**
     * Build the per-source availability rows for the stock (all enabled sources).
     *
     * @param string $sku
     * @param int $stockId
     * @param bool $slrEnabled
     * @return SourceViewInterface[]
     */
    private function buildSources(string $sku, int $stockId, bool $slrEnabled): array
    {
        $enabledSources = [];
        foreach ($this->getSourcesAssignedToStock->execute($stockId) as $source) {
            if ($source->isEnabled()) {
                $enabledSources[(string) $source->getSourceCode()] = $source;
            }
        }
        if (!$enabledSources) {
            return [];
        }

        $sourceCodes = array_keys($enabledSources);
        $physical = $this->getSourceItemQuantity->execute([$sku], $sourceCodes);
        $reservations = $slrEnabled ? $this->getSourceReservations->execute([$sku], $sourceCodes) : [];

        $rows = [];
        foreach ($enabledSources as $sourceCode => $source) {
            $available = ($physical[$sourceCode][$sku] ?? 0.0) + ($reservations[$sourceCode][$sku] ?? 0.0);
            $rows[] = $this->sourceViewFactory->create([
                'sourceCode' => (string) $sourceCode,
                'qty' => max(0.0, $available),
                'name' => $source->getName() ?: (string) $sourceCode,
            ]);
        }

        return $rows;
    }
}
