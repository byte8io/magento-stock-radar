<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Ui\Component\Demand;

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
 * Aggregates pending subscriptions per product+store and joins product SKU/name
 * for the admin demand heatmap. This is the merchandiser's reorder report:
 * "rank what's bleeding the most lost sales right now."
 *
 * SQL shape:
 *   SELECT s.product_id, s.parent_product_id, s.store_id,
 *          COUNT(*) AS subscriber_count,
 *          MIN(s.created_at) AS first_subscribed,
 *          MAX(s.created_at) AS latest_subscribed,
 *          p.sku, name.value AS product_name
 *   FROM byte8_stock_radar_subscription s
 *   LEFT JOIN catalog_product_entity p ON p.entity_id = s.product_id
 *   LEFT JOIN catalog_product_entity_varchar name ON name.entity_id = s.product_id
 *        AND name.attribute_id = <name attr id> AND name.store_id IN (0, s.store_id)
 *   WHERE s.status = 'pending'
 *   GROUP BY s.product_id, s.store_id
 *   ORDER BY subscriber_count DESC
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
        $eventPrefix = 'byte8_stock_radar_demand_collection',
        $eventObject = 'demand_collection',
        $resourceModel = SubscriptionResource::class,
        $connection = null,
        ?\Magento\Framework\Model\ResourceModel\Db\AbstractDb $resource = null
    ) {
        $this->_eventPrefix = $eventPrefix;
        $this->_eventObject = $eventObject;
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $connection, $resource);
        $this->_init(\Magento\Framework\DataObject::class, $resourceModel);
        $this->setMainTable($mainTable);
    }

    protected function _initSelect()
    {
        $select = $this->getConnection()->select();
        $select->from(
            [$this->aliasMain => $this->getMainTable()],
            [
                SubscriptionInterface::PRODUCT_ID,
                SubscriptionInterface::PARENT_PRODUCT_ID,
                SubscriptionInterface::STORE_ID,
                'subscriber_count' => new \Zend_Db_Expr('COUNT(*)'),
                'first_subscribed' => new \Zend_Db_Expr('MIN(' . SubscriptionInterface::CREATED_AT . ')'),
                'latest_subscribed' => new \Zend_Db_Expr('MAX(' . SubscriptionInterface::CREATED_AT . ')'),
            ]
        );
        $select->where(
            $this->aliasMain . '.' . SubscriptionInterface::STATUS . ' = ?',
            SubscriptionInterface::STATUS_PENDING
        );
        $select->group([
            $this->aliasMain . '.' . SubscriptionInterface::PRODUCT_ID,
            $this->aliasMain . '.' . SubscriptionInterface::STORE_ID,
        ]);
        $select->order('subscriber_count DESC');

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
            // Pick store-scoped name when present, else fallback to admin (store_id = 0)
            $select->joinLeft(
                ['name' => $this->getTable('catalog_product_entity_varchar')],
                'name.entity_id = ' . $this->aliasMain . '.' . SubscriptionInterface::PRODUCT_ID
                . ' AND name.attribute_id = ' . $nameAttrId
                . ' AND name.store_id IN (0, ' . $this->aliasMain . '.' . SubscriptionInterface::STORE_ID . ')',
                ['product_name' => 'name.value']
            );
        }

        $this->setSelect($select);
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

    /**
     * @inheritDoc
     */
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
