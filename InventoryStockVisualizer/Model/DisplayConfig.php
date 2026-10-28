<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

/**
 * Effective display configuration for a product, after merging the per-product
 * override over the store-scoped defaults.
 *
 * @api
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
     * Display strategy, one of Config::DISPLAY_TYPE_*.
     *
     * @return string
     */
    public function getDisplayType(): string
    {
        return $this->displayType;
    }

    /**
     * Whether the coarse level (semaphore) strategy is in effect.
     *
     * @return bool
     */
    public function isLevel(): bool
    {
        return $this->displayType === Config::DISPLAY_TYPE_LEVEL;
    }

    /**
     * Level threshold basis, one of Config::LEVEL_BASIS_*.
     *
     * @return string
     */
    public function getLevelBasis(): string
    {
        return $this->levelBasis;
    }

    /**
     * Threshold above which the level is high.
     *
     * @return float
     */
    public function getLevelHigh(): float
    {
        return $this->levelHigh;
    }

    /**
     * Threshold above which the level is medium.
     *
     * @return float
     */
    public function getLevelLow(): float
    {
        return $this->levelLow;
    }

    /**
     * Percentage-basis reference (100% quantity), or null when not configured.
     *
     * @return float|null
     */
    public function getFullQty(): ?float
    {
        return $this->fullQty;
    }
}
