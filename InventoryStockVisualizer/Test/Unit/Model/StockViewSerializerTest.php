<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\Data\SourceView;
use Magento\InventoryStockVisualizer\Model\Data\StockView;
use Magento\InventoryStockVisualizer\Model\StockViewSerializer;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see StockViewSerializer
 */
class StockViewSerializerTest extends TestCase
{
    /**
     * @var Config|MockObject
     */
    private $config;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->config = $this->createMock(Config::class);
    }

    /**
     * Aggregate scope emits only the quantity.
     *
     * @return void
     */
    public function testAggregatePayload(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_AGGREGATE);
        $view = new StockView('SKU-1', 2, 15.0, true, []);

        $this->assertSame(['qty' => 15.0], (new StockViewSerializer($this->config))->serialize($view));
    }

    /**
     * Per-source scope emits a compact code => qty map with no source metadata.
     *
     * @return void
     */
    public function testPerSourcePayload(): void
    {
        $this->config->method('getScope')->willReturn(Config::SCOPE_PER_SOURCE);
        $view = new StockView('SKU-1', 2, 15.0, true, [
            new SourceView('slr_a', 5.0, 'Source A'),
            new SourceView('slr_b', 10.0, 'Source B'),
        ]);

        $this->assertSame(
            ['qty' => 15.0, 'sources' => ['slr_a' => 5.0, 'slr_b' => 10.0]],
            (new StockViewSerializer($this->config))->serialize($view)
        );
    }
}
