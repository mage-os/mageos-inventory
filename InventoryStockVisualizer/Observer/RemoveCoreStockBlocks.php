<?php
/**
 * Copyright 2026 Mage-OS
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\View\LayoutInterface;
use Magento\InventoryStockVisualizer\Model\Config;

/**
 * Replace the core product-page stock status with the visualizer panel, only where the panel is enabled.
 */
class RemoveCoreStockBlocks implements ObserverInterface
{
    /**
     * Core blocks that render the product-page stock status per product type.
     */
    private const CORE_STOCK_BLOCKS = [
        'product.info.simple',
        'product.info.virtual',
        'product.info.grouped.stock',
        'product.info.configurable',
        'product.info.bundle',
        'product.info.downloadable',
    ];

    /**
     * @param Config $config
     */
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * Remove the core stock status blocks once the layout is generated, if the visualizer is enabled.
     *
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        /** @var LayoutInterface $layout */
        $layout = $observer->getEvent()->getData('layout');
        if (!$layout->getBlock('product.info.stock.visualizer') || !$this->config->isEnabled()) {
            return;
        }

        foreach (self::CORE_STOCK_BLOCKS as $name) {
            if ($layout->getBlock($name)) {
                $layout->unsetElement($name);
            }
        }
    }
}
