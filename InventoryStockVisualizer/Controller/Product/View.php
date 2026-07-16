<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
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
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Cache\CacheTag;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;
use Magento\PageCache\Model\Config as PageCacheConfig;

/**
 * Return the quantity availability of a product as a cacheable, tag-purgeable JSON fragment.
 */
class View implements HttpGetActionInterface
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
     * @param ResolveDisplayConfig $resolveDisplayConfig
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
        private readonly ResolveDisplayConfig $resolveDisplayConfig,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Resolve the availability for the requested SKU and emit it as a cacheable fragment.
     *
     * The stock and product id are derived server-side from the website context and the
     * SKU, so request-supplied ids cannot decouple the cache tag from the data or flood
     * the cache key. Level mode never reaches here (guarded), so quantities never leak.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $sku = (string) $this->request->getParam('sku');

        if (!$this->config->isEnabled() || $sku === '') {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        if ($this->resolveDisplayConfig->forSku($sku)->isLevel()) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        try {
            $stockId = $this->getStockIdForCurrentWebsite->execute();
            $view = $this->getStockView->execute($sku, $stockId);
            $data = $this->stockViewSerializer->serialize($view);
            $productId = $this->resolveProductId($sku);
        } catch (\Throwable $e) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        if ($productId <= 0) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        return $this->cacheable($result, $productId)->setData(['data' => $data]);
    }

    /**
     * Resolve the real product id for a SKU, or 0 when it cannot be resolved.
     *
     * @param string $sku
     * @return int
     */
    private function resolveProductId(string $sku): int
    {
        $ids = $this->getProductIdsBySkus->execute([$sku]);

        return (int) ($ids[$sku] ?? 0);
    }

    /**
     * Apply public cache headers and the dedicated purge tag.
     *
     * @param Json $result
     * @param int $productId
     * @return Json
     */
    private function cacheable(Json $result, int $productId): Json
    {
        $ttl = $this->config->getTtl() ?: (int) $this->scopeConfig->getValue(PageCacheConfig::XML_PAGECACHE_TTL);
        $ttl = $ttl > 0 ? $ttl : self::DEFAULT_TTL;

        $result->setHeader('X-Magento-Tags', CacheTag::CACHE_TAG . '_' . $productId, true);
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
