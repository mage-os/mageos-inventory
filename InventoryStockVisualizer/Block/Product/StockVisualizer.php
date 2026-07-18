<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Block\Product;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\InventoryCatalog\Model\GetStockIdForCurrentWebsite;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Cache\CacheTag;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\GetEnabledSources;
use Magento\InventoryStockVisualizer\Model\Level;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;

/**
 * Product-page "Availability" panel.
 */
class StockVisualizer extends Template implements IdentityInterface
{
    /**
     * @var DisplayConfig|null
     */
    private $displayConfig;

    /**
     * @var int|null
     */
    private $stockId;

    /**
     * @var bool
     */
    private $stockResolved = false;

    /**
     * @var \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface|null
     */
    private $view;

    /**
     * @param Context $context
     * @param Registry $registry
     * @param Config $config
     * @param Json $json
     * @param ResolveDisplayConfig $resolveDisplayConfig
     * @param GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite
     * @param GetStockViewInterface $getStockView
     * @param GetEnabledSources $getEnabledSources
     * @param LevelResolver $levelResolver
     * @param array<string, mixed> $data
     */
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly Json $json,
        private readonly ResolveDisplayConfig $resolveDisplayConfig,
        private readonly GetStockIdForCurrentWebsite $getStockIdForCurrentWebsite,
        private readonly GetStockViewInterface $getStockView,
        private readonly GetEnabledSources $getEnabledSources,
        private readonly LevelResolver $levelResolver,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Whether the panel should render on the current product page.
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->config->isEnabled() && $this->getProduct() !== null && $this->getStockId() !== null;
    }

    /**
     * Whether the panel shows a coarse level (semaphore) instead of the number.
     *
     * @return bool
     */
    public function isLevelMode(): bool
    {
        return $this->getDisplayConfig()->isLevel();
    }

    /**
     * Whether the panel breaks availability down per source.
     *
     * @return bool
     */
    public function isPerSource(): bool
    {
        return $this->config->getScope() === Config::SCOPE_PER_SOURCE;
    }

    /**
     * Whether the quantity is fetched on a call-to-action click rather than on page load.
     *
     * When on demand, the initial state (status word plus the call-to-action) is rendered
     * server-side and the volatile numbers stay hidden until the fetch, so the widget never
     * flashes a loading skeleton before it mounts.
     *
     * @return bool
     */
    public function isOnDemand(): bool
    {
        return $this->config->getMode() === Config::MODE_ON_DEMAND;
    }

    /**
     * Panel heading.
     *
     * @return string
     */
    public function getPanelTitle(): string
    {
        return (string) __('Availability');
    }

    /**
     * Aggregate level (level mode).
     *
     * @return string
     */
    public function getAggregateLevel(): string
    {
        return $this->levelResolver->resolve($this->getView()->getSalableQty(), $this->getDisplayConfig());
    }

    /**
     * Server-rendered aggregate status for quantity mode: in stock or out of stock.
     *
     * The status is a coarse salability fact already cached with the product page, so it
     * is baked into the HTML; only the exact number is fetched over AJAX. The widget
     * reconciles it if a live quantity ever contradicts the cached status.
     *
     * @return string
     */
    public function getQuantityStatusLevel(): string
    {
        return $this->getView()->getSalableQty() > 0.0 ? Level::HIGH : Level::OUT;
    }

    /**
     * Per-source level rows (level mode), honouring hide-empty.
     *
     * @return array<int, array{name: string, level: string}>
     */
    public function getLevelSources(): array
    {
        $hideEmpty = $this->config->hideEmptySources();
        $rows = [];
        foreach ($this->getView()->getSources() as $source) {
            $qty = $source->getQty();
            if ($hideEmpty && $qty <= 0.0) {
                continue;
            }
            $rows[] = [
                'name' => (string) $source->getName(),
                'level' => $this->levelResolver->resolve($qty, $this->getDisplayConfig()),
            ];
        }

        return $rows;
    }

    /**
     * Per-source scaffold rows (quantity mode) - labels only, numbers arrive over AJAX.
     *
     * @return array<int, array{code: string, name: string}>
     */
    public function getScaffoldSources(): array
    {
        return $this->getEnabledSources->execute((int) $this->getStockId());
    }

    /**
     * Whether per-source labels are shown.
     *
     * @return bool
     */
    public function showSourceLabels(): bool
    {
        return $this->config->showSourceLabels();
    }

    /**
     * Full data-mage-init payload for quantity mode (keyed by the widget name).
     *
     * @return string
     */
    public function getWidgetConfig(): string
    {
        $product = $this->getProduct();

        return $this->json->serialize([
            'stockVisualizer' => [
                'mode' => $this->config->getMode(),
                'scope' => $this->config->getScope(),
                'sku' => $product ? (string) $product->getSku() : '',
                'hideEmptySources' => $this->config->hideEmptySources(),
                'ajaxUrl' => $this->getUrl('inventory_stockviz/product/view'),
            ],
        ]);
    }

    /**
     * CSS modifier class for a level.
     *
     * @param string $level
     * @return string
     */
    public function levelClass(string $level): string
    {
        return 'level-' . $level;
    }

    /**
     * Availability-bar fill percentage for a level (level mode).
     *
     * @param string $level
     * @return int
     */
    public function levelFill(string $level): int
    {
        return $this->levelResolver->fillPercent($level);
    }

    /**
     * Human-readable label for a level.
     *
     * @param string $level
     * @return string
     */
    public function levelLabel(string $level): string
    {
        switch ($level) {
            case Level::HIGH:
                return (string) __('In stock');
            case Level::MEDIUM:
                return (string) __('Limited availability');
            case Level::LOW:
                return (string) __('Low stock');
            default:
                return (string) __('Out of stock');
        }
    }

    /**
     * @inheritdoc
     */
    public function getIdentities(): array
    {
        if (!$this->isEnabled() || !$this->isLevelMode()) {
            return [];
        }

        return [CacheTag::CACHE_TAG . '_' . (int) $this->getProduct()->getId()];
    }

    /**
     * @inheritdoc
     */
    protected function _toHtml()
    {
        return $this->isEnabled() ? parent::_toHtml() : '';
    }

    /**
     * Current product from the registry, if any.
     *
     * @return ProductInterface|null
     */
    private function getProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');

        return $product instanceof ProductInterface ? $product : null;
    }

    /**
     * Stock id for the current website, or null when it cannot be resolved.
     *
     * @return int|null
     */
    private function getStockId(): ?int
    {
        if (!$this->stockResolved) {
            $this->stockResolved = true;
            try {
                $this->stockId = (int) $this->getStockIdForCurrentWebsite->execute();
            } catch (\Throwable $e) {
                $this->stockId = null;
            }
        }

        return $this->stockId;
    }

    /**
     * Effective display config (per-product override merged over store defaults).
     *
     * @return DisplayConfig
     */
    private function getDisplayConfig(): DisplayConfig
    {
        if ($this->displayConfig === null) {
            $this->displayConfig = $this->resolveDisplayConfig->forProduct($this->getProduct());
        }

        return $this->displayConfig;
    }

    /**
     * Availability quantities for the current product/stock (level mode), memoized per render.
     *
     * @return \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface
     */
    private function getView(): \Magento\InventoryStockVisualizer\Api\Data\StockViewInterface
    {
        if ($this->view === null) {
            $this->view = $this->getStockView->execute(
                (string) $this->getProduct()->getSku(),
                (int) $this->getStockId()
            );
        }

        return $this->view;
    }
}
