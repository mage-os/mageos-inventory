<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Setup\Patch\Schema;

use Magento\Framework\Setup\Patch\SchemaPatchInterface;
use Magento\InventoryCatalog\Setup\Operation\ReindexDefaultStock as Operation;

/**
 * Patch Inventory Source Items with Inventory Stock Item Data
 */
class ReindexDefaultSource implements SchemaPatchInterface
{
    /**
     * @var Operation
     */
    private $operation;

    /**
     * @param Operation $operation
     */
    public function __construct(Operation $operation)
    {
        $this->operation = $operation;
    }

    /**
     * @inheritDoc
     */
    public function apply()
    {
        $this->operation->execute();

        return $this;
    }

    /**
     * @inheritDoc
     */
    public static function getDependencies()
    {
        return [
            UpdateInventorySourceItem::class
        ];
    }

    /**
     * @inheritDoc
     */
    public function getAliases()
    {
        return [];
    }
}
