<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogFrontendUi\Model;

use Magento\InventoryConfigurationApi\Api\Data\StockItemConfigurationInterface;

class IsSalableQtyThresholdReached
{
    public function execute(float $productSalableQty, StockItemConfigurationInterface $stockItemConfig): bool
    {
        return (
                $stockItemConfig->getBackorders() === StockItemConfigurationInterface::BACKORDERS_NO
                || (
                    $stockItemConfig->getBackorders() !== StockItemConfigurationInterface::BACKORDERS_NO
                    && $stockItemConfig->getMinQty() < 0
                )
            ) && $productSalableQty > 0 && $productSalableQty <= $stockItemConfig->getStockThresholdQty();
    }
}
