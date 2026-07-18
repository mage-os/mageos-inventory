<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Test\Unit\Model\SourceReservation;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetPendingSourceReservations;
use Magento\InventorySales\Model\SourceReservation\DistributeCompensationToSources;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class DistributeCompensationToSourcesTest extends TestCase
{
    private const STOCK_ID = 10;
    private const INCREMENT_ID = '000000123';

    /**
     * @var GetPendingSourceReservations|MockObject
     */
    private $getPendingSourceReservations;

    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSourcesAssignedToStockOrderedByPriority;

    /**
     * @var DistributeCompensationToSources
     */
    private $model;

    protected function setUp(): void
    {
        $this->getPendingSourceReservations = $this->createMock(GetPendingSourceReservations::class);
        $this->getSourcesAssignedToStockOrderedByPriority = $this->createMock(
            GetSourcesAssignedToStockOrderedByPriorityInterface::class
        );

        $this->model = new DistributeCompensationToSources(
            $this->getPendingSourceReservations,
            $this->getSourcesAssignedToStockOrderedByPriority
        );
    }

    public function testCompensatesTheSourceHoldingTheOutstandingBalance(): void
    {
        $this->givenPriorityOrder(['source-a', 'source-b']);
        $this->givenPendingBalances(['sku-1' => ['source-a' => -5.0]]);

        $result = $this->model->execute(['sku-1' => 5.0], self::STOCK_ID, self::INCREMENT_ID);

        self::assertSame(
            ['sku-1' => [['source_code' => 'source-a', 'quantity' => 5.0]]],
            $result
        );
    }

    public function testPartialCompensationLeavesResidualBalance(): void
    {
        $this->givenPriorityOrder(['source-a']);
        $this->givenPendingBalances(['sku-1' => ['source-a' => -5.0]]);

        $result = $this->model->execute(['sku-1' => 4.0], self::STOCK_ID, self::INCREMENT_ID);

        self::assertSame(
            ['sku-1' => [['source_code' => 'source-a', 'quantity' => 4.0]]],
            $result
        );
    }

    public function testConsumesBalancesInStockPriorityOrder(): void
    {
        $this->givenPriorityOrder(['source-a', 'source-b']);
        $this->givenPendingBalances(['sku-1' => ['source-b' => -3.0, 'source-a' => -5.0]]);

        $result = $this->model->execute(['sku-1' => 6.0], self::STOCK_ID, self::INCREMENT_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-a', 'quantity' => 5.0],
                    ['source_code' => 'source-b', 'quantity' => 1.0],
                ],
            ],
            $result
        );
    }

    public function testOverCompensationFallsBackToNullSource(): void
    {
        $this->givenPriorityOrder(['source-a']);
        $this->givenPendingBalances(['sku-1' => ['source-a' => -5.0]]);

        $result = $this->model->execute(['sku-1' => 8.0], self::STOCK_ID, self::INCREMENT_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-a', 'quantity' => 5.0],
                    ['source_code' => null, 'quantity' => 3.0],
                ],
            ],
            $result
        );
    }

    public function testFallsBackToNullSourceWhenThereIsNoPendingAllocation(): void
    {
        $this->givenPriorityOrder(['source-a']);
        $this->givenPendingBalances([]);

        $result = $this->model->execute(['sku-1' => 5.0], self::STOCK_ID, self::INCREMENT_ID);

        self::assertSame(
            ['sku-1' => [['source_code' => null, 'quantity' => 5.0]]],
            $result
        );
    }

    public function testIgnoresStockScopedAndPositiveBalances(): void
    {
        $this->givenPriorityOrder(['source-a', 'source-b']);
        $this->givenPendingBalances(
            ['sku-1' => ['' => -2.0, 'source-a' => 4.0, 'source-b' => -3.0]]
        );

        $result = $this->model->execute(['sku-1' => 5.0], self::STOCK_ID, self::INCREMENT_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-b', 'quantity' => 3.0],
                    ['source_code' => null, 'quantity' => 2.0],
                ],
            ],
            $result
        );
    }

    public function testOrdersSourcesUnassignedFromTheStockAfterAssignedOnesByCode(): void
    {
        $this->givenPriorityOrder(['source-c']);
        $this->givenPendingBalances(
            ['sku-1' => ['source-b' => -1.0, 'source-a' => -1.0, 'source-c' => -1.0]]
        );

        $result = $this->model->execute(['sku-1' => 3.0], self::STOCK_ID, self::INCREMENT_ID);

        self::assertSame(
            [
                'sku-1' => [
                    ['source_code' => 'source-c', 'quantity' => 1.0],
                    ['source_code' => 'source-a', 'quantity' => 1.0],
                    ['source_code' => 'source-b', 'quantity' => 1.0],
                ],
            ],
            $result
        );
    }

    public function testReturnsEmptyResultForEmptyInput(): void
    {
        $this->getPendingSourceReservations->expects(self::never())->method('execute');

        self::assertSame([], $this->model->execute([], self::STOCK_ID, self::INCREMENT_ID));
    }

    /**
     * @param string[] $sourceCodes ordered by priority
     */
    private function givenPriorityOrder(array $sourceCodes): void
    {
        $sources = [];
        foreach ($sourceCodes as $sourceCode) {
            $source = $this->createMock(SourceInterface::class);
            $source->method('getSourceCode')->willReturn($sourceCode);
            $sources[] = $source;
        }
        $this->getSourcesAssignedToStockOrderedByPriority
            ->method('execute')
            ->with(self::STOCK_ID)
            ->willReturn($sources);
    }

    /**
     * @param array<string, array<string, float>> $balances
     */
    private function givenPendingBalances(array $balances): void
    {
        $this->getPendingSourceReservations
            ->method('execute')
            ->with(self::INCREMENT_ID, self::anything(), self::STOCK_ID)
            ->willReturn($balances);
    }
}
