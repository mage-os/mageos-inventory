<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventorySales\Model\ResourceModel\SourceReservation;

use Magento\Framework\App\ResourceConnection;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;

/**
 * Load the physical in-stock quantity of the given SKUs on the given sources.
 */
class GetSourceItemQuantityBySkusAndSources
{
    /**
     * @param ResourceConnection $resourceConnection
     */
    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    /**
     * Get in-stock source item quantities indexed by source code and SKU.
     *
     * @param string[] $skus
     * @param string[] $sourceCodes
     * @return array<string, array<string, float>> [source_code][sku] => quantity
     */
    public function execute(array $skus, array $sourceCodes): array
    {
        if (empty($skus) || empty($sourceCodes)) {
            return [];
        }

        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()
            ->from(
                $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM),
                [
                    SourceItemInterface::SOURCE_CODE,
                    SourceItemInterface::SKU,
                    SourceItemInterface::QUANTITY,
                ]
            )
            ->where(SourceItemInterface::SOURCE_CODE . ' IN (?)', $sourceCodes)
            ->where(SourceItemInterface::SKU . ' IN (?)', $skus)
            ->where(SourceItemInterface::STATUS . ' = ?', SourceItemInterface::STATUS_IN_STOCK);

        $result = [];
        foreach ($connection->fetchAll($select) as $row) {
            $result[$row[SourceItemInterface::SOURCE_CODE]][$row[SourceItemInterface::SKU]] =
                (float)$row[SourceItemInterface::QUANTITY];
        }

        return $result;
    }
}
