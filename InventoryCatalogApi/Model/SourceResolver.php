<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogApi\Model;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;

class SourceResolver
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly StockResolverInterface $stockResolver,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
    )
    {
    }

    /**
     * @param int $storeId
     * @return array
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSourcesForStore(int $storeId): array
    {
        $store = $this->storeManager->getStore($storeId);
        $websiteCode = $store->getWebsite()->getCode();
        $stock = $this->stockResolver->execute(
            SalesChannelInterface::TYPE_WEBSITE,
            $websiteCode
        );
        $stockId = (int) $stock->getStockId();

        $sources = $this->getSourcesAssignedToStock->execute($stockId);
        return array_map(fn($source) => $source->getSourceCode(), $sources);
    }
}

