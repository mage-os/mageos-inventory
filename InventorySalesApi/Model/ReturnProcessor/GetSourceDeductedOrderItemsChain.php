<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesApi\Model\ReturnProcessor;

use Magento\Sales\Api\Data\OrderInterface;
use Magento\InventorySalesApi\Model\ReturnProcessor\GetSourceDeductedOrderItemsInterface;
use Magento\InventorySalesApi\Model\ReturnProcessor\Result\SourceDeductedOrderItemsResultFactory;
use Magento\Framework\Exception\InputException;

class GetSourceDeductedOrderItemsChain implements GetSourceDeductedOrderItemsInterface
{
    /**
     * @var array
     */
    private $sourceDeductedItemsSelector;

    /**
     * @var SourceDeductedOrderItemsResultFactory
     */
    private $sourceDeductedOrderItemsResultFactory;

    /**
     * @param SourceDeductedOrderItemsResultFactory $sourceDeductedOrderItemsResultFactory
     * @param array $sourceDeductedItemsSelector
     * @throws InputException
     */
    public function __construct(
        SourceDeductedOrderItemsResultFactory $sourceDeductedOrderItemsResultFactory,
        array $sourceDeductedItemsSelector = []
    ) {
        foreach ($sourceDeductedItemsSelector as $selector) {
            if (!$selector instanceof GetSourceDeductedOrderItemsInterface) {
                throw new InputException(
                    __('%1 doesn\'t implement GetSourceDeductedOrderItemsInterface', get_class($selector))
                );
            }
        }
        $this->sourceDeductedItemsSelector = $sourceDeductedItemsSelector;
        $this->sourceDeductedOrderItemsResultFactory = $sourceDeductedOrderItemsResultFactory;
    }

    /**
     * @inheritdoc
     */
    public function execute(OrderInterface $order, array $returnToStockItems): array
    {
        $sourceDeductedItems = [];
        foreach ($this->sourceDeductedItemsSelector as $selector) {
            $resultItems = $selector->execute($order, $returnToStockItems);
            if (!empty($resultItems)) {
                $sourceDeductedItems[] = $resultItems;
            }
        }

        return $this->groupResultBySourceCode($sourceDeductedItems);
    }

    /**
     * Groups source deducted items by source code and creates result objects for each source with its items.
     *
     * @param array $sourceDeductedItems
     * @return array
     */
    private function groupResultBySourceCode(array $sourceDeductedItems): array
    {
        $groupedItems = $result = [];
        foreach ($sourceDeductedItems as $resultItems) {
            foreach ($resultItems as $resultItem) {
                $sourceCode = $resultItem->getSourceCode();
                if (!isset($groupedItems[$sourceCode])) {
                    $groupedItems[$sourceCode] = [];
                }
                $groupedItems[$sourceCode] = [...$groupedItems[$sourceCode], ...$resultItem->getItems()];
            }
        }

        foreach ($groupedItems as $sourceCode => $items) {
            $result[] = $this->sourceDeductedOrderItemsResultFactory->create([
                'sourceCode' => $sourceCode,
                'items' => $items
            ]);
        }

        return $result;
    }
}
