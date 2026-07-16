<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryStockVisualizer\Model\Product\StockVisualizerAttributes as Attr;

/**
 * Resolve the effective display config for a product (per-product override over store defaults).
 *
 * @api
 */
class ResolveDisplayConfig
{
    /**
     * @param Config $config
     * @param ProductRepositoryInterface $productRepository
     */
    public function __construct(
        private readonly Config $config,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Resolve from an already-loaded product.
     *
     * @param ProductInterface|null $product
     * @param int|string|null $store
     * @return DisplayConfig
     */
    public function forProduct(?ProductInterface $product, $store = null): DisplayConfig
    {
        return $this->merge($product, $store);
    }

    /**
     * Resolve by SKU, loading the product.
     *
     * @param string $sku
     * @param int|string|null $store
     * @return DisplayConfig
     */
    public function forSku(string $sku, $store = null): DisplayConfig
    {
        try {
            $product = $this->productRepository->get($sku);
        } catch (NoSuchEntityException $e) {
            $product = null;
        }

        return $this->merge($product, $store);
    }

    /**
     * Merge the per-product override (when present) over the store-scoped defaults.
     *
     * @param ProductInterface|null $product
     * @param int|string|null $store
     * @return DisplayConfig
     */
    private function merge(?ProductInterface $product, $store): DisplayConfig
    {
        $displayType = $this->config->getDisplayType($store);
        $basis = $this->config->getLevelBasis($store);
        $high = $this->config->getLevelHigh($store);
        $low = $this->config->getLevelLow($store);
        $fullQty = null;

        if ($product !== null) {
            $displayType = $this->overrideString($this->attributeValue($product, Attr::DISPLAY_TYPE)) ?? $displayType;
            $basis = $this->overrideString($this->attributeValue($product, Attr::LEVEL_BASIS)) ?? $basis;
            $high = $this->overrideFloat($this->attributeValue($product, Attr::LEVEL_HIGH)) ?? $high;
            $low = $this->overrideFloat($this->attributeValue($product, Attr::LEVEL_LOW)) ?? $low;
            $fullQty = $this->overrideFloat($this->attributeValue($product, Attr::FULL_QTY));
        }

        return new DisplayConfig($displayType, $basis, $high, $low, $fullQty);
    }

    /**
     * Read a custom (EAV) attribute value from a product, or null when it is not set.
     *
     * @param ProductInterface $product
     * @param string $code
     * @return mixed
     */
    private function attributeValue(ProductInterface $product, string $code)
    {
        $attribute = $product->getCustomAttribute($code);

        return $attribute !== null ? $attribute->getValue() : null;
    }

    /**
     * Normalise a raw override value to a non-empty string, or null to fall through.
     *
     * @param mixed $value
     * @return string|null
     */
    private function overrideString($value): ?string
    {
        $value = $value === null ? '' : (string) $value;

        return $value !== '' ? $value : null;
    }

    /**
     * Normalise a raw override value to a float, or null to fall through.
     *
     * @param mixed $value
     * @return float|null
     */
    private function overrideFloat($value): ?float
    {
        return ($value !== null && $value !== '') ? (float) $value : null;
    }
}
