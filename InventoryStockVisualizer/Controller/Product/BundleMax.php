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
use Magento\InventoryStockVisualizer\Model\Availability\GetBundleMaxSellable;
use Magento\InventoryStockVisualizer\Model\Cache\CacheTag;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use Magento\PageCache\Model\Config as PageCacheConfig;

/**
 * Return how many bundles can be sold for the customer's current selection.
 *
 * The answer depends only on the SKU, the selection and the website stock, so identical
 * selections share a cacheable fragment tagged with the chosen children's purge tags: any
 * stock change on a chosen child invalidates exactly the selections that include it.
 */
class BundleMax implements HttpGetActionInterface
{
    /**
     * Default public lifetime when neither the feature nor the FPC define one.
     */
    private const DEFAULT_TTL = 86400;

    /**
     * @param RequestInterface $request
     * @param JsonFactory $jsonFactory
     * @param Config $config
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param GetBundleMaxSellable $getBundleMaxSellable
     * @param ScopeConfigInterface $scopeConfig
     * @param LevelResolver $levelResolver
     * @param ResolveDisplayConfig $resolveDisplayConfig
     */
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly Config $config,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        private readonly GetBundleMaxSellable $getBundleMaxSellable,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LevelResolver $levelResolver,
        private readonly ResolveDisplayConfig $resolveDisplayConfig
    ) {
    }

    /**
     * Resolve the sellable-bundle count for the posted selection and emit it as JSON.
     *
     * @return Json
     */
    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        $sku = (string) $this->request->getParam('sku');
        $selections = $this->parseSelections($this->request->getParam('selections'));

        if (!$this->config->isEnabled() || $sku === '') {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        try {
            $stockId = $this->getStockIdForCurrentWebsite->execute();
            $bundleMax = $this->getBundleMaxSellable->execute($sku, $selections, $stockId);
        } catch (\Throwable $e) {
            return $this->uncacheable($result)->setData(['data' => null]);
        }

        $max = $bundleMax->getMax();
        $productIds = $bundleMax->getProductIds();
        $payload = $this->payload($sku, $max);

        if ($max === null || $productIds === []) {
            return $this->uncacheable($result)->setData(['data' => $payload]);
        }

        return $this->cacheable($result, $productIds)->setData(['data' => $payload]);
    }

    /**
     * Project the sellable count for the client.
     *
     * The exact count in quantity display, or a coarse level (never the number) in level display.
     *
     * @param string $sku
     * @param int|null $max
     * @return array<string, mixed>|null
     */
    private function payload(string $sku, ?int $max): ?array
    {
        if ($max === null) {
            return null;
        }
        if ($this->config->getDisplayType() === Config::DISPLAY_TYPE_LEVEL) {
            return [
                'level' => $this->levelResolver->resolve((float) $max, $this->resolveDisplayConfig->forSku($sku)),
                'salable' => $max > 0,
            ];
        }

        return ['max' => $max];
    }

    /**
     * Parse the chosen selections (selection id => customer qty) from the request.
     *
     * @param mixed $raw
     * @return array<int|string, float|int|string>
     */
    private function parseSelections($raw): array
    {
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        return is_array($raw) ? $raw : [];
    }

    /**
     * Apply public cache headers and one purge tag per chosen child product.
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
