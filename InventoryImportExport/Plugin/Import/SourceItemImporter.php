<?php
/**
 * Copyright 2018 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryImportExport\Plugin\Import;

use Magento\CatalogImportExport\Model\Import\Product\SkuStorage;
use Magento\CatalogImportExport\Model\StockItemProcessorInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Validation\ValidationException;
use Magento\Inventory\Model\ResourceModel\SourceItem;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterfaceFactory;
use Magento\InventoryApi\Api\SourceItemsSaveInterface;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryCatalogApi\Model\IsSingleSourceModeInterface;
use Magento\InventoryIndexer\Indexer\SourceItem\SourceItemIndexer;

/**
 * Assigning products to default source
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SourceItemImporter
{
    /**
     * These inventory configurations affects all sources
     *
     * @var string[]
     */
    private const STOCK_CONFIGURATION_FIELDS = [
        'min_qty' => null,
        'use_config_min_qty' => null,
        'backorders' => null,
        'use_config_backorders' => null,
        'out_of_stock_qty' => null, // alias for min_qty
        'allow_backorders' => null // alias for backorders
    ];

    /**
     * StockItemImporter constructor
     *
     * @param SourceItemsSaveInterface $sourceItemsSave
     * @param SourceItemInterfaceFactory $sourceItemFactory
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param IsSingleSourceModeInterface $isSingleSourceMode
     * @param ResourceConnection $resourceConnection
     * @param SkuStorage $skuStorage
     * @param SourceItemIndexer $sourceItemIndexer
     */
    public function __construct(
        private readonly SourceItemsSaveInterface $sourceItemsSave,
        private readonly SourceItemInterfaceFactory $sourceItemFactory,
        private readonly DefaultSourceProviderInterface $defaultSourceProvider,
        private readonly IsSingleSourceModeInterface $isSingleSourceMode,
        private readonly ResourceConnection $resourceConnection,
        private readonly SkuStorage $skuStorage,
        private readonly SourceItemIndexer $sourceItemIndexer
    ) {
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
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     */
    public function afterProcess(
        StockItemProcessorInterface $subject,
        mixed $result,
        array $stockData,
        array $importedData
    ): void {
        $sourceItems = [];
        $isSingleSourceMode = $this->isSingleSourceMode->execute();
        // No need to load existing source items in single source mode as we know the only source is 'default'
        $existingSourceItemsBySKU = $isSingleSourceMode ? [] : $this->getSourceItems(array_keys($stockData));
        $defaultSourceCode = $this->defaultSourceProvider->getCode();
        $sourceItemIds = [];
        foreach ($stockData as $sku => $stockDatum) {
            $sku = (string)$sku;
            $isNewSku = !$this->skuStorage->has($sku);
            $isQtyExplicitlySet = $importedData[$sku]['qty'] ?? false;

            $inStock = $stockDatum['is_in_stock'] ?? 0;
            $qty = $stockDatum['qty'] ?? 0;
            $sourceItem = $this->sourceItemFactory->create();
            $sourceItem->setSku($sku);
            $sourceItem->setSourceCode($defaultSourceCode);
            $sourceItem->setQuantity((float)$qty);
            $sourceItem->setStatus((int)$inStock);

            //Prevent existing products to be assigned to `default` source, when `qty` is not explicitly set.
            if ($isNewSku
                || $isQtyExplicitlySet
                || $isSingleSourceMode
                || !empty($existingSourceItemsBySKU[$sku][$defaultSourceCode])) {
                $sourceItems[] = $sourceItem;
            }

            if (isset($existingSourceItemsBySKU[$sku])) {
                $hasGlobalInventoryConfigurationChanges = array_filter(
                    array_intersect_key($importedData[$sku] ?? [], self::STOCK_CONFIGURATION_FIELDS),
                    fn ($value) => $value !== null
                );
                if ($hasGlobalInventoryConfigurationChanges) {
                    $sources = $existingSourceItemsBySKU[$sku];
                    unset($sources[$defaultSourceCode]);
                    array_push($sourceItemIds, ...array_values($sources));
                }
            }
        }
        if (count($sourceItems) > 0) {
            $this->sourceItemsSave->execute($sourceItems);
            // reindex non default source items because global stock configuration
            // such as backorders may affect stock status
            if (!empty($sourceItemIds)) {
                $this->sourceItemIndexer->executeList($sourceItemIds);
            }
        }
    }

    /**
     * Fetch product's source items
     *
     * @param array $skus
     * @return array
     */
    private function getSourceItems(array $skus): array
    {
        $connection = $this->resourceConnection->getConnection();
        $select = $connection->select()->from(
            $this->resourceConnection->getTableName(SourceItem::TABLE_NAME_SOURCE_ITEM),
            [SourceItem::ID_FIELD_NAME, SourceItemInterface::SOURCE_CODE, SourceItemInterface::SKU]
        )->where(
            SourceItemInterface::SKU . ' IN (?)',
            $skus
        );

        $result = [];
        foreach ($connection->fetchAll($select) as $item) {
            $result[$item[SourceItemInterface::SKU]][$item[SourceItemInterface::SOURCE_CODE]] =
                $item[SourceItem::ID_FIELD_NAME];
        }
        return $result;
    }
}
