<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Cron;

use Magento\InventorySales\Cron\DetectOversell;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOversoldSourceItems;
use Magento\InventorySales\Model\SourceReservation\OversellDetectionConfig;
use Magento\InventorySales\Model\SourceReservation\OversellNotifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DetectOversellTest extends TestCase
{
    /**
     * @var OversellDetectionConfig|MockObject
     */
    private $config;

    /**
     * @var GetOversoldSourceItems|MockObject
     */
    private $getOversoldSourceItems;

    /**
     * @var OversellNotifier|MockObject
     */
    private $notifier;

    /**
     * @var DetectOversell
     */
    private $cron;

    protected function setUp(): void
    {
        $this->config = $this->createMock(OversellDetectionConfig::class);
        $this->getOversoldSourceItems = $this->createMock(GetOversoldSourceItems::class);
        $this->notifier = $this->createMock(OversellNotifier::class);
        $this->cron = new DetectOversell(
            $this->config,
            $this->getOversoldSourceItems,
            $this->notifier,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testDoesNothingWhenSweepDisabled(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(false);
        $this->getOversoldSourceItems->expects(self::never())->method('execute');
        $this->notifier->expects(self::never())->method('notify');

        $this->cron->execute();
    }

    public function testNotifiesWhenOversoldFound(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(true);
        $rows = [['source_code' => 'src', 'sku' => 'sku-1', 'physical' => 0.0, 'reserved' => -2.0, 'delta' => -2.0]];
        $this->getOversoldSourceItems->method('execute')->willReturn($rows);
        $this->notifier->expects(self::once())->method('notify')->with($rows);

        $this->cron->execute();
    }

    public function testDoesNotNotifyWhenNoneFound(): void
    {
        $this->config->method('isSweepEnabled')->willReturn(true);
        $this->getOversoldSourceItems->method('execute')->willReturn([]);
        $this->notifier->expects(self::never())->method('notify');

        $this->cron->execute();
    }
}
