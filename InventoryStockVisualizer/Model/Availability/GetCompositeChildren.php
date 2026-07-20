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
use Magento\ConfigurableProduct\Model\Product\Type\Configurable;
use Magento\Framework\Exception\LocalizedException;
use Magento\GroupedProduct\Model\Product\Type\Grouped;

/**
 * Resolve the salable children of a composite product for the per-component breakdown:
 * configurable variants, grouped associated products or bundle selections.
 *
 * Only the identity (SKU) and a display label are returned here; the salable quantity is
 * resolved separately per child on the stock, since each child is an ordinary stockable SKU.
 */
class GetCompositeChildren
{
    /**
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(private readonly ProductRepositoryInterface $productRepository)
    {
    }

    /**
     * Child rows (sku and label), de-duplicated by SKU, for a composite parent.
     *
     * @param string $sku
     * @return array<int, array{sku: string, label: string}>
     */
    public function execute(string $sku): array
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (LocalizedException $e) {
            return [];
        }

        $type = $product->getTypeInstance();
        if ($type instanceof Configurable) {
            $children = $type->getUsedProducts($product);
        } elseif ($type instanceof Grouped) {
            $children = $type->getAssociatedProducts($product);
        } elseif ($type instanceof BundleType) {
            $children = $this->getBundleSelections($type, $product);
        } else {
            return [];
        }

        $rows = [];
        $seen = [];
        foreach ($children as $child) {
            $childSku = (string) $child->getSku();
            if ($childSku === '' || isset($seen[$childSku])) {
                continue;
            }
            $seen[$childSku] = true;
            $rows[] = [
                'sku' => $childSku,
                'label' => (string) ($child->getName() ?: $childSku),
            ];
        }

        return $rows;
    }

    /**
     * Selectable products across all bundle options.
     *
     * @param BundleType $type
     * @param ProductInterface $product
     * @return \Magento\Framework\DataObject[]
     */
    private function getBundleSelections(BundleType $type, ProductInterface $product): array
    {
        $optionIds = $type->getOptionsIds($product);
        if (!$optionIds) {
            return [];
        }

        return iterator_to_array($type->getSelectionsCollection($optionIds, $product));
    }
}
