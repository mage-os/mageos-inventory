<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Model;

use Magento\InventoryReservations\Model\GetReservationsQuantity\CacheStorage;
use Magento\InventoryReservations\Model\ResourceModel\GetReservationsQuantityBySkuList;
use Magento\InventoryReservationsApi\Model\GetReservationsQuantityBySkuListCacheInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;

class GetReservationsQuantityBySkuListCache implements GetReservationsQuantityBySkuListCacheInterface
{
    /**
     * @param GetReservationsQuantityBySkuList $getReservationsQuantityBySkuList
     * @param CacheStorage $reservationsQuantityCacheStorage
     * @param SourceReservationsConfig $sourceReservationsConfig
     */
    public function __construct(
        private readonly GetReservationsQuantityBySkuList $getReservationsQuantityBySkuList,
        private readonly CacheStorage $reservationsQuantityCacheStorage,
        private readonly SourceReservationsConfig $sourceReservationsConfig
    ) {
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus, int $stockId): array
    {
        $skusToLoad = [];
        $result = [];
        foreach ($skus as $sku) {
            if ($this->reservationsQuantityCacheStorage->has((string) $sku, $stockId)) {
                $result[$sku] = $this->reservationsQuantityCacheStorage->get((string) $sku, $stockId);
            } else {
                $skusToLoad[] = $sku;
            }
        }
        if (!empty($skusToLoad)) {
            foreach ($this->getReservationsQuantityBySkuList->execute($skusToLoad, $stockId) as $sku => $value) {
                $result[$sku] = $value;
                $this->reservationsQuantityCacheStorage->set((string) $sku, $stockId, $value);
            }
        }
        return $result;
    }
    
    /**
     * @inheritdoc
     */
    public function warmup(array $skus, int $stockId): void
    {
        $this->execute($skus, $stockId);
    }

    /**
     * @inheritdoc
     */
    public function clean(array $skus, ?int $stockId): void
    {
        if ($this->sourceReservationsConfig->isEnabled()) {
            $stockId = null;
        }
        foreach ($skus as $sku) {
            $this->reservationsQuantityCacheStorage->delete((string)$sku, $stockId);
        }
    }
}
