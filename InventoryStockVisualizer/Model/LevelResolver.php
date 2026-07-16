<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

/**
 * Map a quantity to a coarse availability level using the effective display config.
 *
 * @api
 */
class LevelResolver
{
    /**
     * Resolve the availability level for a quantity.
     *
     * Percentage basis uses the per-product full quantity as the 100% reference; when it
     * is not configured the resolver degrades to raw-quantity thresholds.
     *
     * @param float $qty
     * @param DisplayConfig $config
     * @return string one of the Level::* constants
     */
    public function resolve(float $qty, DisplayConfig $config): string
    {
        if ($qty <= 0.0) {
            return Level::OUT;
        }

        if ($config->getLevelBasis() === Config::LEVEL_BASIS_PERCENTAGE) {
            $reference = $config->getFullQty();
            if ($reference !== null && $reference > 0.0) {
                return $this->byThresholds($qty / $reference * 100.0, $config->getLevelHigh(), $config->getLevelLow());
            }
        }

        return $this->byThresholds($qty, $config->getLevelHigh(), $config->getLevelLow());
    }

    /**
     * Meter fill percentage for a coarse level, used to size the availability bar.
     *
     * @param string $level one of the Level::* constants
     * @return int
     */
    public function fillPercent(string $level): int
    {
        switch ($level) {
            case Level::HIGH:
                return 100;
            case Level::MEDIUM:
                return 60;
            case Level::LOW:
                return 30;
            default:
                return 0;
        }
    }

    /**
     * Classify a value against the high/low thresholds into a level.
     *
     * @param float $value
     * @param float $high
     * @param float $low
     * @return string
     */
    private function byThresholds(float $value, float $high, float $low): string
    {
        if ($value > $high) {
            return Level::HIGH;
        }
        if ($value > $low) {
            return Level::MEDIUM;
        }

        return Level::LOW;
    }
}
