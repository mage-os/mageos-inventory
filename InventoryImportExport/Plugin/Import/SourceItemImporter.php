<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Plugin\Import;

use Magento\CatalogImportExport\Model\Import\Product\SkuProcessor;
use Magento\CatalogImportExport\Model\Import\Product\SkuStorage;
use Magento\CatalogImportExport\Model\StockItemProcessorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryImportExport\Model\Import\SourceResolver;
use Magento\InventorySalesApi\Api\StockResolverInterface;

/**
 * Assigning products to default source
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SourceItemImporter
{
    /**
     * Source Items Save Interface for saving multiple source items
     *
     * @var SourceItemsSaveInterface $sourceItemsSave
     */
    private SourceItemsSaveInterface $sourceItemsSave;

    /**
     * Source Item Interface Factory
     *
     * @var SourceItemInterfaceFactory $sourceItemFactory
     */
    private SourceItemInterfaceFactory $sourceItemFactory;

    /**
     * @var SourceResolver $sourceResolver
     */
    private SourceResolver $sourceResolver;


    /**
     * @var ResourceConnection
     */
    private ResourceConnection $resourceConnection;

    /**
     * @var SkuProcessor
     */
    private SkuProcessor $skuProcessor;

    /**
     * @var SkuStorage
     */
    private SkuStorage $skuStorage;

    /**
     * @var StockResolverInterface
     */
    private StockResolverInterface $stockResolver;

    /**
     * @var GetSourceItemsBySkuInterface
     */
    private GetSourceItemsBySkuInterface $sourceItemsBySku;

    /**
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param ResourceConnection $resourceConnection
     * @param SkuProcessor $skuProcessor
     * @param SkuStorage $skuStorage
     * @param StockResolverInterface $stockResolver
     * @param GetSourceItemsBySkuInterface $sourceItemsBySku
     * @param SourceResolver $sourceResolver
     */
    public function __construct(
        SourceItemsSaveInterface $sourceItemsSave,
        SourceItemInterfaceFactory $sourceItemFactory,
        ResourceConnection $resourceConnection,
        SkuProcessor $skuProcessor,
        SkuStorage $skuStorage,
        StockResolverInterface $stockResolver,
        GetSourceItemsBySkuInterface $sourceItemsBySku,
        SourceResolver $sourceResolver
    ) {
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
        $this->resourceConnection = $resourceConnection;
        $this->skuProcessor = $skuProcessor;
        $this->skuStorage = $skuStorage;
        $this->stockResolver = $stockResolver;
        $this->sourceItemsBySku = $sourceItemsBySku;
        $this->sourceResolver = $sourceResolver;
    }

    /**
     * After plugin Import to import Stock Data to Source Items
     *
     * @param StockItemProcessorInterface $subject
     * @param mixed $result
     * @param array $stockData
     * @param array $importedData
     * @return void
     * @throws CouldNotSaveException
     * @throws InputException
     * @throws ValidationException
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function afterProcess(
        StockItemProcessorInterface $subject,
        mixed $result,
        array $stockData,
        array $importedData
    ): void {
        $sourceItems = [];

        foreach ($stockData as $sku => $stockDatum) {
            foreach ($stockDatum as $storeId => $stockDataItem) {
                $isQtyExplicitlySet = $importedData[$sku][$storeId]['qty'] ?? false;

                $sources = $this->sourceResolver->getSourcesForStore($storeId);
                $currentSource = reset($sources);
                $qty = 0;
                if ($isQtyExplicitlySet) {
                    $qty = $importedData[$sku][$storeId]['qty'];
                } else {
                    $items = $this->sourceItemsBySku->execute($sku);
                    foreach ($items as $item) {
                        if ($item->getSourceCode() == $currentSource) {
                            $qty = $item->getQuantity();
                            break;
                        }
                    }
                }

                $minQty  = $stockDataItem['min_qty'] ?? 0;
                $inStock = $qty >= $minQty ? 1 : 0;

                $sourceItem = $this->sourceItemFactory->create();
                $sourceItem->setSku((string)$sku);
                $sourceItem->setSourceCode($currentSource);
                $sourceItem->setQuantity((float)$qty);
                $sourceItem->setStatus($inStock);

                $sourceItems[] = $sourceItem;
            }
        }

        if (count($sourceItems) > 0) {
            /** SourceItemInterface[] $sourceItems */
            $this->sourceItemsSave->execute($sourceItems);
        }
    }
}
