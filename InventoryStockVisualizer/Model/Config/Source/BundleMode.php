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
 * Availability display modes for bundle products.
 */
class BundleMode implements OptionSourceInterface
{
    /**
     * @inheritdoc
     *
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            [
                'value' => Config::COMPOSITE_MODE_MAX,
                'label' => __('Sellable bundles (how many of the current selection)')
            ],
            [
                'value' => Config::COMPOSITE_MODE_CHILDREN,
                'label' => __('Per component (each selection stock)')
            ],
            [
                'value' => Config::COMPOSITE_MODE_STATUS,
                'label' => __('Aggregate status (in stock / out of stock)')
            ],
        ];
    }
}
