<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Availability;

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventorySalesApi\Api\GetProductSalableQtyInterface;

/**
 * Compute how many units of a bundle can be sold for the customer's current selection.
 *
 * The result is bounded by the required options (each must have a chosen, salable selection)
 * and by every chosen selection's stock: max = min over chosen selections of
 * floor(childSalableQty / perBundleQty). Returns null while a required option is unselected,
 * so the storefront can prompt the customer to finish choosing.
 */
class GetBundleMaxSellable
{
    /**
     * @param ProductRepositoryInterface $productRepository
     * @param GetProductSalableQtyInterface $getProductSalableQty
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly GetProductSalableQtyInterface $getProductSalableQty
    ) {
    }

    /**
     * Maximum sellable bundle count for the given selection, with the child product ids it depends on.
     *
     * The count is null when the selection is not yet complete.
     *
     * @param string $sku
     * @param array<int|string,float|int|string> $selectedQtyBySelectionId chosen selection id => customer qty
     * @param int $stockId
     * @return BundleMaxResult
     */
    public function execute(string $sku, array $selectedQtyBySelectionId, int $stockId): BundleMaxResult
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (LocalizedException $e) {
            return new BundleMaxResult(null, []);
        }

        $type = $product->getTypeInstance();
        if (!$type instanceof BundleType) {
            return new BundleMaxResult(null, []);
        }

        $optionIds = $type->getOptionsIds($product);
        if (!$optionIds) {
            return new BundleMaxResult(null, []);
        }

        $requiredOptionIds = [];
        foreach ($type->getOptionsCollection($product) as $option) {
            if ($option->getRequired()) {
                $requiredOptionIds[(int) $option->getOptionId()] = true;
            }
        }

        $chosenOptionIds = [];
        $productIds = [];
        $max = null;
        foreach ($type->getSelectionsCollection($optionIds, $product) as $selection) {
            $selectionId = (int) $selection->getSelectionId();
            if (!array_key_exists($selectionId, $selectedQtyBySelectionId)) {
                continue;
            }
            $chosenOptionIds[(int) $selection->getOptionId()] = true;

            $perBundleQty = $selection->getSelectionCanChangeQty()
                ? max(1.0, (float) $selectedQtyBySelectionId[$selectionId])
                : (float) $selection->getSelectionQty();
            if ($perBundleQty <= 0.0) {
                continue;
            }

            try {
                $childSalable = (float) $this->getProductSalableQty->execute((string) $selection->getSku(), $stockId);
            } catch (LocalizedException $e) {
                $childSalable = 0.0;
            }

            $productId = (int) $selection->getProductId();
            if ($productId > 0) {
                $productIds[$productId] = true;
            }
            $cap = (int) floor($childSalable / $perBundleQty);
            $max = $max === null ? $cap : min($max, $cap);
        }

        foreach (array_keys($requiredOptionIds) as $optionId) {
            if (!isset($chosenOptionIds[$optionId])) {
                return new BundleMaxResult(null, []);
            }
        }

        return new BundleMaxResult($max, array_keys($productIds));
    }
}
