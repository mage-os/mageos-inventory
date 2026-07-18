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
 * Cache-purge delivery-strategy options for the stock visualizer.
 */
class AsyncPurge implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => Config::ASYNC_PURGE_AUTO, 'label' => __('Auto (async under scheduled indexing)')],
            ['value' => Config::ASYNC_PURGE_ON, 'label' => __('Always async (queue)')],
            ['value' => Config::ASYNC_PURGE_OFF, 'label' => __('Always synchronous')],
        ];
    }
}
