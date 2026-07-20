<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Availability;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;

/**
 * Compute how many complete sets of a grouped product can be assembled from current stock.
 *
 * A set is one unit of every associated product at its configured default quantity (the recipe;
 * a default quantity of 0 falls back to 1). Every associated product is considered, including
 * out-of-stock ones, since a set cannot be completed while any component is unavailable:
 * max sets = min over associated products of floor(childSalableQty / recipeQty). Returns null
 * when the product is not a grouped product or has no associated products.
 *
 * The full component list comes from the type's link table (not the storefront associated-product
 * collection, which hides out-of-stock components and would over-report the buildable sets).
 */
class GetGroupedSetsMax
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param GetProductSalableQtyInterface $getProductSalableQty
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly GetProductSalableQtyInterface $getProductSalableQty,
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds
    ) {
    }

    /**
     * Maximum number of complete sets for the grouped product, or null when not applicable.
     *
     * @param string $sku
     * @param int $stockId
     * @return int|null
     */
    public function execute(string $sku, int $stockId): ?int
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (LocalizedException $e) {
            return null;
        }

        if ($product->getTypeId() !== Grouped::TYPE_CODE) {
            return null;
        }

        $childIds = [];
        foreach ($product->getTypeInstance()->getChildrenIds((int) $product->getId()) as $group) {
            foreach ($group as $childId) {
                $childIds[] = (int) $childId;
            }
        }
        if (!$childIds) {
            return null;
        }

        $recipeBySku = $this->getRecipeBySku($product);

        $max = null;
        foreach ($this->getSkusByProductIds->execute($childIds) as $childSku) {
            $childSku = (string) $childSku;
            $recipeQty = $recipeBySku[$childSku] ?? 1.0;

            try {
                $childSalable = (float) $this->getProductSalableQty->execute($childSku, $stockId);
            } catch (LocalizedException $e) {
                $childSalable = 0.0;
            }

            $cap = (int) floor(max(0.0, $childSalable) / $recipeQty);
            $max = $max === null ? $cap : min($max, $cap);
        }

        return $max;
    }

    /**
     * Per-component default quantity (the recipe), keyed by child SKU; 0 or missing falls back to 1.
     *
     * @param \Magento\Catalog\Api\Data\ProductInterface $product
     * @return array<string, float>
     */
    private function getRecipeBySku($product): array
    {
        $recipe = [];
        foreach ($product->getProductLinks() as $link) {
            if ($link->getLinkType() !== 'associated') {
                continue;
            }
            $extension = $link->getExtensionAttributes();
            $qty = $extension !== null ? (float) $extension->getQty() : 0.0;
            $recipe[(string) $link->getLinkedProductSku()] = $qty > 0.0 ? $qty : 1.0;
        }

        return $recipe;
    }
}
