<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Magento\Framework\Model\AbstractModel;

class Subscription extends AbstractModel
{
    protected $_eventPrefix = SubscriptionInterface::DB_TABLE_NAME;
    protected $_idFieldName = SubscriptionInterface::ENTITY_ID;

    protected function _construct()
    {
        $this->_init(SubscriptionResource::class);
    }
}
