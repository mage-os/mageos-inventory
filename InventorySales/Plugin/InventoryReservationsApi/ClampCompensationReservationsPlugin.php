<?php
/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySales\Plugin\InventoryReservationsApi;

use Magento\Framework\Serialize\SerializerInterface;
use Magento\InventoryReservationsApi\Model\AppendReservationsInterface;
use Magento\InventoryReservationsApi\Model\ReservationBuilderInterface;
use Magento\InventoryReservationsApi\Model\ReservationInterface;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\GetOrderReservationBalance;
use Magento\InventorySales\Model\ResourceModel\SourceReservation\ReservationClampLock;
use Psr\Log\LoggerInterface;

/**
 * Clamp compensation (positive) reservations so the cumulative balance of an
 * order per (sku, source) never becomes positive. A release that exceeds the
 * outstanding negative balance would leave a permanent positive residue (the
 * daily cleanup only deletes zero-sum groups) and inflate salable qty. The
 * excess is dropped and traced. Enforced at the single append chokepoint so it
 * covers every path (shipment, cancel, credit memo, manual compensation), in
 * both stock-scoped and source-aware modes. Rows without a resolvable order
 * increment id pass through unchanged.
 */
class ClampCompensationReservationsPlugin
{
    private const EPSILON = 0.000001;

    /**
     * @param GetOrderReservationBalance $getOrderReservationBalance
     * @param ReservationBuilderInterface $reservationBuilder
     * @param SerializerInterface $serializer
     * @param LoggerInterface $logger
     * @param ReservationClampLock $reservationClampLock
     */
    public function __construct(
        private readonly GetOrderReservationBalance $getOrderReservationBalance,
        private readonly ReservationBuilderInterface $reservationBuilder,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger,
        private readonly ReservationClampLock $reservationClampLock
    ) {
    }

    /**
     * Clamp positive reservations to the outstanding negative balance before appending.
     *
     * @param AppendReservationsInterface $subject
     * @param callable $proceed
     * @param ReservationInterface[] $reservations
     * @return void
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(AppendReservationsInterface $subject, callable $proceed, array $reservations)
    {
        $lockItems = $this->releaseItems($reservations);
        if (empty($lockItems)) {
            return $proceed($reservations);
        }

        $locks = $this->reservationClampLock->acquire($lockItems);
        try {
            $balances = $this->loadBalances($reservations);
            $consumed = [];

            $result = [];
            foreach ($reservations as $reservation) {
                $clamped = $this->clamp($reservation, $balances, $consumed);
                if ($clamped !== null) {
                    $result[] = $clamped;
                }
            }

            if (empty($result)) {
                return;
            }

            return $proceed($result);
        } finally {
            $this->reservationClampLock->release($locks);
        }
    }

    /**
     * Collect the distinct (stock, sku) of the compensation (positive) rows to lock.
     *
     * @param ReservationInterface[] $reservations
     * @return array<int, array{stock_id:int, sku:string}>
     */
    private function releaseItems(array $reservations): array
    {
        $items = [];
        foreach ($reservations as $reservation) {
            if ($reservation->getQuantity() <= 0) {
                continue;
            }
            $items[$reservation->getStockId() . '|' . $reservation->getSku()] = [
                'stock_id' => $reservation->getStockId(),
                'sku' => $reservation->getSku(),
            ];
        }

        return array_values($items);
    }

    /**
     * Load the pre-existing balance for every order group referenced by a positive row.
     *
     * @param ReservationInterface[] $reservations
     * @return array<string, array<string, array<string, float>>> groupKey => [sku][source|''] => balance
     */
    private function loadBalances(array $reservations): array
    {
        $skusByGroup = [];
        $groupContext = [];
        foreach ($reservations as $reservation) {
            if ($reservation->getQuantity() <= 0) {
                continue;
            }
            $incrementId = $this->orderIncrementId($reservation);
            if ($incrementId === null) {
                continue;
            }
            $groupKey = $incrementId . '|' . $reservation->getStockId();
            $skusByGroup[$groupKey][$reservation->getSku()] = true;
            $groupContext[$groupKey] = [$incrementId, $reservation->getStockId()];
        }

        $balances = [];
        foreach ($skusByGroup as $groupKey => $skuSet) {
            [$incrementId, $stockId] = $groupContext[$groupKey];
            $balances[$groupKey] = $this->getOrderReservationBalance->execute(
                $incrementId,
                array_keys($skuSet),
                $stockId
            );
        }

        return $balances;
    }

    /**
     * Return the reservation as-is, clamped, or null when it must be dropped.
     *
     * @param ReservationInterface $reservation
     * @param array $balances
     * @param array $consumed
     * @return ReservationInterface|null
     */
    private function clamp(
        ReservationInterface $reservation,
        array $balances,
        array &$consumed
    ): ?ReservationInterface {
        $requested = $reservation->getQuantity();
        if ($requested <= 0) {
            return $reservation;
        }
        $incrementId = $this->orderIncrementId($reservation);
        if ($incrementId === null) {
            return $reservation;
        }

        $groupKey = $incrementId . '|' . $reservation->getStockId();
        $sku = $reservation->getSku();
        $sourceKey = (string)$reservation->getSourceCode();

        $existing = $balances[$groupKey][$sku][$sourceKey] ?? 0.0;
        $allowed = max(0.0, -$existing) - ($consumed[$groupKey][$sku][$sourceKey] ?? 0.0);
        $take = min($requested, max(0.0, $allowed));

        if ($take + self::EPSILON < $requested) {
            $this->traceClamp($incrementId, $sku, $sourceKey, $requested, $take);
        }
        if ($take <= self::EPSILON) {
            return null;
        }
        $consumed[$groupKey][$sku][$sourceKey] = ($consumed[$groupKey][$sku][$sourceKey] ?? 0.0) + $take;

        if (abs($take - $requested) <= self::EPSILON) {
            return $reservation;
        }

        return $this->reservationBuilder
            ->setSku($sku)
            ->setQuantity($take)
            ->setStockId($reservation->getStockId())
            ->setMetadata($reservation->getMetadata())
            ->setSourceCode($reservation->getSourceCode())
            ->setObjectIncrementId($reservation->getObjectIncrementId())
            ->build();
    }

    /**
     * Resolve the order increment id from the column or the metadata, or null when absent.
     *
     * @param ReservationInterface $reservation
     * @return string|null
     */
    private function orderIncrementId(ReservationInterface $reservation): ?string
    {
        $incrementId = (string)($reservation->getObjectIncrementId() ?? '');
        if ($incrementId !== '') {
            return $incrementId;
        }

        $metadata = $reservation->getMetadata();
        if ($metadata === null || $metadata === '') {
            return null;
        }
        try {
            $data = $this->serializer->unserialize($metadata);
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_array($data)) {
            return null;
        }
        $incrementId = (string)($data['object_increment_id'] ?? '');

        return $incrementId === '' ? null : $incrementId;
    }

    /**
     * Log a clamped compensation.
     *
     * @param string $incrementId
     * @param string $sku
     * @param string $sourceKey
     * @param float $requested
     * @param float $granted
     * @return void
     */
    private function traceClamp(
        string $incrementId,
        string $sku,
        string $sourceKey,
        float $requested,
        float $granted
    ): void {
        $this->logger->warning(
            'Source-level reservations: clamped compensation exceeding the outstanding balance.',
            [
                'object_increment_id' => $incrementId,
                'sku' => $sku,
                'source_code' => $sourceKey === '' ? null : $sourceKey,
                'requested' => $requested,
                'granted' => $granted,
            ]
        );
    }
}
