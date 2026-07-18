<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;
use Magento\InventorySales\Model\SourceReservation\OversellDetectionConfig;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class OversellDetectionConfigTest extends TestCase
{
    /**
     * @var ScopeConfigInterface|MockObject
     */
    private $scopeConfig;

    /**
     * @var SourceReservationsConfig|MockObject
     */
    private $sourceReservationsConfig;

    /**
     * @var OversellDetectionConfig
     */
    private $config;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->sourceReservationsConfig = $this->createMock(SourceReservationsConfig::class);
        $this->config = new OversellDetectionConfig($this->scopeConfig, $this->sourceReservationsConfig);
    }

    public function testDetectionDisabledWhenSlrDisabled(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(false);
        $this->scopeConfig->expects(self::never())->method('isSetFlag');

        self::assertFalse($this->config->isDetectionEnabled());
    }

    public function testDetectionEnabledRequiresBothFlags(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('isSetFlag')
            ->with(OversellDetectionConfig::XML_PATH_DETECTION_ENABLED)
            ->willReturn(true);

        self::assertTrue($this->config->isDetectionEnabled());
    }

    public function testSweepDisabledWhenSlrDisabled(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(false);
        $this->scopeConfig->expects(self::never())->method('isSetFlag');

        self::assertFalse($this->config->isSweepEnabled());
    }

    public function testSweepEnabledRequiresBothFlags(): void
    {
        $this->sourceReservationsConfig->method('isEnabled')->willReturn(true);
        $this->scopeConfig->method('isSetFlag')
            ->with(OversellDetectionConfig::XML_PATH_SWEEP_ENABLED)
            ->willReturn(true);

        self::assertTrue($this->config->isSweepEnabled());
    }
}
