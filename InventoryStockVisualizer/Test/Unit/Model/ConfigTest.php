<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryStockVisualizer\Model\Config;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see Config
 */
class ConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface|MockObject
     */
    private $storeManager;

    /**
     * @var Config
     */
    private $config;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->config = new Config($this->scopeConfig, $this->storeManager);
    }

    /**
     * Store-independent callers see the panel as enabled when any store view enables it.
     *
     * @return void
     */
    public function testIsEnabledInAnyStoreChecksEveryStoreView(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            $this->createConfiguredStub(StoreInterface::class, ['getId' => 1]),
            $this->createConfiguredStub(StoreInterface::class, ['getId' => 2]),
        ]);
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            fn(string $path, string $scope, $store) => $path === Config::XML_PATH_ENABLED
                && $scope === ScopeInterface::SCOPE_STORE
                && (int)$store === 2
        );

        $this->assertFalse($this->config->isEnabled());
        $this->assertTrue($this->config->isEnabledInAnyStore());
    }

    /**
     * Disabled in every store view means nothing to purge.
     *
     * @return void
     */
    public function testIsEnabledInAnyStoreIsFalseWhenNoStoreEnablesIt(): void
    {
        $this->storeManager->method('getStores')->willReturn([
            $this->createConfiguredStub(StoreInterface::class, ['getId' => 1]),
        ]);
        $this->scopeConfig->method('isSetFlag')->willReturn(false);

        $this->assertFalse($this->config->isEnabledInAnyStore());
    }

    /**
     * Enabled reads the config flag.
     *
     * @return void
     */
    public function testIsEnabledReadsFlag(): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturn(true);
        $this->assertTrue($this->config->isEnabled());
    }

    /**
     * The grouped sets calculator reads the config flag on the store scope.
     *
     * @return void
     */
    public function testGroupedSetsCalculatorReadsFlag(): void
    {
        $this->scopeConfig->method('isSetFlag')
            ->with(Config::XML_PATH_GROUPED_SETS_CALCULATOR, ScopeInterface::SCOPE_STORE, null)
            ->willReturn(true);
        $this->assertTrue($this->config->isGroupedSetsCalculatorEnabled());
    }

    /**
     * Display type falls back to level.
     *
     * @return void
     */
    public function testDisplayTypeFallsBackToLevel(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(null);
        $this->assertSame(Config::DISPLAY_TYPE_LEVEL, $this->config->getDisplayType());
    }

    /**
     * Level thresholds are read as floats.
     *
     * @return void
     */
    public function testLevelThresholds(): void
    {
        $this->scopeConfig->method('getValue')->willReturnMap([
            [Config::XML_PATH_LEVEL_HIGH, 'store', null, '10'],
            [Config::XML_PATH_LEVEL_LOW, 'store', null, '3'],
        ]);
        $this->assertSame(10.0, $this->config->getLevelHigh());
        $this->assertSame(3.0, $this->config->getLevelLow());
    }

    /**
     * TTL is never negative.
     *
     * @return void
     */
    public function testTtlIsNeverNegative(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('-10');
        $this->assertSame(0, $this->config->getTtl());
    }
}
