<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Test\Unit\Model;

use Magento\InventoryApi\Api\Data\SourceInterface;
use Magento\InventoryApi\Api\GetSourcesAssignedToStockOrderedByPriorityInterface;
use Magento\InventoryStockVisualizer\Model\GetEnabledSources;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * @see GetEnabledSources
 */
class GetEnabledSourcesTest extends TestCase
{
    /**
     * @var GetSourcesAssignedToStockOrderedByPriorityInterface|MockObject
     */
    private $getSourcesAssignedToStock;

    /**
     * @var GetEnabledSources
     */
    private $model;

    /**
     * @inheritdoc
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->getSourcesAssignedToStock = $this->createMock(
            GetSourcesAssignedToStockOrderedByPriorityInterface::class
        );
        $this->model = new GetEnabledSources($this->getSourcesAssignedToStock);
    }

    /**
     * Only enabled sources are returned, with the code as name fallback.
     *
     * @return void
     */
    public function testReturnsEnabledSourcesWithNameFallback(): void
    {
        $this->getSourcesAssignedToStock->method('execute')->willReturn([
            $this->source('slr_a', 'Source A', true),
            $this->source('slr_b', null, true),
            $this->source('slr_c', 'Disabled', false),
        ]);

        $this->assertSame(
            [
                ['code' => 'slr_a', 'name' => 'Source A'],
                ['code' => 'slr_b', 'name' => 'slr_b'],
            ],
            $this->model->execute(2)
        );
    }

    /**
     * @param string $code
     * @param string|null $name
     * @param bool $enabled
     * @return SourceInterface|MockObject
     */
    private function source(string $code, ?string $name, bool $enabled)
    {
        $source = $this->createMock(SourceInterface::class);
        $source->method('getSourceCode')->willReturn($code);
        $source->method('getName')->willReturn($name);
        $source->method('isEnabled')->willReturn($enabled);

        return $source;
    }
}
