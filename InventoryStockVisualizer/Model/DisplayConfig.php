<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

/**
 * Effective display configuration for a product, after merging the per-product
 * override over the store-scoped defaults.
 */
class DisplayConfig
{
    /**
     * @param string $displayType Config::DISPLAY_TYPE_*
     * @param string $levelBasis Config::LEVEL_BASIS_*
     * @param float $levelHigh threshold above which the level is high
     * @param float $levelLow threshold above which the level is medium
     * @param float|null $fullQty percentage reference (100% quantity); null falls back to on-hand
     */
    public function __construct(
        private readonly string $displayType,
        private readonly string $levelBasis,
        private readonly float $levelHigh,
        private readonly float $levelLow,
        private readonly ?float $fullQty
    ) {
    }

    /**
     * @return string
     */
    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    /**
     * @return bool
     */
    public function isLevel(): bool
    {
        return $this->displayType === Config::DISPLAY_TYPE_LEVEL;
    }

    /**
     * @return string
     */
    public function getLevelBasis(): string
    {
        return $this->levelBasis;
    }

    /**
     * @return float
     */
    public function getLevelHigh(): float
    {
        return $this->levelHigh;
    }

    /**
     * @return float
     */
    public function getLevelLow(): float
    {
        return $this->levelLow;
    }

    /**
     * @return float|null
     */
    public function getFullQty(): ?float
    {
        return $this->fullQty;
    }
}
