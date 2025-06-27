<?php
/**
 * Copyright 2017 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalog\Model;

use Magento\Catalog\Model\ResourceModel\Product as ProductResourceModel;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\InventoryCatalogApi\Model\GetProductIdsBySkusInterface;

/**
 * @inheritdoc
 */
class GetProductIdsBySkus implements GetProductIdsBySkusInterface
{
    /**
     * @var ProductResourceModel
     */
    private $productResource;

    /**
     * @param ProductResourceModel $productResource
     */
    public function __construct(
        ProductResourceModel $productResource
    ) {
        $this->productResource = $productResource;
    }

    /**
     * @inheritdoc
     */
    public function execute(array $skus): array
    {
        $idsBySkus = $this->productResource->getProductsIdsBySkus($skus);
        $notFoundedSkus = array_diff($skus, array_keys($idsBySkus));

        if (!empty($notFoundedSkus)) {
            throw new NoSuchEntityException(
                __('Following products with requested skus were not found: %1', implode(', ', $notFoundedSkus))
            );
        }

        return $idsBySkus;
    }
}
