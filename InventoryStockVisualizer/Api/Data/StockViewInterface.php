<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
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
}
