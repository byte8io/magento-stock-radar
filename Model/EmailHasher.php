<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

/**
 * Stable, case-insensitive hash for email lookups. Used so GDPR delete-by-email
 * requests can wipe subscriptions in O(1) without storing the raw email in the
 * lookup index.
 */
class EmailHasher
{
    public function hash(string $email): string
    {
        return hash('sha256', strtolower(trim($email)));
    }
}
