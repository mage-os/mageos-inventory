<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

/**
 * Map a quantity to a coarse availability level using the effective display config.
 */
class LevelResolver
{
    /**
     * Resolve the availability level for a quantity.
     *
     * @param float $qty
     * @param DisplayConfig $config
     * @param float|null $reference on-hand fallback for the percentage basis
     * @return string one of the Level::* constants
     */
    public function resolve(float $qty, DisplayConfig $config, ?float $reference = null): string
    {
        if ($qty <= 0.0) {
            return Level::OUT;
        }

        if ($config->getLevelBasis() === Config::LEVEL_BASIS_PERCENTAGE) {
            $ref = $config->getFullQty() ?: $reference;
            if ($ref !== null && $ref > 0.0) {
                return $this->byThresholds($qty / $ref * 100.0, $config->getLevelHigh(), $config->getLevelLow());
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
