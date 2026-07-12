<?php
/**
 * Copyright 2021 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryIndexer\Indexer\Stock;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Select;
use Magento\InventoryReservationsApi\Model\SourceReservationsConfig;

class PrepareReservationsIndexData
{
    /**
     * @var ResourceConnection
     */
    private $resourceConnection;

    /**
     * @var ReservationsIndexTable
     */
    private $reservationsIndexTable;

    /**
     * @param ResourceConnection $resourceConnection
     * @param ReservationsIndexTable $reservationsIndexTable
     * @param SourceReservationsConfig $sourceReservationsConfig
     */
    public function __construct(
        ResourceConnection $resourceConnection,
        ReservationsIndexTable $reservationsIndexTable,
        private readonly SourceReservationsConfig $sourceReservationsConfig
    ) {
        $this->resourceConnection = $resourceConnection;
        $this->reservationsIndexTable = $reservationsIndexTable;
    }

    /**
     * Prepare reservation index data.
     *
     * @param int $stockId
     * @return void
     */
    public function execute(int $stockId): void
    {
        $connection = $this->resourceConnection->getConnection();
        $reservationsData = $this->sourceReservationsConfig->isEnabled()
            ? $this->getSourceAggregatedSelect($stockId)
            : $this->getStockScopedSelect($stockId);

        $insertFromSelect = $connection->insertFromSelect(
            $reservationsData,
            $this->reservationsIndexTable->getTableName($stockId)
        );
        $connection->query($insertFromSelect);
    }

    /**
     * Build the legacy select aggregating reservations by stock id.
     *
     * @param int $stockId
     * @return Select
     */
    private function getStockScopedSelect(int $stockId): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $reservationsData = $connection->select();
        $reservationsData->from(
            ['reservations' => $this->resourceConnection->getTableName('inventory_reservation')],
            [
                'sku',
                'reservation_qty' => 'SUM(reservations.quantity)'
            ]
        );
        $reservationsData->where('stock_id = ?', $stockId);
        $reservationsData->group(['sku', 'stock_id']);

        return $reservationsData;
    }

    /**
     * Build the select combining stock-scoped rows and rows of the sources linked to the stock.
     *
     * @param int $stockId
     * @return Select
     */
    private function getSourceAggregatedSelect(int $stockId): Select
    {
        $connection = $this->resourceConnection->getConnection();
        $reservationTable = $this->resourceConnection->getTableName('inventory_reservation');

        $stockScopedSelect = $connection->select()
            ->from($reservationTable, ['sku', 'quantity'])
            ->where('stock_id = ?', $stockId)
            ->where('source_code IS NULL');

        $sourceScopedSelect = $connection->select()
            ->from(
                ['reservation' => $reservationTable],
                ['sku', 'quantity']
            )
            ->joinInner(
                ['stock_source_link' => $this->resourceConnection->getTableName('inventory_source_stock_link')],
                'stock_source_link.source_code = reservation.source_code',
                []
            )
            ->joinInner(
                ['source' => $this->resourceConnection->getTableName('inventory_source')],
                'source.source_code = stock_source_link.source_code',
                []
            )
            ->where('stock_source_link.stock_id = ?', $stockId)
            ->where('source.enabled = ?', 1);

        $unionSelect = $connection->select()->union(
            [$stockScopedSelect, $sourceScopedSelect],
            Select::SQL_UNION_ALL
        );

        return $connection->select()
            ->from(
                ['reservations' => $unionSelect],
                [
                    'sku',
                    'reservation_qty' => 'SUM(reservations.quantity)'
                ]
            )
            ->group('sku');
    }
}
