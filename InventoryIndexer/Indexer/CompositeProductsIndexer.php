<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Indexer;

use Magento\Catalog\Model\ProductTypes\ConfigInterface as ProductTypesConfig;
use Magento\InventoryApi\Model\GetStockIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetChildrenSkusOfParentSkusInterface;
use Magento\InventoryCatalogApi\Model\GetProductTypesBySkusInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\SkuListInStockFactory;
use Magento\InventoryIndexer\Indexer\Stock\SkuListsProcessor;

class CompositeProductsIndexer
{
    /**
     * @param ProductTypesConfig $productTypesConfig
     * @param GetProductTypesBySkusInterface $getProductTypesBySkus
     * @param GetChildrenSkusOfParentSkusInterface $getChildrenSkusOfParentSkus
     * @param GetStockIdsBySkusInterface $getStockIdsBySkus
     * @param SkuListInStockFactory $skuListInStockFactory
     * @param SkuListsProcessor $skuListsProcessor
     */
    public function __construct(
        private readonly ProductTypesConfig $productTypesConfig,
        private readonly GetProductTypesBySkusInterface $getProductTypesBySkus,
        private readonly GetChildrenSkusOfParentSkusInterface $getChildrenSkusOfParentSkus,
        private readonly GetStockIdsBySkusInterface $getStockIdsBySkus,
        private readonly SkuListInStockFactory $skuListInStockFactory,
        private readonly SkuListsProcessor $skuListsProcessor,
    ) {
    }

    /**
     * Reindex composite products present in the provided SKU list for stocks related to their child products.
     *
     * @param string[] $skus
     * @return void
     */
    public function reindexList(array $skus): void
    {
        if (!$skus) {
            return;
        }

        $compositeTypes = array_filter(
            $this->productTypesConfig->getAll(),
            fn (array $type) => ($type['composite'] ?? false) === true,
        );

        $productTypesBySkus = $this->getProductTypesBySkus->execute($skus);
        $productSkusByTypes = array_fill_keys(array_unique(array_values($productTypesBySkus)), []);
        foreach ($productTypesBySkus as $sku => $type) {
            $productSkusByTypes[$type][] = $sku;
        }
        $compositeSkus = array_merge([], ...array_values(array_intersect_key($productSkusByTypes, $compositeTypes)));
        if (!$compositeSkus) {
            return;
        }

        $parentStocks = [];
        $childrenSkusOfParentSkus = $this->getChildrenSkusOfParentSkus->execute($compositeSkus);
        foreach ($childrenSkusOfParentSkus as $parentSku => $childrenSkus) {
            if (!$childrenSkus) {
                continue;
            }

            $stockIds = $this->getStockIdsBySkus->execute($childrenSkus);
            foreach ($stockIds as $stockId) {
                $parentStocks[$stockId][] = $parentSku;
            }
        }

        $skuListInStockList = [];
        foreach ($parentStocks as $stockId => $parentSkus) {
            $skuListInStockList[] = $this->skuListInStockFactory->create(
                ['stockId' => $stockId, 'skuList' => $parentSkus]
            );
        }
        $this->skuListsProcessor->reindexList($skuListInStockList);
    }
}
