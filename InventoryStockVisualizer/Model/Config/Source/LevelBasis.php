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
 * Level-basis options for the stock visualizer.
 */
class LevelBasis implements OptionSourceInterface
{
    /**
     * @inheritdoc
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::LEVEL_BASIS_QUANTITY, 'label' => __('By quantity')],
            ['value' => Config::LEVEL_BASIS_PERCENTAGE, 'label' => __('By percentage')],
        ];
    }
}
