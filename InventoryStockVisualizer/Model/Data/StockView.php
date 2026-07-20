<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Data;

use Magento\InventoryStockVisualizer\Api\Data\StockViewInterface;

/**
 * @inheritdoc
 */
class StockView implements StockViewInterface
{
    /**
     * @var string
     */
    private $sku;

    /**
     * @var int
     */
    private $stockId;

    /**
     * @var float
     */
    private $salableQty;

    /**
     * @var bool
     */
    private $sourceReservationsEnabled;

    /**
     * @var \Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface[]
     */
    private $sources;

    /**
     * @var bool
     */
    private $salable;

    /**
     * @var bool
     */
    private $aggregateOnly;

    /**
     * @var \Magento\InventoryStockVisualizer\Api\Data\ChildViewInterface[]
     */
    private $children;

    /**
     * @param string $sku
     * @param int $stockId
     * @param float $salableQty
     * @param bool $sourceReservationsEnabled
     * @param \Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface[] $sources
     * @param bool|null $salable
     * @param bool $aggregateOnly
     * @param \Magento\InventoryStockVisualizer\Api\Data\ChildViewInterface[] $children
     */
    public function __construct(
        string $sku,
        int $stockId,
        float $salableQty,
        bool $sourceReservationsEnabled,
        array $sources = [],
        ?bool $salable = null,
        bool $aggregateOnly = false,
        array $children = []
    ) {
        $this->sku = $sku;
        $this->stockId = $stockId;
        $this->salableQty = $salableQty;
        $this->sourceReservationsEnabled = $sourceReservationsEnabled;
        $this->sources = $sources;
        $this->salable = $salable ?? ($salableQty > 0.0);
        $this->aggregateOnly = $aggregateOnly;
        $this->children = $children;
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
    public function getStockId(): int
    {
        return $this->stockId;
    }

    /**
     * @inheritdoc
     */
    public function getSalableQty(): float
    {
        return $this->salableQty;
    }

    /**
     * @inheritdoc
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * @inheritdoc
     */
    public function setSources(array $sources): void
    {
        $this->sources = $sources;
    }

    /**
     * @inheritdoc
     */
    public function isSourceReservationsEnabled(): bool
    {
        return $this->sourceReservationsEnabled;
    }

    /**
     * @inheritdoc
     */
    public function isSalable(): bool
    {
        return $this->salable;
    }

    /**
     * @inheritdoc
     */
    public function isAggregateOnly(): bool
    {
        return $this->aggregateOnly;
    }

    /**
     * @inheritdoc
     */
    public function getChildren(): array
    {
        return $this->children;
    }
}
