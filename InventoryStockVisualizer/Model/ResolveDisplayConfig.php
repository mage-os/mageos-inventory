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
            $displayType = $this->override((string) $product->getData(Attr::DISPLAY_TYPE)) ?? $displayType;
            $basis = $this->override((string) $product->getData(Attr::LEVEL_BASIS)) ?? $basis;
            $high = $this->overrideFloat($product->getData(Attr::LEVEL_HIGH)) ?? $high;
            $low = $this->overrideFloat($product->getData(Attr::LEVEL_LOW)) ?? $low;
            $fullQty = $this->overrideFloat($product->getData(Attr::FULL_QTY));
        }

        return new DisplayConfig($displayType, $basis, $high, $low, $fullQty);
    }

    /**
     * @param string $value
     * @return string|null
     */
    private function override(string $value): ?string
    {
        return $value !== '' ? $value : null;
    }

    /**
     * @param mixed $value
     * @return float|null
     */
    private function overrideFloat($value): ?float
    {
        return ($value !== null && $value !== '') ? (float) $value : null;
    }
}
