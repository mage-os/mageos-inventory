<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Api\Data;

/**
 * Availability of one child of a composite product (a configurable variant, a grouped
 * associated product or a bundle selection), shown as a per-component breakdown.
 *
 * @api
 */
interface ChildViewInterface
{
    /**
     * Child SKU.
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * Display label (child product name, falling back to the SKU).
     *
     * @return string
     */
    public function getLabel(): string;

    /**
     * Salable quantity of the child on the stock.
     *
     * @return float
     */
    public function getQty(): float;

    /**
     * Whether the child is salable.
     *
     * @return bool
     */
    public function isSalable(): bool;
}
