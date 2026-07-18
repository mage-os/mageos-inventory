<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryStockVisualizer\Model\Config;
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
        $this->config = new Config($this->scopeConfig);
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
