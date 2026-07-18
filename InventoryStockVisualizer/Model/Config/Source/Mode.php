<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\InventoryStockVisualizer\Model\Config;

/**
 * Delivery-mode options for the stock visualizer.
 */
class Mode implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_ON_DEMAND, 'label' => __('On demand (fetch on click)')],
            ['value' => Config::MODE_INSTANT, 'label' => __('Instant (fetch on page load)')],
        ];
    }
}
