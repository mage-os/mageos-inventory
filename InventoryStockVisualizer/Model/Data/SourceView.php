<?php
/**
 * Copyright 2026 Jeanmarcos Juarez
 * SPDX-License-Identifier: OSL-3.0 OR AFL-3.0
 */
declare(strict_types=1);

namespace Magento\InventoryStockVisualizer\Model\Data;

use Magento\InventoryStockVisualizer\Api\Data\SourceViewInterface;

/**
 * @inheritdoc
 */
class SourceView implements SourceViewInterface
{
    /**
     * @var string
     */
    private $sourceCode;

    /**
     * @var float
     */
    private $qty;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @param string $sourceCode
     * @param float $qty
     * @param string|null $name
     */
    public function __construct(string $sourceCode, float $qty, ?string $name = null)
    {
        $this->sourceCode = $sourceCode;
        $this->qty = $qty;
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getSourceCode(): string
    {
        return $this->sourceCode;
    }

    /**
     * @inheritdoc
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @inheritdoc
     */
    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    /**
     * @inheritdoc
     */
    public function getQty(): float
    {
        return $this->qty;
    }
}
