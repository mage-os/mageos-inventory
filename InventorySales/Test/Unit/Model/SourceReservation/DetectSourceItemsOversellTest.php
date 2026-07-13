<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Magento\InventorySales\Model\SourceReservation\DetectSourceItemsOversell;
use Magento\InventorySales\Model\SourceReservation\OversellDetectionConfig;
use Magento\InventorySales\Model\SourceReservation\OversellNotifier;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DetectSourceItemsOversellTest extends TestCase
{
    /**
     * @var OversellDetectionConfig|MockObject
     */
    private $config;

    /**
     * @var GetReservationsQuantityBySkusAndSources|MockObject
     */
    private $getReservationsQuantity;

    /**
     * @var GetSourceItemQuantityBySkusAndSources|MockObject
     */
    private $getSourceItemQuantity;

    /**
     * @var OversellNotifier|MockObject
     */
    private $notifier;

    /**
     * @var DetectSourceItemsOversell
     */
    private $detect;

    protected function setUp(): void
    {
        $this->config = $this->createMock(OversellDetectionConfig::class);
        $this->getReservationsQuantity = $this->createMock(GetReservationsQuantityBySkusAndSources::class);
        $this->getSourceItemQuantity = $this->createMock(GetSourceItemQuantityBySkusAndSources::class);
        $this->notifier = $this->createMock(OversellNotifier::class);

        $this->detect = new DetectSourceItemsOversell(
            $this->config,
            $this->getReservationsQuantity,
            $this->getSourceItemQuantity,
            $this->notifier,
            $this->createMock(LoggerInterface::class)
        );
    }

    public function testDoesNothingWhenDetectionDisabled(): void
    {
        $this->config->method('isDetectionEnabled')->willReturn(false);
        $this->getReservationsQuantity->expects(self::never())->method('execute');
        $this->getSourceItemQuantity->expects(self::never())->method('execute');
        $this->notifier->expects(self::never())->method('notify');

        self::assertSame([], $this->detect->execute([['source_code' => 'src', 'sku' => 'sku-1']]));
    }

    public function testDetectsAndNotifiesOversoldPosition(): void
    {
        $this->givenDetectionEnabled();
        $this->getReservationsQuantity->method('execute')->willReturn(['src' => ['sku-1' => -5.0]]);
        $this->getSourceItemQuantity->method('execute')->willReturn(['src' => ['sku-1' => 3.0]]);
        $this->notifier->expects(self::once())->method('notify')->with(self::callback(
            static fn (array $items) => count($items) === 1
                && $items[0]['source_code'] === 'src'
                && $items[0]['sku'] === 'sku-1'
                && abs($items[0]['delta'] - (-2.0)) < 1e-9
        ));

        $result = $this->detect->execute([['source_code' => 'src', 'sku' => 'sku-1']]);

        self::assertCount(1, $result);
    }

    public function testDoesNotFlagWhenPhysicalCoversCommitted(): void
    {
        $this->givenDetectionEnabled();
        $this->getReservationsQuantity->method('execute')->willReturn(['src' => ['sku-1' => -5.0]]);
        $this->getSourceItemQuantity->method('execute')->willReturn(['src' => ['sku-1' => 5.0]]);
        $this->notifier->expects(self::never())->method('notify');

        self::assertSame([], $this->detect->execute([['source_code' => 'src', 'sku' => 'sku-1']]));
    }

    public function testTreatsMissingPhysicalAsZero(): void
    {
        $this->givenDetectionEnabled();
        $this->getReservationsQuantity->method('execute')->willReturn(['src' => ['sku-1' => -2.0]]);
        $this->getSourceItemQuantity->method('execute')->willReturn([]);
        $this->notifier->expects(self::once())->method('notify');

        $result = $this->detect->execute([['source_code' => 'src', 'sku' => 'sku-1']]);

        self::assertCount(1, $result);
        self::assertSame(0.0, $result[0]['physical']);
    }

    public function testSkipsPairsWithoutSourceOrSku(): void
    {
        $this->givenDetectionEnabled();
        $this->getReservationsQuantity->expects(self::never())->method('execute');
        $this->notifier->expects(self::never())->method('notify');

        self::assertSame([], $this->detect->execute([
            ['source_code' => '', 'sku' => 'sku-1'],
            ['source_code' => 'src', 'sku' => ''],
            ['source_code' => null, 'sku' => 'sku-2'],
        ]));
    }

    public function testSwallowsFailuresSoItNeverBreaksTheWrite(): void
    {
        $this->givenDetectionEnabled();
        $this->getReservationsQuantity->method('execute')->willThrowException(new \RuntimeException('boom'));
        $this->notifier->expects(self::never())->method('notify');

        self::assertSame([], $this->detect->execute([['source_code' => 'src', 'sku' => 'sku-1']]));
    }

    private function givenDetectionEnabled(): void
    {
        $this->config->method('isDetectionEnabled')->willReturn(true);
    }
}
