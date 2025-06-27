<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockItemRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;

$objectManager = Bootstrap::getObjectManager();

/** @var ProductRepositoryInterface $productRepository */
$productRepository = $objectManager->get(ProductRepositoryInterface::class);
$stockItemRepository = $objectManager->get(StockItemRepositoryInterface::class);

$skus = ['SKU-1', 'SKU-2', 'SKU-3'];

foreach ($skus as $sku) {
    $product = $productRepository->get($sku);

    $stockItem = $product->getExtensionAttributes()->getStockItem();
    $stockItem->setUseConfigManageStock(false);
    $stockItem->setManageStock(true);

    $stockItemRepository->save($stockItem);
}
