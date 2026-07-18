<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Product;

/**
 * Product attribute codes for the per-product stock-visualizer override.
 */
class StockVisualizerAttributes
{
    public const DISPLAY_TYPE = 'stockviz_display_type';
    public const LEVEL_BASIS = 'stockviz_level_basis';
    public const LEVEL_HIGH = 'stockviz_level_high';
    public const LEVEL_LOW = 'stockviz_level_low';
    public const FULL_QTY = 'stockviz_full_qty';
}
