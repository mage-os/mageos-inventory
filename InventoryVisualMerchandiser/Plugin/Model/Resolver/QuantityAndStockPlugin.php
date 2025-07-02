<?php
/**
 * Copyright 2020 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryVisualMerchandiser\Plugin\Model\Resolver;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\EntityManager\MetadataPool;
use Magento\Framework\Exception\LocalizedException;
use Magento\InventoryCatalogApi\Api\DefaultStockProviderInterface;
use Magento\InventoryIndexer\Model\StockIndexTableNameResolverInterface;
use Magento\InventorySalesApi\Api\Data\SalesChannelInterface;
use Magento\InventorySalesApi\Api\StockResolverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\VisualMerchandiser\Model\Resolver\QuantityAndStock;

/**
 * This plugin adds multi-source stock calculation capabilities to the Visual Merchandiser feature.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class QuantityAndStockPlugin
{
    private const PARENT_STOCK_TABLE = 'tmp_parent_stock';

    private const CHILD_RELATIONS = 'tmp_child_relations';

    private const CHILD_STOCK = 'tmp_child_stock';

    /**
     * @var ResourceConnection
     */
    private $resource;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var StockResolverInterface
     */
    private $stockResolver;

    /**
     * @var StockIndexTableNameResolverInterface
     */
    private $stockIndexTableNameResolver;

    /**
     * @var DefaultStockProviderInterface
     */
    private $defaultStockProvider;

    /**
     * @var MetadataPool
     */
    private $metadataPool;

    /**
     * @param ResourceConnection $resource
     * @param StoreManagerInterface $storeManager
     * @param StockResolverInterface $stockResolver
     * @param StockIndexTableNameResolverInterface $stockIndexTableNameResolver
     * @param DefaultStockProviderInterface $defaultStockProvider
     * @param MetadataPool $metadataPool
     */
    public function __construct(
        ResourceConnection                   $resource,
        StoreManagerInterface                $storeManager,
        StockResolverInterface               $stockResolver,
        StockIndexTableNameResolverInterface $stockIndexTableNameResolver,
        DefaultStockProviderInterface        $defaultStockProvider,
        MetadataPool                         $metadataPool
    )
    {
        $this->resource = $resource;
        $this->storeManager = $storeManager;
        $this->stockResolver = $stockResolver;
        $this->stockIndexTableNameResolver = $stockIndexTableNameResolver;
        $this->defaultStockProvider = $defaultStockProvider;
        $this->metadataPool = $metadataPool;
    }

    /**
     * Extend Visual Merchandiser collection with multi-sourcing capabilities.
     *
     * @param QuantityAndStock $subject
     * @param callable $proceed
     * @param Collection $collection
     * @return Collection
     * @throws LocalizedException|\Zend_Db_Select_Exception
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundJoinStock(QuantityAndStock $subject, callable $proceed, Collection $collection): Collection
    {
        if ($collection->getStoreId() !== null) {
            $websiteId = $this->storeManager->getStore($collection->getStoreId())->getWebsiteId();
            $websiteCode = $this->storeManager->getWebsite($websiteId)->getCode();
        } else {
            $websiteCode = $this->storeManager->getWebsite()->getCode();
        }

        if ($websiteCode === 'admin') {
            $parentStockTableName = $this->createParentProductsStockTemporaryTable();
            $childRelationsTableName = $this->createChildRelationsTemporaryTable();
            $childStockTableName = $this->createChildStockTemporaryTable($childRelationsTableName);

            $collection->getSelect()
                ->joinLeft(
                    ['ps' => $parentStockTableName],
                    'e.sku = ps.sku', ['parent_stock' => 'COALESCE(ps.parent_qty, 0)']
                )
                ->joinLeft(
                    ['cs' => $childStockTableName],
                    'e.row_id = cs.parent_id', ['child_stock' => 'COALESCE(cs.child_qty, 0)']
                )
                ->columns([
                    'stock' => new \Zend_Db_Expr(
                        'IF(COALESCE(ps.parent_qty, 0) = 0, COALESCE(cs.child_qty, 0), COALESCE(ps.parent_qty, 0))'
                    )
                ]);
        } else {
            $stock = $this->stockResolver->execute(SalesChannelInterface::TYPE_WEBSITE, $websiteCode);
            $stockId = (int)$stock->getStockId();
            $collection->getSelect()->joinLeft(
                ['inventory_stock' => $this->stockIndexTableNameResolver->execute($stockId)],
                'inventory_stock.sku = e.sku',
                ['stock' => 'IFNULL(quantity, 0)']
            );
        }

        return $collection;
    }


    /**
     * @return string
     * @throws \Zend_Db_Exception
     */
    private function createParentProductsStockTemporaryTable(): string
    {
        $connection = $this->resource->getConnection();
        $parentStockTableName = $this->resource->getTableName(
            str_replace('.', '_', uniqid(self::PARENT_STOCK_TABLE, true))
        );
        $parentStockTable = $connection->newTable($parentStockTableName)
            ->addColumn('sku', Table::TYPE_TEXT, 64, ['nullable' => false])
            ->addColumn('parent_qty', Table::TYPE_DECIMAL, '12,4', ['nullable' => false])
            ->addIndex('IDX_TMP_PARENT_STOCK_SKU', ['sku'])
            ->setOption('temporary', true);
        $connection->createTable($parentStockTable);

        $select = $connection->select()
            ->from('inventory_source_item', ['sku', 'parent_qty' => new \Zend_Db_Expr('SUM(quantity)')])
            ->group('sku');

        $connection->query($connection->insertFromSelect($select, $parentStockTableName, ['sku', 'parent_qty']));

        return $parentStockTableName;
    }

    /**
     * @return string
     * @throws \Zend_Db_Exception
     */
    private function createChildRelationsTemporaryTable(): string
    {
        $connection = $this->resource->getConnection();
        $productLinkField = $this->metadataPool->getMetadata(ProductInterface::class)
            ->getLinkField();
        $childRelationsTableName = $this->resource->getTableName(
            str_replace('.', '_', uniqid(self::CHILD_RELATIONS, true))
        );

        $childRelationsTable = $connection->newTable($childRelationsTableName)
            ->addColumn('parent_id', Table::TYPE_INTEGER, null, ['nullable' => false])
            ->addColumn('child_sku', Table::TYPE_TEXT, 64, ['nullable' => false])
            ->addIndex('IDX_TMP_CHILD_REL_PARENT_ID', ['parent_id'])
            ->addIndex('IDX_TMP_CHILD_REL_CHILD_SKU', ['child_sku'])
            ->setOption('temporary', true);
        $connection->createTable($childRelationsTable);

        $select = $connection->select()
            ->from(['r' => 'catalog_product_relation'], ['parent_id'])
            ->join(
                ['c' => 'catalog_product_entity'],
                'r.child_id = c.' . $productLinkField,
                ['child_sku' => 'c.sku']
            );

        $connection->query($connection->insertFromSelect($select, $childRelationsTableName, ['parent_id', 'child_sku']));

        return $childRelationsTableName;
    }

    /**
     * @param string $childRelationsTableName
     * @return string
     * @throws \Zend_Db_Exception
     */
    private function createChildStockTemporaryTable(string $childRelationsTableName): string
    {
        $connection = $this->resource->getConnection();
        $childStockTableName = $this->resource->getTableName(
            str_replace('.', '_', uniqid(self::CHILD_STOCK, true))
        );
        $childStockTable = $connection->newTable($childStockTableName)
            ->addColumn('parent_id', Table::TYPE_INTEGER, null, ['nullable' => false])
            ->addColumn('child_qty', Table::TYPE_DECIMAL, '12,4', ['nullable' => false])
            ->addIndex('IDX_TMP_CHILD_STOCK_PARENT_ID', ['parent_id'])
            ->setOption('temporary', true);
        $connection->createTable($childStockTable);

        $select = $connection->select()
            ->from(['cr' => $childRelationsTableName], ['parent_id'])
            ->join(
                ['isi' => 'inventory_source_item'],
                'cr.child_sku = isi.sku',
                ['child_qty' => new \Zend_Db_Expr('SUM(isi.quantity)')]
            )
            ->group('cr.parent_id');

        $connection->query($connection->insertFromSelect($select, $childStockTableName, ['parent_id', 'child_qty']));

        return $childStockTableName;
    }
}
