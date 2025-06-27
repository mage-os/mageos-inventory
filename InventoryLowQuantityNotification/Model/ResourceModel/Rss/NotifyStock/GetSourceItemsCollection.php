<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryLowQuantityNotification\Model\ResourceModel\Rss\NotifyStock;

use Magento\Inventory\Model\ResourceModel\SourceItem\Collection;
use Magento\Inventory\Model\ResourceModel\SourceItem\CollectionFactory;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * Get source items collection for notify stock.
 */
class GetSourceItemsCollection
{
    /**
     * @var CollectionFactory
     */
    private $collectionFactory;

    /**
     * @var SelectBuilder
     */
    private $selectBuilder;

    /**
     * @param CollectionFactory $collectionFactory
     * @param SelectBuilder $selectBuilder
     */
    public function __construct(
        CollectionFactory $collectionFactory,
        SelectBuilder $selectBuilder
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->selectBuilder = $selectBuilder;
    }

    /**
     * Builds a source item collection with selected fields and applies custom SQL modifications using a select builder.
     *
     * @return Collection
     */
    public function execute()
    {
        $collection = $this->collectionFactory->create();
        $collection->removeAllFieldsFromSelect();
        $collection->addFieldToSelect(SourceItemInterface::SOURCE_CODE);
        $collection->addFieldToSelect(SourceItemInterface::QUANTITY, 'qty');

        $this->selectBuilder->build($collection->getSelect());

        return $collection;
    }
}
