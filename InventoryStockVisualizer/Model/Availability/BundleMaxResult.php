<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Availability;

/**
 * Sellable-bundle count for a selection plus the child product ids the count depends on.
 *
 * The product ids let the controller tag the cached fragment so a stock change on any chosen
 * child purges exactly the selections that include it.
 */
class BundleMaxResult
{
    /**
     * @param int|null $max
     * @param int[] $productIds
     */
    public function __construct(
        private readonly ?int $max,
        private readonly array $productIds
    ) {
    }

    /**
     * Maximum sellable bundle count, or null when the selection is not yet complete.
     *
     * @return int|null
     */
    public function getMax(): ?int
    {
        return $this->max;
    }

    /**
     * Child product ids whose stock bounds the count.
     *
     * @return int[]
     */
    public function getProductIds(): array
    {
        return $this->productIds;
    }
}
