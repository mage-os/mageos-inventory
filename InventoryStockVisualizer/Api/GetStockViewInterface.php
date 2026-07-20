<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Api;

use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;

/**
 * Build the storefront availability view for a SKU on a stock.
 *
 * @api
 */
interface GetStockViewInterface
{
    /**
     * Build the availability view (salable quantity and per-source breakdown) for a SKU on a stock.
     *
     * The optional product type id lets the caller skip a product load. When null it is
     * resolved from the SKU. Composite types (configurable/grouped/bundle) yield an
     * aggregate-only view (status without a quantity or per-source breakdown), since their
     * salable quantity is undefined at the parent level.
     *
     * @param string $sku
     * @param int $stockId
     * @param string|null $typeId
     * @return \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface
     */
    public function execute(string $sku, int $stockId, ?string $typeId = null): StockViewInterface;
}
