<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Indexer;

use Magento\Framework\DB\Select;

/**
 * Prepare select for data provider
 *
 * @api
 */
interface SelectBuilderInterface
{
    /**
     * Prepare select based on stockId
     *
     * @param int $stockId
     * @return Select
     */
    public function execute(int $stockId): Select;
}
