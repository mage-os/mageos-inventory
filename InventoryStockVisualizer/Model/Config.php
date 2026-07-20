<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Reader for the storefront stock visualizer store-scoped configuration.
 *
 * @api
 */
class Config
{
    public const XML_PATH_ENABLED = 'cataloginventory/stock_visualizer/enabled';
    public const XML_PATH_MODE = 'cataloginventory/stock_visualizer/mode';
    public const XML_PATH_DISPLAY_TYPE = 'cataloginventory/stock_visualizer/display_type';
    public const XML_PATH_SCOPE = 'cataloginventory/stock_visualizer/scope';
    public const XML_PATH_LEVEL_BASIS = 'cataloginventory/stock_visualizer/level_basis';
    public const XML_PATH_LEVEL_HIGH = 'cataloginventory/stock_visualizer/level_high';
    public const XML_PATH_LEVEL_LOW = 'cataloginventory/stock_visualizer/level_low';
    public const XML_PATH_TTL = 'cataloginventory/stock_visualizer/ttl';
    public const XML_PATH_SHOW_SOURCE_LABELS = 'cataloginventory/stock_visualizer/show_source_labels';
    public const XML_PATH_HIDE_EMPTY_SOURCES = 'cataloginventory/stock_visualizer/hide_empty_sources';
    public const XML_PATH_ASYNC_PURGE = 'cataloginventory/stock_visualizer/async_purge';
    public const XML_PATH_CONFIGURABLE_MODE = 'cataloginventory/stock_visualizer/composite_configurable_mode';
    public const XML_PATH_BUNDLE_MODE = 'cataloginventory/stock_visualizer/composite_bundle_mode';
    public const XML_PATH_GROUPED_MODE = 'cataloginventory/stock_visualizer/composite_grouped_mode';
    public const XML_PATH_GROUPED_SETS_CALCULATOR =
        'cataloginventory/stock_visualizer/composite_grouped_sets_calculator';

    public const COMPOSITE_MODE_STATUS = 'status';
    public const COMPOSITE_MODE_CHILDREN = 'children';
    public const COMPOSITE_MODE_VARIANT = 'variant';
    public const COMPOSITE_MODE_MAX = 'max';

    public const MODE_INSTANT = 'instant';
    public const MODE_ON_DEMAND = 'on_demand';

    public const ASYNC_PURGE_AUTO = 'auto';
    public const ASYNC_PURGE_ON = 'on';
    public const ASYNC_PURGE_OFF = 'off';

    public const DISPLAY_TYPE_QUANTITY = 'quantity';
    public const DISPLAY_TYPE_LEVEL = 'level';

    public const LEVEL_BASIS_QUANTITY = 'quantity';
    public const LEVEL_BASIS_PERCENTAGE = 'percentage';

    public const SCOPE_AGGREGATE = 'aggregate';
    public const SCOPE_PER_SOURCE = 'per_source';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * Whether the visualizer is enabled for the given store.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Delivery mode (quantity display only): instant or on-demand.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getMode($store = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_MODE, ScopeInterface::SCOPE_STORE, $store)
            ?: self::MODE_ON_DEMAND;
    }

    /**
     * Display type: exact quantity or coarse level (semaphore).
     *
     * @param int|string|null $store
     * @return string
     */
    public function getDisplayType($store = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_DISPLAY_TYPE, ScopeInterface::SCOPE_STORE, $store)
            ?: self::DISPLAY_TYPE_LEVEL;
    }

    /**
     * Display scope: aggregate or per-source.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getScope($store = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_SCOPE, ScopeInterface::SCOPE_STORE, $store)
            ?: self::SCOPE_AGGREGATE;
    }

    /**
     * Level basis: absolute quantity thresholds or percentage of a reference.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getLevelBasis($store = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_LEVEL_BASIS, ScopeInterface::SCOPE_STORE, $store)
            ?: self::LEVEL_BASIS_QUANTITY;
    }

    /**
     * Threshold above which the level is high.
     *
     * @param int|string|null $store
     * @return float
     */
    public function getLevelHigh($store = null): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_PATH_LEVEL_HIGH, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Threshold above which the level is medium (at or below it is low).
     *
     * @param int|string|null $store
     * @return float
     */
    public function getLevelLow($store = null): float
    {
        return (float) $this->scopeConfig->getValue(self::XML_PATH_LEVEL_LOW, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Cache TTL in seconds for the quantity fragment. 0 relies on tag purge only.
     *
     * @param int|string|null $store
     * @return int
     */
    public function getTtl($store = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_PATH_TTL, ScopeInterface::SCOPE_STORE, $store));
    }

    /**
     * Whether the per-source breakdown shows source labels.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function showSourceLabels($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_SHOW_SOURCE_LABELS, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Whether the per-source breakdown hides sources with no available quantity.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function hideEmptySources($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_HIDE_EMPTY_SOURCES, ScopeInterface::SCOPE_STORE, $store);
    }

    /**
     * Cache-purge delivery strategy: auto (async only under scheduled indexing), on, or off.
     *
     * The store is irrelevant for the write-path decision, so this is read on the default scope.
     *
     * @return string
     */
    public function getAsyncPurge(): string
    {
        $value = (string) $this->scopeConfig->getValue(self::XML_PATH_ASYNC_PURGE);

        return in_array($value, [self::ASYNC_PURGE_ON, self::ASYNC_PURGE_OFF], true)
            ? $value
            : self::ASYNC_PURGE_AUTO;
    }

    /**
     * Availability display mode for configurable products.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getConfigurableMode($store = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CONFIGURABLE_MODE, ScopeInterface::SCOPE_STORE, $store)
            ?: self::COMPOSITE_MODE_VARIANT;
    }

    /**
     * Availability display mode for bundle products.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getBundleMode($store = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_BUNDLE_MODE, ScopeInterface::SCOPE_STORE, $store)
            ?: self::COMPOSITE_MODE_MAX;
    }

    /**
     * Availability display mode for grouped products.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getGroupedMode($store = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_GROUPED_MODE, ScopeInterface::SCOPE_STORE, $store)
            ?: self::COMPOSITE_MODE_CHILDREN;
    }

    /**
     * Whether the grouped "complete sets" calculator is shown alongside the per-component list.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isGroupedSetsCalculatorEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GROUPED_SETS_CALCULATOR,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Short fingerprint of the display configuration that shapes the AJAX fragments.
     *
     * The storefront appends it to the fragment request, so changing any of these settings mints a
     * fresh cache key and the new output is served immediately instead of waiting for a tag purge.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getVersion($store = null): string
    {
        return substr(hash('sha256', implode('|', [
            $this->getDisplayType($store),
            $this->getScope($store),
            $this->getLevelBasis($store),
            (string) $this->getLevelHigh($store),
            (string) $this->getLevelLow($store),
            $this->showSourceLabels($store) ? '1' : '0',
            $this->hideEmptySources($store) ? '1' : '0',
            $this->getConfigurableMode($store),
            $this->getBundleMode($store),
            $this->getGroupedMode($store),
            $this->isGroupedSetsCalculatorEnabled($store) ? '1' : '0',
        ])), 0, 12);
    }
}
