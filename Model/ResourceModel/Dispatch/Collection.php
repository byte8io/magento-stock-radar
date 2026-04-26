<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\ResourceModel\Dispatch;

use Byte8\StockRadar\Api\Data\DispatchInterface;
use Byte8\StockRadar\Model\Dispatch;
use Byte8\StockRadar\Model\ResourceModel\Dispatch as DispatchResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = DispatchInterface::ENTITY_ID;

    protected function _construct()
    {
        $this->_init(Dispatch::class, DispatchResource::class);
    }
}
