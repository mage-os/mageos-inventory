<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventorySalesAdminUi\Model;

use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\Store\Api\WebsiteRepositoryInterface;

/**
 * {@inheritdoc}
 *
 * In default implementation works only with website
 */
class WebsiteNameResolver implements SalesChannelNameResolverInterface
{
    /**
     * @var WebsiteRepositoryInterface
     */
    private $websiteRepository;

    /**
     * @param WebsiteRepositoryInterface $websiteRepository
     */
    public function __construct(
        WebsiteRepositoryInterface $websiteRepository
    ) {
        $this->websiteRepository = $websiteRepository;
    }

    /**
     * @inheritdoc
     */
    public function resolve(string $type, string $code): string
    {
        return SalesChannelInterface::TYPE_WEBSITE === $type ? $this->websiteRepository->get($code)->getName() : $code;
    }
}
