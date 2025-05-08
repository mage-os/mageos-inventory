<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryLowQuantityNotificationAdminUi\Controller\Adminhtml\Report;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\ResponseInterface;
use Magento\Reports\Controller\Adminhtml\Report\Product as ProductReportController;

class ExportLowstockExcel extends ProductReportController implements HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_Reports::report_products';

    /**
     * Export low stock products report to XML format
     *
     * @return ResponseInterface
     */
    public function execute()
    {
        $this->_view->loadLayout('reports_report_product_lowstock');
        $fileName = 'products_lowstock.xml';

        $exportBlock = $this->_view->getLayout()->getChildBlock(
            'adminhtml.block.report.product.inventory.lowstock.grid',
            'grid.export'
        );

        return $this->_fileFactory->create(
            $fileName,
            $exportBlock->getExcelFile(),
            DirectoryList::VAR_DIR
        );
    }
}
