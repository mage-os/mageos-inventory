<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Api\Data;

/**
 * Availability quantities of a SKU on a stock. The presentation layer derives the
 * displayed level (semaphore) or number from these quantities.
 *
 * @api
 */
interface StockViewInterface
{
    /**
     * Requested SKU.
     *
     * @return string
     */
    public function getSku(): string;

    /**
     * Resolved stock id the view was computed for.
     *
     * @return int
     */
    public function getStockId(): int;

    /**
     * Aggregate salable quantity (on-hand minus reservations minus out-of-stock threshold).
     *
     * @return float
     */
    public function getSalableQty(): float;

    /**
     * Per-source availability rows, empty when the scope is aggregate.
     *
     * @return \Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface[]
     */
    public function getSources(): array;

    /**
     * Set the per-source availability rows.
     *
     * @param \Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface[] $sources
     * @return void
     */
    public function setSources(array $sources): void;

    /**
     * Whether per-source quantities are net of source-level reservations.
     *
     * @return bool
     */
    public function isSourceReservationsEnabled(): bool;

    /**
     * Authoritative salability of the SKU on the stock.
     *
     * For stockable (is_qty) types this mirrors salableQty > 0; for composite types
     * (configurable/grouped/bundle) it is the aggregated index salability, since their
     * salable quantity is undefined at the parent level.
     *
     * @return bool
     */
    public function isSalable(): bool;

    /**
     * Whether the view carries only an aggregate salable/not-salable status.
     *
     * True for composite parents, whose salable quantity and per-source breakdown are
     * not meaningful: the presentation must render the status word only, never a number
     * and never per-source rows.
     *
     * @return bool
     */
    public function isAggregateOnly(): bool;

    /**
     * Per-child availability rows for a composite parent (children display mode).
     *
     * Empty unless the composite type is configured to show a per-component breakdown.
     *
     * @return \Magento\InventoryStockVisualizer\Api\Data\ChildViewInterface[]
     */
    public function getChildren(): array;
}
