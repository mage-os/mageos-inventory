<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Plugin\Import;

use Magento\CatalogImportExport\Model\StockItemProcessorInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\GetSourceItemsBySkuInterface;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryImportExport\Model\Import\SourceResolver;

/**
 * Assigning products to corresponding source
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
     * @var SourceItemInterfaceFactory $sourceItemFactory
     */
    private SourceItemInterfaceFactory $sourceItemFactory;

    /**
     * @var SourceResolver $sourceResolver
     */
    private SourceResolver $sourceResolver;

    /**
     * @var GetSourceItemsBySkuInterface
     */
    private GetSourceItemsBySkuInterface $sourceItemsBySku;

    /**
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param GetSourceItemsBySkuInterface $sourceItemsBySku
     * @param SourceResolver $sourceResolver
     */
    public function __construct(
        SourceItemsSaveInterface $sourceItemsSave,
        SourceItemInterfaceFactory $sourceItemFactory,
        GetSourceItemsBySkuInterface $sourceItemsBySku,
        SourceResolver $sourceResolver
    ) {
        $this->sourceItemsSave = $sourceItemsSave;
        $this->sourceItemFactory = $sourceItemFactory;
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
                $sources = $this->sourceResolver->getSourcesForStore($storeId);
                $currentSource = reset($sources);

                $qty = $this->determineSourceQuantity(
                    (string)$sku,
                    $storeId,
                    $currentSource,
                    $importedData
                );

                $inStock = $this->determineStatus($sku, $storeId, $qty, $importedData, $stockDataItem);

                $sourceItem = $this->sourceItemFactory->create();
                $sourceItem->setSku((string)$sku);
                $sourceItem->setSourceCode($currentSource);
                $sourceItem->setQuantity($qty);
                $sourceItem->setStatus($inStock);

                $sourceItems[] = $sourceItem;
            }
        }

        if (count($sourceItems) > 0) {
            /** SourceItemInterface[] $sourceItems */
            $this->sourceItemsSave->execute($sourceItems);
        }
    }

    /**
     * Determine the stock status for a given SKU and store.
     *
     * @param string $sku
     * @param int $storeId
     * @param float $qty
     * @param array $importedData
     * @param array $stockDataItem
     * @return int
     */
    private function determineStatus(
        string $sku,
        int $storeId,
        float $qty,
        array $importedData,
        array $stockDataItem
    ): int {
        if (isset($importedData[$sku][$storeId]['is_in_stock'])) {
            return (int)$importedData[$sku][$storeId]['is_in_stock'];
        }

        $minQty  = $stockDataItem['min_qty'] ?? 0;
        $inStock = $qty > 0 && $qty >= $minQty ? 1 : 0;
        if (!$inStock && $qty >= $minQty && $stockDataItem['backorders'] == 1) {
            $inStock = 1;
        }
        return $inStock;
    }

    /**
     * Determine the source quantity for a given SKU and store.
     *
     * @param string $sku
     * @param int $storeId
     * @param string $currentSource
     * @param array $importedData
     * @return float
     */
    private function determineSourceQuantity(
        string $sku,
        int $storeId,
        string $currentSource,
        array $importedData
    ): float {
        $isQtyExplicitlySet = isset($importedData[$sku][$storeId]['qty']) ?? false;

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

        return (float)$qty;
    }
}
