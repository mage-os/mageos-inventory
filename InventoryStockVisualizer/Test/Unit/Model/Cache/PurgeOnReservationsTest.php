<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model\Cache;

use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventoryStockVisualizer\Api\GetStockViewInterface;
use Magento\InventoryStockVisualizer\Model\Cache\FlushStockVisualizerCache;
use Magento\InventoryStockVisualizer\Model\Cache\PurgeOnReservations;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\SourceView;
use Magento\InventoryStockVisualizer\Model\Data\StockView;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use Magento\InventoryStockVisualizer\Model\ResolveDisplayConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see PurgeOnReservations
 */
class PurgeOnReservationsTest extends TestCase
{
    private const SKU = 'SLR-1';
    private const STOCK_ID = 10;
    private const PRODUCT_ID = 42;

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
     * @var GetProductIdsBySkusInterface|MockObject
     */
    private $getProductIdsBySkus;

    /**
     * @var FlushStockVisualizerCache|MockObject
     */
    private $flush;

    /**
     * @var PurgeOnReservations
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
        $this->getProductIdsBySkus = $this->createMock(GetProductIdsBySkusInterface::class);
        $this->flush = $this->createMock(FlushStockVisualizerCache::class);

        $this->model = new PurgeOnReservations(
            $this->config,
            $this->resolveDisplayConfig,
            new LevelResolver(),
            $this->getStockView,
            $this->getProductIdsBySkus,
            $this->flush
        );
    }

    /**
     * Disabled feature never purges.
     *
     * @return void
     */
    public function testDisabledDoesNotPurge(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->flush->expects($this->never())->method('execute');

        $this->model->execute([$this->reservation(-2.0)]);
    }

    /**
     * Quantity display purges every touched SKU.
     *
     * @return void
     */
    public function testQuantityModePurgesTouchedSku(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->quantityConfig());
        $this->getProductIdsBySkus->method('execute')->willReturn([self::SKU => self::PRODUCT_ID]);
        $this->flush->expects($this->once())->method('execute')->with([self::PRODUCT_ID]);

        $this->model->execute([$this->reservation(-2.0)]);
    }

    /**
     * Level display does not purge when the level is unchanged.
     *
     * @return void
     */
    public function testLevelModeSkipsWhenLevelUnchanged(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 8.0, true, []));
        $this->flush->expects($this->never())->method('execute');

        $this->model->execute([$this->reservation(-2.0)]);
    }

    /**
     * Level display purges when the aggregate level changes.
     *
     * @return void
     */
    public function testLevelModePurgesWhenLevelChanges(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $this->getStockView->method('execute')->willReturn(new StockView(self::SKU, self::STOCK_ID, 2.0, true, []));
        $this->getProductIdsBySkus->method('execute')->willReturn([self::SKU => self::PRODUCT_ID]);
        $this->flush->expects($this->once())->method('execute')->with([self::PRODUCT_ID]);

        $this->model->execute([$this->reservation(-5.0)]);
    }

    /**
     * Level display purges when a per-source level crosses even if the aggregate stays put.
     *
     * @return void
     */
    public function testLevelModePurgesWhenPerSourceLevelChanges(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->resolveDisplayConfig->method('forSku')->willReturn($this->levelConfig());
        $view = new StockView(self::SKU, self::STOCK_ID, 8.0, true, [new SourceView('slr_a', 3.0, 'A')]);
        $this->getStockView->method('execute')->willReturn($view);
        $this->getProductIdsBySkus->method('execute')->willReturn([self::SKU => self::PRODUCT_ID]);
        $this->flush->expects($this->once())->method('execute')->with([self::PRODUCT_ID]);

        $this->model->execute([$this->sourceReservation(-1.0, 'slr_a')]);
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

    /**
     * @param float $quantity
     * @return ReservationInterface|MockObject
     */
    private function reservation(float $quantity)
    {
        return $this->buildReservation($quantity, null);
    }

    /**
     * @param float $quantity
     * @param string $sourceCode
     * @return ReservationInterface|MockObject
     */
    private function sourceReservation(float $quantity, string $sourceCode)
    {
        return $this->buildReservation($quantity, $sourceCode);
    }

    /**
     * @param float $quantity
     * @param string|null $sourceCode
     * @return ReservationInterface|MockObject
     */
    private function buildReservation(float $quantity, ?string $sourceCode)
    {
        $reservation = $this->createMock(ReservationInterface::class);
        $reservation->method('getQuantity')->willReturn($quantity);
        $reservation->method('getStockId')->willReturn(self::STOCK_ID);
        $reservation->method('getSku')->willReturn(self::SKU);
        $reservation->method('getSourceCode')->willReturn($sourceCode);

        return $reservation;
    }
}
