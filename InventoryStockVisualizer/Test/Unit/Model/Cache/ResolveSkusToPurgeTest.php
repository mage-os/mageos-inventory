<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Cache\ResolveSkusToPurge;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\SourceView;
use Magento\InventoryStockVisualizer\Model\Data\StockView;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see ResolveSkusToPurge
 */
class ResolveSkusToPurgeTest extends TestCase
{
    private const SKU = 'SLR-1';
    private const STOCK_ID = 10;

    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @var ResolveDisplayConfig|MockObject
     */
    private $resolveDisplayConfig;

    /**
     * @var GetStockViewInterface|MockObject
     */
    private $getStockView;

    /**
     * @var ResolveSkusToPurge
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
        $this->resolveDisplayConfig = $this->createMock(ResolveDisplayConfig::class);
        $this->getStockView = $this->createMock(GetStockViewInterface::class);

        $this->model = new ResolveSkusToPurge(
            $this->config,
            $this->resolveDisplayConfig,
            new LevelResolver(),
            $this->getStockView
        );
    }

    /**
     * Disabled feature resolves nothing.
     *
     * @return void
     */
    public function testDisabledResolvesNothing(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->resolveDisplayConfig->expects($this->never())->method('forSku');

        $this->assertSame([], $this->model->execute($this->deltas(-2.0)));
    }

    /**
     * Quantity display resolves every touched SKU.
     *
     * @return void
     */
    public function testQuantityModeResolvesTouchedSku(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->quantityConfig());

        $this->assertSame([self::SKU], $this->model->execute($this->deltas(-2.0)));
    }

    /**
     * Level display skips a SKU whose level did not change.
     *
     * @return void
     */
    public function testLevelModeSkipsWhenLevelUnchanged(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 8.0, true, []));

        $this->assertSame([], $this->model->execute($this->deltas(-2.0)));
    }

    /**
     * Level display resolves a SKU whose aggregate level crossed a threshold.
     *
     * @return void
     */
    public function testLevelModeResolvesWhenAggregateLevelChanges(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 2.0, true, []));

        $this->assertSame([self::SKU], $this->model->execute($this->deltas(-5.0)));
    }

    /**
     * Level display resolves a SKU whose per-source level crossed even if the aggregate stays put.
     *
     * @return void
     */
    public function testLevelModeResolvesWhenPerSourceLevelChanges(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $view = new StockView(self::SKU, self::STOCK_ID, 8.0, true, [new SourceView('slr_a', 3.0, 'A')]);
        $this->getStockView->method('execute')->willReturn($view);

        $this->assertSame([self::SKU], $this->model->execute($this->sourceDeltas(-1.0, 'slr_a')));
    }

    /**
     * @param float $total
     * @return array<int, array<string, array{total: float, bySource: array<string, float>}>>
     */
    private function deltas(float $total): array
    {
        return [self::STOCK_ID => [self::SKU => ['total' => $total, 'bySource' => []]]];
    }

    /**
     * @param float $total
     * @param string $sourceCode
     * @return array<int, array<string, array{total: float, bySource: array<string, float>}>>
     */
    private function sourceDeltas(float $total, string $sourceCode): array
    {
        return [self::STOCK_ID => [self::SKU => ['total' => $total, 'bySource' => [$sourceCode => $total]]]];
    }

    /**
     * @return DisplayConfig
     */
    private function levelConfig(): DisplayConfig
    {
        return new DisplayConfig(Config::DISPLAY_TYPE_LEVEL, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null);
    }

    /**
     * @return DisplayConfig
     */
    private function quantityConfig(): DisplayConfig
    {
        return new DisplayConfig(Config::DISPLAY_TYPE_QUANTITY, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null);
    }
}
