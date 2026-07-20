<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Availability;

use Magento\Bundle\Model\Product\Type as BundleType;
use Magento\Catalog\Api\Data\ProductInterface;
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
        $product = $this->loadBundleProduct($sku);
        if ($product === null) {
            return new BundleMaxResult(null, []);
        }

        $type = $product->getTypeInstance();
        $optionIds = $type->getOptionsIds($product);
        if (!$optionIds) {
            return new BundleMaxResult(null, []);
        }

        $evaluation = $this->evaluateSelections($type, $product, $optionIds, $selectedQtyBySelectionId, $stockId);
        if (!$this->hasAllRequiredOptions($type, $product, $evaluation['chosenOptionIds'])) {
            return new BundleMaxResult(null, []);
        }

        return new BundleMaxResult($evaluation['max'], $evaluation['productIds']);
    }

    /**
     * Load the SKU and return it only when it is a bundle product, otherwise null.
     *
     * @param string $sku
     * @return ProductInterface|null
     */
    private function loadBundleProduct(string $sku): ?ProductInterface
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (LocalizedException $e) {
            return null;
        }

        return $product->getTypeInstance() instanceof BundleType ? $product : null;
    }

    /**
     * Fold the chosen selections into the sellable cap, the touched option ids and product ids.
     *
     * @param BundleType $type
     * @param ProductInterface $product
     * @param int[] $optionIds
     * @param array<int|string,float|int|string> $selectedQtyBySelectionId
     * @param int $stockId
     * @return array{max: int|null, chosenOptionIds: array<int,true>, productIds: int[]}
     */
    private function evaluateSelections(
        BundleType $type,
        ProductInterface $product,
        array $optionIds,
        array $selectedQtyBySelectionId,
        int $stockId
    ): array {
        $chosenOptionIds = [];
        $productIds = [];
        $max = null;
        foreach ($type->getSelectionsCollection($optionIds, $product) as $selection) {
            $evaluated = $this->evaluateSelection($selection, $selectedQtyBySelectionId, $stockId);
            if ($evaluated === null) {
                continue;
            }
            $chosenOptionIds[$evaluated['optionId']] = true;
            if ($evaluated['cap'] === null) {
                continue;
            }
            if ($evaluated['productId'] > 0) {
                $productIds[$evaluated['productId']] = true;
            }
            $max = $max === null ? $evaluated['cap'] : min($max, $evaluated['cap']);
        }

        return ['max' => $max, 'chosenOptionIds' => $chosenOptionIds, 'productIds' => array_keys($productIds)];
    }

    /**
     * Evaluate one selection: null when not chosen, else its option id, sellable cap and product id.
     *
     * The cap is null when the selection is chosen but its per-bundle quantity is not positive, so
     * the option still counts as chosen without bounding the sellable count.
     *
     * @param \Magento\Bundle\Model\Selection $selection
     * @param array<int|string,float|int|string> $selectedQtyBySelectionId
     * @param int $stockId
     * @return array{optionId: int, cap: int|null, productId: int}|null
     */
    private function evaluateSelection($selection, array $selectedQtyBySelectionId, int $stockId): ?array
    {
        $selectionId = (int) $selection->getSelectionId();
        if (!array_key_exists($selectionId, $selectedQtyBySelectionId)) {
            return null;
        }

        $optionId = (int) $selection->getOptionId();
        $perBundleQty = $selection->getSelectionCanChangeQty()
            ? max(1.0, (float) $selectedQtyBySelectionId[$selectionId])
            : (float) $selection->getSelectionQty();
        if ($perBundleQty <= 0.0) {
            return ['optionId' => $optionId, 'cap' => null, 'productId' => 0];
        }

        try {
            $childSalable = (float) $this->getProductSalableQty->execute((string) $selection->getSku(), $stockId);
        } catch (LocalizedException $e) {
            $childSalable = 0.0;
        }

        return [
            'optionId' => $optionId,
            'cap' => (int) floor($childSalable / $perBundleQty),
            'productId' => (int) $selection->getProductId(),
        ];
    }

    /**
     * Whether every required bundle option has a chosen selection.
     *
     * @param BundleType $type
     * @param ProductInterface $product
     * @param array<int,true> $chosenOptionIds
     * @return bool
     */
    private function hasAllRequiredOptions(BundleType $type, ProductInterface $product, array $chosenOptionIds): bool
    {
        foreach ($type->getOptionsCollection($product) as $option) {
            if ($option->getRequired() && !isset($chosenOptionIds[(int) $option->getOptionId()])) {
                return false;
            }
        }

        return true;
    }
}
