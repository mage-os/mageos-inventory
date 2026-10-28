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
 * Display-type options for the stock visualizer.
 */
class DisplayType implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::DISPLAY_TYPE_LEVEL, 'label' => __('Level (semaphore)')],
            ['value' => Config::DISPLAY_TYPE_QUANTITY, 'label' => __('Exact quantity')],
        ];
    }
}
