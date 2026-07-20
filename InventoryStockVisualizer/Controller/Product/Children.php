<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Controller\Product;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryCatalogApi\Model\GetSkusByProductIdsInterface;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Availability\GetGroupedSetsMax;
use Magento\InventoryStockVisualizer\Model\Cache\CacheTag;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;
use Magento\PageCache\Model\Config as PageCacheConfig;

/**
 * Return the per-child availability of a composite product as a cacheable, tag-purgeable
 * JSON fragment, so the volatile child quantities never sit in the product page cache.
 *
 * The fragment is tagged with each child's dedicated purge tag: a stock change on one child
 * invalidates exactly the composites that list it, leaving the product page untouched.
 */
class Children implements HttpGetActionInterface
{
    /**
     * Default public lifetime when neither the feature nor the FPC define one.
     */
    private const DEFAULT_TTL = 86400;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param Config $config
     * @param GetStockViewInterface $getStockView
     * @param StockViewSerializer $stockViewSerializer
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param GetProductIdsBySkusInterface $getProductIdsBySkus
     * @param GetSkusByProductIdsInterface $getSkusByProductIds
     * @param GetGroupedSetsMax $getGroupedSetsMax
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly GetStockViewInterface $getStockView,
        private readonly StockViewSerializer $stockViewSerializer,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        private readonly GetProductIdsBySkusInterface $getProductIdsBySkus,
        private readonly GetSkusByProductIdsInterface $getSkusByProductIds,
        private readonly GetGroupedSetsMax $getGroupedSetsMax,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Resolve the composite children availability and emit it as a purge-tagged JSON fragment.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $sku = (string) $this->request->getParam('sku');
        if ($sku === '') {
            $sku = $this->resolveSkuFromProductId((int) $this->request->getParam('product_id'));
        }

        if (!$this->config->isEnabled() || $sku === '') {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        try {
            $stockId = $this->getStockIdForCurrentWebsite->execute();
            $view = $this->getStockView->execute($sku, $stockId);
        } catch (\Throwable $e) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        if (!$view->isAggregateOnly()) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        $data = $this->stockViewSerializer->serializeChildren($view);
        $sets = $this->resolveSets($sku, $stockId);
        if ($sets !== null) {
            $data['sets'] = $sets;
        }

        $productIds = $this->resolveChildProductIds($data['children']);
        if ($productIds === []) {
            return $this->uncacheable($result)->setData(['data' => $data]);
        }

        return $this->cacheable($result, $productIds)->setData(['data' => $data]);
    }

    /**
     * Maximum complete grouped sets, when the calculator applies to this product, else null.
     *
     * @param string $sku
     * @param int $stockId
     * @return int|null
     */
    private function resolveSets(string $sku, int $stockId): ?int
    {
        if (!$this->config->isGroupedSetsCalculatorEnabled()
            || $this->config->getGroupedMode() !== Config::COMPOSITE_MODE_CHILDREN
        ) {
            return null;
        }

        $sets = $this->getGroupedSetsMax->execute($sku, $stockId);
        if ($sets === null) {
            return null;
        }

        // Level display exposes no exact numbers, so the set count collapses to a coarse flag.
        if ($this->config->getDisplayType() === Config::DISPLAY_TYPE_LEVEL) {
            return $sets > 0 ? 1 : 0;
        }

        return $sets;
    }

    /**
     * Resolve the product ids of the listed children for tagging.
     *
     * @param array<int,array{sku:string,label:string,salable:bool,qty:float}> $children
     * @return int[]
     */
    private function resolveChildProductIds(array $children): array
    {
        $skus = [];
        foreach ($children as $child) {
            $skus[] = $child['sku'];
        }
        if ($skus === []) {
            return [];
        }

        try {
            $map = $this->getProductIdsBySkus->execute($skus);
        } catch (\Throwable $e) {
            return [];
        }

        return array_values(array_map('intval', $map));
    }

    /**
     * Resolve a SKU from a product id (composite selected client-side), or '' if unknown.
     *
     * @param int $productId
     * @return string
     */
    private function resolveSkuFromProductId(int $productId): string
    {
        if ($productId <= 0) {
            return '';
        }

        try {
            $skus = $this->getSkusByProductIds->execute([$productId]);
        } catch (\Throwable $e) {
            return '';
        }

        return (string) ($skus[$productId] ?? '');
    }

    /**
     * Apply public cache headers and one purge tag per child product.
     *
     * @param Json $result
     * @param int[] $productIds
     * @return Json
     */
    private function cacheable(Json $result, array $productIds): Json
    {
        $ttl = $this->config->getTtl() ?: (int) $this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TTL);
        $ttl = $ttl > 0 ? $ttl : self::DEFAULT_TTL;

        $tags = [];
        foreach ($productIds as $productId) {
            $tags[] = CacheTag::CACHE_TAG . '_' . (int) $productId;
        }

        $result->setHeader('X-Magento-Tags', implode(',', $tags), true);
        $result->setHeader('Cache-Control', 'public, max-age=' . $ttl . ', s-maxage=' . $ttl, true);
        $result->setHeader('Pragma', 'cache', true);

        return $result;
    }

    /**
     * Mark the response as non-cacheable.
     *
     * @param Json $result
     * @return Json
     */
    private function uncacheable(Json $result): Json
    {
        $result->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $result->setHeader('Pragma', 'no-cache', true);

        return $result;
    }
}
