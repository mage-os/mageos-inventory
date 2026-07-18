<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\InventoryStockVisualizer\Model\Config;
use Magento\InventoryStockVisualizer\Model\DisplayConfig;
use Magento\InventoryStockVisualizer\Model\Level;
use Magento\InventoryStockVisualizer\Model\LevelResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @see LevelResolver
 */
class LevelResolverTest extends TestCase
{
    /**
     * @var LevelResolver
     */
    private $resolver;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new LevelResolver();
    }

    /**
     * Levels by absolute quantity thresholds.
     *
     * @param float $qty
     * @param string $expected
     * @return void
     */
    #[DataProvider('quantityProvider')]
    public function testQuantityBasis(float $qty, string $expected): void
    {
        $config = new DisplayConfig(Config::DISPLAY_TYPE_LEVEL, Config::LEVEL_BASIS_QUANTITY, 10.0, 3.0, null);
        $this->assertSame($expected, $this->resolver->resolve($qty, $config));
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function quantityProvider(): array
    {
        return [
            'zero is out' => [0.0, Level::OUT],
            'at low is low' => [3.0, Level::LOW],
            'above low is medium' => [5.0, Level::MEDIUM],
            'at high is medium' => [10.0, Level::MEDIUM],
            'above high is high' => [15.0, Level::HIGH],
        ];
    }

    /**
     * Levels by percentage of the per-product full quantity.
     *
     * @param float $qty
     * @param string $expected
     * @return void
     */
    #[DataProvider('percentageProvider')]
    public function testPercentageBasisWithFullQty(float $qty, string $expected): void
    {
        $config = new DisplayConfig(Config::DISPLAY_TYPE_LEVEL, Config::LEVEL_BASIS_PERCENTAGE, 50.0, 20.0, 20.0);
        $this->assertSame($expected, $this->resolver->resolve($qty, $config));
    }

    /**
     * @return array<string, array{0: float, 1: string}>
     */
    public static function percentageProvider(): array
    {
        return [
            '75% is high' => [15.0, Level::HIGH],
            '25% is medium' => [5.0, Level::MEDIUM],
            '10% is low' => [2.0, Level::LOW],
            '0 is out' => [0.0, Level::OUT],
        ];
    }

    /**
     * Percentage basis without a reference degrades to raw-quantity thresholds.
     *
     * @return void
     */
    public function testPercentageWithoutReferenceDegradesToQuantity(): void
    {
        $config = new DisplayConfig(Config::DISPLAY_TYPE_LEVEL, Config::LEVEL_BASIS_PERCENTAGE, 10.0, 3.0, null);
        $this->assertSame(Level::HIGH, $this->resolver->resolve(15.0, $config));
        $this->assertSame(Level::LOW, $this->resolver->resolve(2.0, $config));
    }

    /**
     * Meter fill percentage decreases with the level and is zero when out.
     *
     * @param string $level
     * @param int $expected
     * @return void
     */
    #[DataProvider('fillProvider')]
    public function testFillPercent(string $level, int $expected): void
    {
        $this->assertSame($expected, $this->resolver->fillPercent($level));
    }

    /**
     * @return array<string, array{0: string, 1: int}>
     */
    public static function fillProvider(): array
    {
        return [
            'high fills the bar' => [Level::HIGH, 100],
            'medium is partial' => [Level::MEDIUM, 60],
            'low is small' => [Level::LOW, 30],
            'out is empty' => [Level::OUT, 0],
        ];
    }
}
