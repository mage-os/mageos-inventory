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
use Magento\InventoryConfigurationApi\Model\IsSourceItemManagementAllowedForProductTypeInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;
use Magento\InventoryStockVisualizer\Api\Data\StockViewInterfaceFactory;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Availability\CompositeViewBuilder;
use Magento\InventoryStockVisualizer\Model\Availability\SourceViewBuilder;

/**
 * Default availability-quantity provider.
 *
 * Routes by product type: stockable products resolve their exact salable quantity (and, when
 * per-source scope is on, the per-source breakdown); composite products delegate to the
 * composite view builder for a per-child or aggregate-status view.
 */
class GetStockView implements GetStockViewInterface
{
    /**
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param SourceReservationsConfig $sourceReservationsConfig
     * @param Config $config
     * @param StockViewInterfaceFactory $stockViewFactory
     * @param EventManagerInterface $eventManager
     * @param IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed
     * @param ProductRepositoryInterface $productRepository
     * @param SourceViewBuilder $sourceViewBuilder
     * @param CompositeViewBuilder $compositeViewBuilder
     */
    public function __construct(
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly SourceReservationsConfig $sourceReservationsConfig,
        private readonly Config $config,
        private readonly StockViewInterfaceFactory $stockViewFactory,
        private readonly EventManagerInterface $eventManager,
        private readonly IsSourceItemManagementAllowedForProductTypeInterface $isSourceItemManagementAllowed,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly SourceViewBuilder $sourceViewBuilder,
        private readonly CompositeViewBuilder $compositeViewBuilder
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
            return $this->compositeViewBuilder->build($sku, $stockId, $typeId, $slrEnabled);
        }

        try {
            $salableQty = (float) $this->getProductSalableQty->execute($sku, $stockId);
        } catch (LocalizedException $e) {
            $salableQty = 0.0;
        }

        $sources = $this->config->getScope() === Config::SCOPE_PER_SOURCE
            ? $this->sourceViewBuilder->build($sku, $stockId, $slrEnabled)
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
}
