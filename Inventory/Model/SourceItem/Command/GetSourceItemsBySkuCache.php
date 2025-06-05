<?php
/**
 * Copyright 2022 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\Inventory\Model\SourceItem\Command;

use Magento\Framework\ObjectManager\ResetAfterRequestInterface;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;

/**
 * @inheritdoc
 */
class GetSourceItemsBySkuCache implements GetSourceItemsBySkuInterface, ResetAfterRequestInterface
{
    /**
     * @var GetSourceItemsBySku
     */
    private $getSourceItemsBySku;

    /**
     * @var array
     */
    private $sourceItemsBySku = [];

    /**
     * @param GetSourceItemsBySku $getSourceItemsBySku
     */
    public function __construct(GetSourceItemsBySku $getSourceItemsBySku)
    {
        $this->getSourceItemsBySku = $getSourceItemsBySku;
    }

    /**
     * @inheritDoc
     */
    public function _resetState(): void
    {
        $this->sourceItemsBySku = [];
    }

    /**
     * @inheritdoc
     */
    public function execute(string $sku): array
    {
        if (!isset($this->sourceItemsBySku[$sku])) {
            $this->sourceItemsBySku[$sku] = $this->getSourceItemsBySku->execute($sku);
        }

        return $this->sourceItemsBySku[$sku];
    }
}
