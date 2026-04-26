<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\Resolver;

use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;

class Subscribe implements ResolverInterface
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
        $input = $args['input'] ?? [];
        $sku = trim((string) ($input['sku'] ?? ''));
        $email = trim((string) ($input['email'] ?? ''));
        if ($sku === '' || $email === '') {
            throw new GraphQlInputException(__('SKU and email are required.'));
        }

        /** @var ContextInterface $context */
        $store = $context->getExtensionAttributes()->getStore();
        $storeId = (int) $store->getId();
        $customerId = $context->getUserId() > 0 ? (int) $context->getUserId() : null;

        try {
            $result = $this->subscriptionService->subscribe($sku, $email, $storeId, $customerId);
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }

        return [
            'success' => true,
            'created' => $result['created'],
            'message' => (string) __("We'll email you when this product is back in stock."),
        ];
    }
}
