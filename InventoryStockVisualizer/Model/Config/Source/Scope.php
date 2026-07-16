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
 * Display-scope options for the stock visualizer.
 */
class Scope implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::SCOPE_AGGREGATE, 'label' => __('Aggregate')],
            ['value' => Config::SCOPE_PER_SOURCE, 'label' => __('Per source')],
        ];
    }
}
