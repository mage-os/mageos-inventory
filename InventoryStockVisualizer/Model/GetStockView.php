<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventorySalesApi\Model\GetStockItemDataInterface;
use Magento\InventoryStockVisualizer\Api\Data\ChildViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface;
use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Availability\GetCompositeChildren;

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
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed
     * @param GetStockItemDataInterface $getStockItemData
     * @param ProductRepositoryInterface $productRepository
     * @param GetCompositeChildren $getCompositeChildren
     * @param ChildViewInterfaceFactory $childViewFactory
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
        private readonly EventManagerInterface $eventManager,
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed,
        private readonly GetStockItemDataInterface $getStockItemData,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly GetCompositeChildren $getCompositeChildren,
        private readonly ChildViewInterfaceFactory $childViewFactory
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(string $sku, int $stockId, ?string $typeId = null): StockViewInterface
    {
        $slrEnabled = $this->sourceReservationsConfig->isEnabled();
        $typeId = $this->resolveTypeId($sku, $typeId);

        if ($typeId !== null && !$this->isSourceItemManagementAllowed->execute($typeId)) {
            if ($this->getCompositeMode($typeId) === Config::COMPOSITE_MODE_CHILDREN) {
                $view = $this->buildChildrenView($sku, $stockId, $slrEnabled);
                if ($view !== null) {
                    return $view;
                }
            }

            return $this->buildAggregateView($sku, $stockId, $slrEnabled);
        }

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
     * Resolve the product type id, loading the product only when the caller did not pass it.
     *
     * The storefront block passes the type id from the current product to skip a load; on
     * the AJAX/API path it is null and resolved from the SKU. An unresolvable SKU yields null,
     * which routes to the stockable path (degrades to qty 0).
     *
     * @param string $sku
     * @param string|null $typeId
     * @return string|null
     */
    private function resolveTypeId(string $sku, ?string $typeId): ?string
    {
        if ($typeId !== null) {
            return $typeId;
        }

        try {
            return $this->productRepository->get($sku)->getTypeId();
        } catch (LocalizedException $e) {
            return null;
        }
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

        /** @var StockViewInterface $view */
        $view = $this->stockViewFactory->create([
            'sku' => $sku,
            'stockId' => $stockId,
            'salableQty' => 0.0,
            'sourceReservationsEnabled' => $slrEnabled,
            'sources' => [],
            'salable' => $anySalable,
            'aggregateOnly' => true,
            'children' => $children,
        ]);

        $this->eventManager->dispatch(
            'inventory_stock_visualizer_view_load_after',
            ['stock_view' => $view, 'sku' => $sku, 'stock_id' => $stockId]
        );

        return $view;
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

        /** @var StockViewInterface $view */
        $view = $this->stockViewFactory->create([
            'sku' => $sku,
            'stockId' => $stockId,
            'salableQty' => 0.0,
            'sourceReservationsEnabled' => $slrEnabled,
            'sources' => [],
            'salable' => $salable,
            'aggregateOnly' => true,
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
