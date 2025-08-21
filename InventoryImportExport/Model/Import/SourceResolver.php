<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Model\Import;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;

class SourceResolver implements ResetAfterRequestInterface
{
    /**
     * @var array
     */
    private array $storeSourcesCache = [];

    /**
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
     */
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly StockResolverInterface $stockResolver,
        private readonly GetSourcesAssignedToStockOrderedByPriorityInterface $getSourcesAssignedToStock
    ) {
    }

    /**
     * Get sources assigned to stock for a given store.
     *
     * @param int $storeId
     * @return array
     * @throws NoSuchEntityException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function getSourcesForStore(int $storeId): array
    {
        if (!isset($this->storeSourcesCache[$storeId])) {
            $store = $this->storeManager->getStore($storeId);
            $websiteCode = $store->getWebsite()->getCode();
            $stock = $this->stockResolver->execute(
                SalesChannelInterface::TYPE_WEBSITE,
                $websiteCode
            );
            $stockId = (int) $stock->getStockId();

            $sources = $this->getSourcesAssignedToStock->execute($stockId);
            $this->storeSourcesCache[$storeId] = array_map(fn($source) => $source->getSourceCode(), $sources);
        }

        return $this->storeSourcesCache[$storeId];
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->storeSourcesCache = [];
    }
}
