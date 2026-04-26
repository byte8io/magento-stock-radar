<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Ui\Component\Listing\Column\SubscriptionStatus;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Magento\Framework\Data\OptionSourceInterface;

class Options implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => SubscriptionInterface::STATUS_PENDING, 'label' => __('Pending')],
            ['value' => SubscriptionInterface::STATUS_NOTIFIED, 'label' => __('Notified')],
            ['value' => SubscriptionInterface::STATUS_CANCELLED, 'label' => __('Cancelled')],
            ['value' => SubscriptionInterface::STATUS_BOUNCED, 'label' => __('Bounced')],
        ];
    }
}
