<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\Resolver;

use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;

class Unsubscribe implements ResolverInterface
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        $token = trim((string) ($args['token'] ?? ''));
        if ($token === '') {
            throw new GraphQlInputException(__('Token is required.'));
        }

        // Always return success — never reveal whether a token matched.
        $this->subscriptionService->unsubscribeByToken($token);

        return [
            'success' => true,
            'message' => (string) __('You have been unsubscribed.'),
        ];
    }
}
