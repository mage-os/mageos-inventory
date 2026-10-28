<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Availability;

use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventoryStockVisualizer\Api\Data\ChildViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Model\Config;

/**
 * Build the availability view for a composite product (configurable / bundle / grouped).
 *
 * A composite type does not manage its own stock, so the view is either a per-child breakdown
 * (children mode) or a single aggregate salable status read from the index. No exact parent
 * quantity is produced.
 */
class CompositeViewBuilder
{
    /**
     * @param GetCompositeChildren $getCompositeChildren
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param GetStockItemDataInterface $getStockItemData
     * @param StockViewInterfaceFactory $stockViewFactory
     * @param ChildViewInterfaceFactory $childViewFactory
     * @param EventManagerInterface $eventManager
     * @param Config $config
     */
    public function __construct(
        private readonly GetCompositeChildren $getCompositeChildren,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly GetStockItemDataInterface $getStockItemData,
        private readonly StockViewInterfaceFactory $stockViewFactory,
        private readonly ChildViewInterfaceFactory $childViewFactory,
        private readonly EventManagerInterface $eventManager,
        private readonly Config $config
    ) {
    }

    /**
     * Availability view for a composite parent, by the configured mode.
     *
     * A per-child breakdown when children mode is configured and available, otherwise the
     * aggregate salable status.
     *
     * @param string $sku
     * @param int $stockId
     * @param string $typeId
     * @param bool $slrEnabled
     * @return StockViewInterface
     */
    public function build(string $sku, int $stockId, string $typeId, bool $slrEnabled): StockViewInterface
    {
        if ($this->getCompositeMode($typeId) === Config::COMPOSITE_MODE_CHILDREN) {
            $view = $this->buildChildrenView($sku, $stockId, $slrEnabled);
            if ($view !== null) {
                return $view;
            }
        }

        return $this->buildAggregateView($sku, $stockId, $slrEnabled);
    }

    /**
     * Configured composite display mode for the product type.
     *
     * @param string $typeId
     * @return string
     */
    private function getCompositeMode(string $typeId): string
    {
        switch ($typeId) {
            case 'configurable':
                return $this->config->getConfigurableMode();
            case 'bundle':
                return $this->config->getBundleMode();
            case 'grouped':
                return $this->config->getGroupedMode();
            default:
                return Config::COMPOSITE_MODE_STATUS;
        }
    }

    /**
     * Build a per-child availability view for a composite parent, or null when it has no children.
     *
     * Each child is an ordinary stockable SKU, so its salable quantity comes from the usual
     * quantity API; the parent status reflects whether any child is salable.
     *
     * @param string $sku
     * @param int $stockId
     * @param bool $slrEnabled
     * @return StockViewInterface|null
     */
    private function buildChildrenView(string $sku, int $stockId, bool $slrEnabled): ?StockViewInterface
    {
        $rows = $this->getCompositeChildren->execute($sku);
        if (!$rows) {
            return null;
        }

        $children = [];
        $anySalable = false;
        foreach ($rows as $row) {
            try {
                $qty = (float) $this->getProductSalableQty->execute($row['sku'], $stockId);
            } catch (LocalizedException $e) {
                $qty = 0.0;
            }
            $salable = $qty > 0.0;
            $anySalable = $anySalable || $salable;
            $children[] = $this->childViewFactory->create([
                'sku' => $row['sku'],
                'label' => $row['label'],
                'qty' => $qty,
                'salable' => $salable,
            ]);
        }

        return $this->createView($sku, $stockId, $slrEnabled, $anySalable, $children);
    }

    /**
     * Build an aggregate-only view (salable status from the index, no quantity or sources).
     *
     * @param string $sku
     * @param int $stockId
     * @param bool $slrEnabled
     * @return StockViewInterface
     */
    private function buildAggregateView(string $sku, int $stockId, bool $slrEnabled): StockViewInterface
    {
        $data = $this->getStockItemData->execute($sku, $stockId);
        $salable = $data !== null && (bool) ($data[GetStockItemDataInterface::IS_SALABLE] ?? false);

        return $this->createView($sku, $stockId, $slrEnabled, $salable, []);
    }

    /**
     * Assemble an aggregate-only stock view and announce it for extension.
     *
     * @param string $sku
     * @param int $stockId
     * @param bool $slrEnabled
     * @param bool $salable
     * @param array<int,\Magento\InventoryStockVisualizer\Api\Data\ChildViewInterface> $children
     * @return StockViewInterface
     */
    private function createView(
        string $sku,
        int $stockId,
        bool $slrEnabled,
        bool $salable,
        array $children
    ): StockViewInterface {
        /** @var StockViewInterface $view */
        $view = $this->stockViewFactory->create([
            'sku' => $sku,
            'stockId' => $stockId,
            'salableQty' => 0.0,
            'sourceReservationsEnabled' => $slrEnabled,
            'sources' => [],
            'salable' => $salable,
            'aggregateOnly' => true,
            'children' => $children,
        ]);

        $this->eventManager->dispatch(
            'inventory_stock_visualizer_view_load_after',
            ['stock_view' => $view, 'sku' => $sku, 'stock_id' => $stockId]
        );

        return $view;
    }
}
