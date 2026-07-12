<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventoryReservationsApi\Model\CleanupReservationsInterface;

/**
 * @inheritdoc
 */
class CleanupReservations implements CleanupReservationsInterface
{
    private const DELETE_CHUNK_SIZE = 10000;

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var int
     */
    private $groupConcatMaxLen;

    /**
     * @param ResourceConnection $resource
     * @param int $groupConcatMaxLen
     */
    public function __construct(
        ResourceConnection $resource,
        int $groupConcatMaxLen
    ) {
        $this->resource = $resource;
        $this->groupConcatMaxLen = $groupConcatMaxLen;
    }

    /**
     * @inheritdoc
     */
    public function execute(): void
    {
        $groupedReservationIds = array_unique(
            array_merge(
                $this->getReservationIdsByField('object_id'),
                $this->getReservationIdsByField('object_increment_id')
            )
        );

        $connection = $this->resource->getConnection();
        $seenIds = [];
        $chunk = [];
        foreach ($groupedReservationIds as $groupedIds) {
            $groupIds = [];
            foreach (explode(',', (string)$groupedIds) as $reservationId) {
                $reservationId = (int)$reservationId;
                if ($reservationId && !isset($seenIds[$reservationId])) {
                    $seenIds[$reservationId] = true;
                    $groupIds[] = $reservationId;
                }
            }
            if (!$groupIds) {
                continue;
            }
            if ($chunk && count($chunk) + count($groupIds) > self::DELETE_CHUNK_SIZE) {
                $this->deleteReservations($connection, $chunk);
                $chunk = [];
            }
            array_push($chunk, ...$groupIds);
        }
        if ($chunk) {
            $this->deleteReservations($connection, $chunk);
        }
    }

    /**
     * Delete reservations by ids.
     *
     * @param AdapterInterface $connection
     * @param int[] $reservationIds
     * @return void
     */
    private function deleteReservations(AdapterInterface $connection, array $reservationIds): void
    {
        $connection->delete(
            $this->resource->getTableName('inventory_reservation'),
            [ReservationInterface::RESERVATION_ID . ' IN (?)' => $reservationIds]
        );
    }

    /**
     * Returns reservation ids by specified field.
     *
     * @param string $field
     * @return array
     */
    private function getReservationIdsByField(string $field) : array
    {
        $connection = $this->resource->getConnection();
        $reservationTable = $this->resource->getTableName('inventory_reservation');
        $select = $connection->select()
            ->from(
                $reservationTable,
                ['GROUP_CONCAT(' . ReservationInterface::RESERVATION_ID . ')']
            )
            ->group(
                "JSON_EXTRACT(metadata, '$.$field')",
                "JSON_EXTRACT(metadata, '$.object_type')",
                ReservationInterface::SOURCE_CODE
            )
            ->having('SUM(' . ReservationInterface::QUANTITY . ') = 0');
        $connection->query('SET group_concat_max_len = ' . $this->groupConcatMaxLen);
        return $connection->fetchCol($select);
    }
}
