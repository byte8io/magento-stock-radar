<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Ui\Component\Subscription;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Framework\Api\Search\AggregationInterface;
use Magento\Framework\Api\Search\SearchResultInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Psr\Log\LoggerInterface;

/**
 * Admin subscription grid data source — joins product SKU + name onto every
 * subscription row so admins can search "who's waiting for SKU X" instead of
 * having to translate from numeric product_id.
 *
 * Modelled on the Demand Collection (same JOIN strategy) but without the
 * COUNT/GROUP aggregation — this grid is one row per subscription.
 */
class Collection extends AbstractCollection implements SearchResultInterface
{
    private string $aliasMain = 'main_table';

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        private readonly EavConfig $eavConfig,
        $mainTable = SubscriptionInterface::DB_TABLE_NAME,
        $eventPrefix = 'byte8_stock_radar_subscription_collection',
        $eventObject = 'subscription_collection',
        $resourceModel = SubscriptionResource::class,
        $connection = null,
        ?\Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->_eventPrefix = $eventPrefix;
        $this->_eventObject = $eventObject;
        // Document — not bare DataObject — so each row exposes getCustomAttributes()
        // for Magento\Framework\View\Element\UiComponent\DataProvider\DataProvider::searchResultToOutput
        $this->_init(\Magento\Framework\View\Element\UiComponent\DataProvider\Document::class, $resourceModel);
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->setMainTable($mainTable);
    }

    protected function _initSelect()
    {
        $select = $this->getConnection()->select();
        $select->from(
            [$this->aliasMain => $this->getMainTable()],
            [
                SubscriptionInterface::ENTITY_ID,
                SubscriptionInterface::PRODUCT_ID,
                SubscriptionInterface::PARENT_PRODUCT_ID,
                SubscriptionInterface::STORE_ID,
                SubscriptionInterface::CUSTOMER_ID,
                SubscriptionInterface::EMAIL,
                SubscriptionInterface::STATUS,
                SubscriptionInterface::CREATED_AT,
                SubscriptionInterface::NOTIFIED_AT,
            ]
        );

        $select->joinLeft(
            ['p' => $this->getTable('catalog_product_entity')],
            'p.entity_id = ' . $this->aliasMain . '.' . SubscriptionInterface::PRODUCT_ID,
            ['sku' => 'p.sku']
        );

        try {
            $nameAttr = $this->eavConfig->getAttribute(\Magento\Catalog\Model\Product::ENTITY, 'name');
            $nameAttrId = $nameAttr ? (int) $nameAttr->getAttributeId() : 0;
        } catch (\Throwable $e) {
            $nameAttrId = 0;
        }

        if ($nameAttrId > 0) {
            // Pick store-scoped name where present, else fallback to admin (store_id = 0)
            $select->joinLeft(
                ['name' => $this->getTable('catalog_product_entity_varchar')],
                'name.entity_id = ' . $this->aliasMain . '.' . SubscriptionInterface::PRODUCT_ID
                . ' AND name.attribute_id = ' . $nameAttrId
                . ' AND name.store_id IN (0, ' . $this->aliasMain . '.' . SubscriptionInterface::STORE_ID . ')',
                ['product_name' => 'name.value']
            );
        }

        // AbstractDb has no setSelect(); assign the protected property directly.
        // We've built the full select inline (FROM + columns + joins) rather
        // than mutating the parent's default, so this replaces wholesale.
        $this->_select = $select;
        return $this;
    }

    public function getMainTable()
    {
        return $this->getTable(SubscriptionInterface::DB_TABLE_NAME);
    }

    public function setMainTable($table)
    {
        $this->_mainTable = $this->getTable($table);
        return $this;
    }

    public function getAggregations(): ?AggregationInterface
    {
        return $this->aggregations ?? null;
    }

    public function setAggregations($aggregations): self
    {
        $this->aggregations = $aggregations;
        return $this;
    }

    public function getSearchCriteria()
    {
        return null;
    }

    public function setSearchCriteria(?\Magento\Framework\Api\SearchCriteriaInterface $searchCriteria = null): self
    {
        return $this;
    }

    public function getTotalCount()
    {
        return $this->getSize();
    }

    public function setTotalCount($totalCount): self
    {
        return $this;
    }

    public function setItems(?array $items = null): self
    {
        return $this;
    }

    /** @var AggregationInterface|null */
    private ?AggregationInterface $aggregations = null;
}
