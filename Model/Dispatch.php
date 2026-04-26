<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Byte8\StockRadar\Api\Data\DispatchInterface;
use Byte8\StockRadar\Model\ResourceModel\Dispatch as DispatchResource;
use Magento\Framework\Model\AbstractModel;

class Dispatch extends AbstractModel
{
    protected $_eventPrefix = DispatchInterface::DB_TABLE_NAME;
    protected $_idFieldName = DispatchInterface::ENTITY_ID;

    protected function _construct()
    {
        $this->_init(DispatchResource::class);
    }
}
