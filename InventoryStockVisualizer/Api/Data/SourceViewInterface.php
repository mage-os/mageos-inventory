<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Api\Data;

/**
 * Availability of a SKU at a single source.
 *
 * @api
 */
interface SourceViewInterface
{
    /**
     * Source code.
     *
     * @return string
     */
    public function getSourceCode(): string;

    /**
     * Human-readable source name, when exposed.
     *
     * @return string|null
     */
    public function getName(): ?string;

    /**
     * Set the human-readable source name.
     *
     * @param string|null $name
     * @return void
     */
    public function setName(?string $name): void;

    /**
     * Available quantity at the source (net of source-level reservations when enabled).
     *
     * @return float
     */
    public function getQty(): float;
}
