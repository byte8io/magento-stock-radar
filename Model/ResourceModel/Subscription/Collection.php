<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\ResourceModel\Subscription;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Byte8\StockRadar\Model\Subscription;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = SubscriptionInterface::ENTITY_ID;

    protected function _construct()
    {
        $this->_init(Subscription::class, SubscriptionResource::class);
    }
}
