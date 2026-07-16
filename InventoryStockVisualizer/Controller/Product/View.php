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
use Magento\InventoryStockVisualizer\Model\Cache\CacheTag;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;

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
        private readonly ResolveDisplayConfig $resolveDisplayConfig,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $sku = (string) $this->request->getParam('sku');
        $productId = (int) $this->request->getParam('product_id');

        if (!$this->config->isEnabled() || $sku === '' || $productId <= 0) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        if ($this->resolveDisplayConfig->forSku($sku)->isLevel()) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        try {
            $stockId = $this->getStockIdForCurrentWebsite->execute();
            $view = $this->getStockView->execute($sku, $stockId);
            $data = $this->stockViewSerializer->serialize($view);
        } catch (\Throwable $e) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        return $this->cacheable($result, $productId)->setData(['data' => $data]);
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
        $ttl = $this->config->getTtl() ?: (int) $this->scopeConfig->getValue('system/full_page_cache/ttl');
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
