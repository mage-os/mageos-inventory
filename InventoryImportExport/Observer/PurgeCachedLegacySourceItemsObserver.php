<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Observer;

use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryConfiguration\Model\LegacyStockItem\CacheStorage;

class PurgeCachedLegacySourceItemsObserver implements ObserverInterface
{
    /**
     * @param CacheStorage $cacheStorage
     */
    public function __construct(private readonly CacheStorage $cacheStorage)
    {
    }

    /**
     * @param Observer $observer
     * @return void
     */
    public function execute(Observer $observer): void
    {
        $sourceItems = $observer->getEvent()->getSourceItems();
        if (empty($sourceItems)) {
            return;
        }
        /** @var SourceItemInterface $sourceItem */
        foreach ($sourceItems as $sourceItem) {
            if (!empty($this->cacheStorage->get($sourceItem->getSku()))) {
                $this->cacheStorage->delete($sourceItem->getSku());
            }
        }
    }
}
