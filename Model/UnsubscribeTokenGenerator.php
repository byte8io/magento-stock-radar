<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Magento\Framework\Math\Random;

class UnsubscribeTokenGenerator
{
    public function __construct(
        private readonly Random $random
    ) {
    }

    public function generate(): string
    {
        return $this->random->getRandomString(48, Random::CHARS_LOWERS . Random::CHARS_DIGITS);
    }
}
