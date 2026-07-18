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
     * @param string $sku
     * @param int $stockId
     * @return \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface
     */
    public function execute(string $sku, int $stockId): StockViewInterface;
}
