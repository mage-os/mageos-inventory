<?php
/**
 * Copyright 2025 Adobe
 * All Rights Reserved.
 */
declare(strict_types=1);

namespace Magento\InventoryAdminUi\Controller\Adminhtml\Source;

use Exception;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\CouldNotDeleteException;
use Magento\Inventory\Model\ResourceModel\Source as SourceResource;
use Magento\Inventory\Model\ResourceModel\Source\Collection;
use Magento\Inventory\Model\ResourceModel\Source\CollectionFactory;
use Magento\InventoryCatalogApi\Api\DefaultSourceProviderInterface;
use Magento\InventoryApi\Api\GetStockSourceLinksInterface;
use Magento\InventoryApi\Api\SourceItemRepositoryInterface;
use Magento\InventoryApi\Api\Data\SourceItemInterface;
use Magento\InventoryApi\Api\Data\StockSourceLinkInterface;
use Magento\Backend\App\AbstractAction;
use Magento\Ui\Component\MassAction\Filter;

/**
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class MassDelete extends AbstractAction implements HttpPostActionInterface, HttpGetActionInterface
{
    /**
     * Authorization level of a basic admin session
     *
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_InventoryApi::source_edit';

    /**
     * @var Filter
     */
    private $massActionFilter;

    /**
     * @var CollectionFactory
     */
    private $sourceCollectionFactory;

    /**
     * @var DefaultSourceProviderInterface
     */
    private $defaultSourceProvider;

    /**
     * @var GetStockSourceLinksInterface
     */
    private $getStockSourceLinks;

    /**
     * @var SourceItemRepositoryInterface
     */
    private $sourceItemRepository;

    /**
     * @var SearchCriteriaBuilder
     */
    private $searchCriteriaBuilder;

    /**
     * @var SourceResource
     */
    private $sourceResource;

    /**
     * @param Context $context
     * @param Filter $massActionFilter
     * @param CollectionFactory $sourceCollectionFactory
     * @param DefaultSourceProviderInterface $defaultSourceProvider
     * @param GetStockSourceLinksInterface $getStockSourceLinks
     * @param SourceItemRepositoryInterface $sourceItemRepository
     * @param SearchCriteriaBuilder $searchCriteriaBuilder
     * @param SourceResource $sourceResource
     */
    public function __construct(
        Context $context,
        Filter $massActionFilter,
        CollectionFactory $sourceCollectionFactory,
        DefaultSourceProviderInterface $defaultSourceProvider,
        GetStockSourceLinksInterface $getStockSourceLinks,
        SourceItemRepositoryInterface $sourceItemRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        SourceResource $sourceResource
    ) {
        $this->massActionFilter = $massActionFilter;
        $this->sourceCollectionFactory = $sourceCollectionFactory;
        $this->defaultSourceProvider = $defaultSourceProvider;
        $this->getStockSourceLinks = $getStockSourceLinks;
        $this->sourceItemRepository = $sourceItemRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->sourceResource = $sourceResource;
        parent::__construct($context);
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        $resultRedirect->setPath('inventory/source/index');

        $request = $this->getRequest();
        if (!$request->isPost()) {
            $this->messageManager->addErrorMessage(__('Wrong request.'));
            return $resultRedirect;
        }

        $collection = $this->sourceCollectionFactory->create();
        $this->massActionFilter->getCollection($collection);

        $this->deleteSources($collection);

        return $resultRedirect;
    }

    /**
     * Mass delete sources
     *
     * @param Collection $sourceCollection
     * @return void
     * @SuppressWarnings(PHPMD.CyclomaticComplexity)
     * @SuppressWarnings(PHPMD.NPathComplexity)
     */
    private function deleteSources(Collection $sourceCollection): void
    {
        $deleted = 0;
        $skippedDefault = 0;
        $skippedLinked = 0;
        $skippedWithItems = 0;

        foreach ($sourceCollection as $source) {
            $code = (string)$source->getSourceCode();

            // Default Source guard
            if ($code === $this->defaultSourceProvider->getCode()) {
                $skippedDefault++;
                continue;
            }

            // Block if linked to any stock
            $this->searchCriteriaBuilder->addFilter(
                StockSourceLinkInterface::SOURCE_CODE,
                $code
            );
            $links = $this->getStockSourceLinks->execute($this->searchCriteriaBuilder->create());
            // Reset builder state for next use
            $this->searchCriteriaBuilder->setFilterGroups([]);

            if ($links->getTotalCount() > 0) {
                $skippedLinked++;
                continue;
            }

            // Block if any source items exist
            $this->searchCriteriaBuilder->addFilter(
                SourceItemInterface::SOURCE_CODE,
                $code
            );
            $items = $this->sourceItemRepository->getList($this->searchCriteriaBuilder->create());
            $this->searchCriteriaBuilder->setFilterGroups([]);

            if ($items->getTotalCount() > 0) {
                $skippedWithItems++;
                continue;
            }

            try {
                $this->sourceResource->delete($source);
                $deleted++;
            } catch (Exception $e) {
                $this->messageManager->addErrorMessage(__('Could not delete source "%1": %2', $code, $e->getMessage()));
            }
        }

        if ($deleted) {
            $this->messageManager->addSuccessMessage(
                __('A total of %1 record(s) have been deleted.', $deleted)
            );
        }
        if ($skippedDefault) {
            $this->messageManager->addNoticeMessage(
                __('A total of %1 source(s) skipped: Default Source cannot be deleted.', $skippedDefault)
            );
        }
        if ($skippedLinked) {
            $this->messageManager->addNoticeMessage(
                __('A total of %1 source(s) skipped: assigned to Stock. Unassign first.', $skippedLinked)
            );
        }
        if ($skippedWithItems) {
            $this->messageManager->addNoticeMessage(
                __('A total of %1 source(s) skipped: has Source Items. Remove them first.', $skippedWithItems)
            );
        }
    }
}
