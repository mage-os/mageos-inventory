<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
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
     * @param string $sku
     * @param int $stockId
     * @param float $salableQty
     * @param bool $sourceReservationsEnabled
     * @param array $sources
     */
    public function __construct(
        string $sku,
        int $stockId,
        float $salableQty,
        bool $sourceReservationsEnabled,
        array $sources = []
    ) {
        $this->sku = $sku;
        $this->stockId = $stockId;
        $this->salableQty = $salableQty;
        $this->sourceReservationsEnabled = $sourceReservationsEnabled;
        $this->sources = $sources;
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
}
