<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
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
