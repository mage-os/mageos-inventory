<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetReservationsQuantityBySkusAndSources;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetSourceItemQuantityBySkusAndSources;
use Magento\InventorySales\Model\SourceReservation\AllocateItemsToSources;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AllocateItemsToSourcesTest extends TestCase
{
    private const STOCK_ID = 10;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSourcesAssignedToStockOrderedByPriority;

    /**
     * @var GetSourceItemQuantityBySkusAndSources|MockObject
     */
    private $getSourceItemQuantityBySkusAndSources;

    /**
     * @var GetReservationsQuantityBySkusAndSources|MockObject
     */
    private $getReservationsQuantityBySkusAndSources;

    /**
     * @var AllocateItemsToSources
     */
    private $model;

    protected function setUp(): void
    {
        $this->getSourcesAssignedToStockOrderedByPriority = $this->createMock(
            GetSourcesAssignedToStockOrderedByPriorityInterface::class
        );
        $this->getSourceItemQuantityBySkusAndSources = $this->createMock(
            GetSourceItemQuantityBySkusAndSources::class
        );
        $this->getReservationsQuantityBySkusAndSources = $this->createMock(
            GetReservationsQuantityBySkusAndSources::class
        );

        $this->model = new AllocateItemsToSources(
            $this->getSourcesAssignedToStockOrderedByPriority,
            $this->getSourceItemQuantityBySkusAndSources,
            $this->getReservationsQuantityBySkusAndSources
        );
    }

    public function testAllocatesToFirstSourceWhenItCoversTheDemand(): void
    {
        $this->givenSources(['source-a' => true, 'source-b' => true]);
        $this->givenPhysicalQuantities(['source-a' => ['sku-1' => 10.0], 'source-b' => ['sku-1' => 10.0]]);
        $this->givenReservationQuantities([]);

        $result = $this->model->execute(['sku-1' => 7.0], self::STOCK_ID);

        self::assertSame(
            ['sku-1' => [['source_code' => 'source-a', 'quantity' => 7.0]]],
            $result
        );
    }

    public function testSpillsOverToNextSourceInPriorityOrder(): void
    {
        $this->givenSources(['source-a' => true, 'source-b' => true]);
        $this->givenPhysicalQuantities(['source-a' => ['sku-1' => 5.0], 'source-b' => ['sku-1' => 10.0]]);
        $this->givenReservationQuantities([]);

        $result = $this->model->execute(['sku-1' => 7.0], self::STOCK_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-a', 'quantity' => 5.0],
                    ['source_code' => 'source-b', 'quantity' => 2.0],
                ],
            ],
            $result
        );
    }

    public function testAvailabilityAccountsForExistingReservations(): void
    {
        $this->givenSources(['source-a' => true, 'source-b' => true]);
        $this->givenPhysicalQuantities(['source-a' => ['sku-1' => 5.0], 'source-b' => ['sku-1' => 10.0]]);
        $this->givenReservationQuantities(['source-a' => ['sku-1' => -3.0]]);

        $result = $this->model->execute(['sku-1' => 7.0], self::STOCK_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-a', 'quantity' => 2.0],
                    ['source_code' => 'source-b', 'quantity' => 5.0],
                ],
            ],
            $result
        );
    }

    public function testLastSourceAbsorbsTheRemainderWhenDemandExceedsAvailability(): void
    {
        $this->givenSources(['source-a' => true, 'source-b' => true]);
        $this->givenPhysicalQuantities(['source-a' => ['sku-1' => 5.0], 'source-b' => ['sku-1' => 2.0]]);
        $this->givenReservationQuantities([]);

        $result = $this->model->execute(['sku-1' => 10.0], self::STOCK_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-a', 'quantity' => 5.0],
                    ['source_code' => 'source-b', 'quantity' => 5.0],
                ],
            ],
            $result
        );
    }

    public function testFallsBackToNullSourceWhenStockHasNoEnabledSources(): void
    {
        $this->givenSources(['source-a' => false]);

        $result = $this->model->execute(['sku-1' => 7.0], self::STOCK_ID);

        self::assertSame(
            ['sku-1' => [['source_code' => null, 'quantity' => 7.0]]],
            $result
        );
    }

    public function testSkipsDisabledSources(): void
    {
        $this->givenSources(['source-a' => false, 'source-b' => true]);
        $this->givenPhysicalQuantities(['source-a' => ['sku-1' => 100.0], 'source-b' => ['sku-1' => 1.0]]);
        $this->givenReservationQuantities([]);

        $result = $this->model->execute(['sku-1' => 7.0], self::STOCK_ID);

        self::assertSame(
            ['sku-1' => [['source_code' => 'source-b', 'quantity' => 7.0]]],
            $result
        );
    }

    public function testAllocatesMultipleSkusIndependently(): void
    {
        $this->givenSources(['source-a' => true, 'source-b' => true]);
        $this->givenPhysicalQuantities([
            'source-a' => ['sku-1' => 5.0],
            'source-b' => ['sku-1' => 10.0, 'sku-2' => 4.0],
        ]);
        $this->givenReservationQuantities([]);

        $result = $this->model->execute(['sku-1' => 7.0, 'sku-2' => 3.0], self::STOCK_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-a', 'quantity' => 5.0],
                    ['source_code' => 'source-b', 'quantity' => 2.0],
                ],
                'sku-2' => [
                    ['source_code' => 'source-b', 'quantity' => 3.0],
                ],
            ],
            $result
        );
    }

    public function testReturnsEmptyResultForEmptyInput(): void
    {
        $this->getSourcesAssignedToStockOrderedByPriority->expects(self::never())->method('execute');

        self::assertSame([], $this->model->execute([], self::STOCK_ID));
    }

    /**
     * @param array<string, bool> $sourceCodesToEnabled ordered by priority
     */
    private function givenSources(array $sourceCodesToEnabled): void
    {
        $sources = [];
        foreach ($sourceCodesToEnabled as $sourceCode => $enabled) {
            $source = $this->createMock(SourceInterface::class);
            $source->method('getSourceCode')->willReturn($sourceCode);
            $source->method('isEnabled')->willReturn($enabled);
            $sources[] = $source;
        }
        $this->getSourcesAssignedToStockOrderedByPriority
            ->method('execute')
            ->with(self::STOCK_ID)
            ->willReturn($sources);
    }

    /**
     * @param array<string, array<string, float>> $quantities
     */
    private function givenPhysicalQuantities(array $quantities): void
    {
        $this->getSourceItemQuantityBySkusAndSources->method('execute')->willReturn($quantities);
    }

    /**
     * @param array<string, array<string, float>> $quantities
     */
    private function givenReservationQuantities(array $quantities): void
    {
        $this->getReservationsQuantityBySkusAndSources->method('execute')->willReturn($quantities);
    }
}
