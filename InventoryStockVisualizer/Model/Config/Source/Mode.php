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
 * Delivery-mode options for the stock visualizer.
 */
class Mode implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::MODE_ON_DEMAND, 'label' => __('On demand (fetch on click)')],
            ['value' => Config::MODE_INSTANT, 'label' => __('Instant (fetch on page load)')],
        ];
    }
}
