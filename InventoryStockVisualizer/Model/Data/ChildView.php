<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Data;

use Magento\InventoryStockVisualizer\Api\Data\ChildViewInterface;

/**
 * @inheritdoc
 */
class ChildView implements ChildViewInterface
{
    /**
     * @param string $sku
     * @param string $label
     * @param float $qty
     * @param bool $salable
     */
    public function __construct(
        private readonly string $sku,
        private readonly string $label,
        private readonly float $qty,
        private readonly bool $salable
    ) {
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @inheritdoc
     */
    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @inheritdoc
     */
    public function getQty(): float
    {
        return $this->qty;
    }

    /**
     * @inheritdoc
     */
    public function isSalable(): bool
    {
        return $this->salable;
    }
}
