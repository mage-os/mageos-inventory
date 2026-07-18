<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Console\Command;

use Magento\InventorySales\Console\Command\DetectOversellCommand;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOversoldSourceItems;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class DetectOversellCommandTest extends TestCase
{
    /**
     * @var GetOversoldSourceItems|MockObject
     */
    private $getOversoldSourceItems;

    /**
     * @var CommandTester
     */
    private $tester;

    protected function setUp(): void
    {
        $this->getOversoldSourceItems = $this->createMock(GetOversoldSourceItems::class);
        $this->tester = new CommandTester(new DetectOversellCommand($this->getOversoldSourceItems));
    }

    public function testReturnsSuccessWhenNoOversold(): void
    {
        $this->getOversoldSourceItems->method('execute')->willReturn([]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::SUCCESS, $exit);
        self::assertStringContainsString('No oversold source positions found', $this->tester->getDisplay());
    }

    public function testReturnsFailureAndReportsRows(): void
    {
        $this->getOversoldSourceItems->method('execute')->willReturn([
            ['source_code' => 'src-a', 'sku' => 'sku-1', 'physical' => 2.0, 'reserved' => -5.0, 'delta' => -3.0],
        ]);

        $exit = $this->tester->execute([]);

        self::assertSame(Command::FAILURE, $exit);
        $display = $this->tester->getDisplay();
        self::assertStringContainsString('src-a', $display);
        self::assertStringContainsString('sku-1', $display);
    }

    public function testFiltersBySource(): void
    {
        $this->getOversoldSourceItems->method('execute')->willReturn([
            ['source_code' => 'src-a', 'sku' => 'sku-1', 'physical' => 2.0, 'reserved' => -5.0, 'delta' => -3.0],
            ['source_code' => 'src-b', 'sku' => 'sku-2', 'physical' => 0.0, 'reserved' => -1.0, 'delta' => -1.0],
        ]);

        $this->tester->execute(['--source' => 'src-b']);
        $display = $this->tester->getDisplay();

        self::assertStringContainsString('src-b', $display);
        self::assertStringNotContainsString('src-a', $display);
    }
}
