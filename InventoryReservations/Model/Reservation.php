<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryReservations\Model;

use Magento\InventoryReservationsApi\Model\ReservationInterface;

/**
 * {@inheritdoc}
 *
 * @codeCoverageIgnore
 */
class Reservation implements ReservationInterface
{
    /**
     * @var int|null
     */
    private $reservationId;

    /**
     * @var int
     */
    private $stockId;

    /**
     * @var string
     */
    private $sku;

    /**
     * @var float
     */
    private $quantity;

    /**
     * @var string|null
     */
    private $metadata;

    /**
     * @var string|null
     */
    private $sourceCode;

    /**
     * @var string|null
     */
    private $objectIncrementId;

    /**
     * @param int|null $reservationId
     * @param int $stockId
     * @param string $sku
     * @param float $quantity
     * @param string|null $metadata
     * @param string|null $sourceCode
     * @param string|null $objectIncrementId
     */
    public function __construct(
        $reservationId,
        int $stockId,
        string $sku,
        float $quantity,
        $metadata = null,
        ?string $sourceCode = null,
        ?string $objectIncrementId = null
    ) {
        $this->reservationId = $reservationId;
        $this->stockId = $stockId;
        $this->sku = $sku;
        $this->quantity = $quantity;
        $this->metadata = $metadata;
        $this->sourceCode = $sourceCode;
        $this->objectIncrementId = $objectIncrementId;
    }

    /**
     * @inheritdoc
     */
    public function getReservationId(): ?int
    {
        return $this->reservationId === null ?
            null:
            (int)$this->reservationId;
    }

    /**
     * @inheritdoc
     */
    public function getStockId(): int
    {
        return $this->stockId;
    }

    /**
     * @inheritdoc
     */
    public function getSku(): string
    {
        return $this->sku;
    }

    /**
     * @inheritdoc
     */
    public function getQuantity(): float
    {
        return $this->quantity;
    }

    /**
     * @inheritdoc
     */
    public function getMetadata(): ?string
    {
        return $this->metadata;
    }

    /**
     * @inheritdoc
     */
    public function getSourceCode(): ?string
    {
        return $this->sourceCode;
    }

    /**
     * @inheritdoc
     */
    public function getObjectIncrementId(): ?string
    {
        return $this->objectIncrementId;
    }
}
