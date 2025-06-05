<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryCatalogAdminUi\Controller\Adminhtml\Inventory;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\InventoryCatalogAdminUi\Controller\Adminhtml\Bulk\BulkPageProcessor;

/**
 * Mass transfer sources between products.
 */
class BulkTransfer extends Action implements HttpPostActionInterface
{
    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Catalog::products';

    /**
     * @var BulkPageProcessor
     */
    private $processBulkPage;

    /**
     * @param Action\Context $context
     * @param BulkPageProcessor $processBulkPage
     */
    public function __construct(
        Action\Context $context,
        BulkPageProcessor $processBulkPage
    ) {
        parent::__construct($context);

        $this->processBulkPage = $processBulkPage;
    }

    /**
     * Bulk Inventory Transfer
     *
     * @return ResponseInterface|ResultInterface
     */
    public function execute()
    {
        return $this->processBulkPage->execute(__('Bulk Inventory Transfer'), true);
    }
}
