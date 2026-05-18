<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\Resolver;

use Byte8\StockRadar\Model\ConfigInterface;
use Byte8\StockRadar\Model\SubscriptionService;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlInputException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\GraphQl\Model\Query\ContextInterface;

class Subscribe implements ResolverInterface
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly ConfigInterface $config,
        private readonly RemoteAddress $remoteAddress
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
            $result = $this->subscriptionService->subscribe(
                $sku,
                $email,
                $storeId,
                $customerId,
                (string) $this->remoteAddress->getRemoteAddress()
            );
        } catch (LocalizedException $e) {
            throw new GraphQlInputException(__($e->getMessage()));
        }

        // When hide_created_flag is on we always report created=true so the
        // mutation can't be used to probe whether an email is already
        // subscribed to a given SKU.
        $created = $this->config->isCreatedFlagHidden($storeId) ? true : $result['created'];
        $requiresConfirmation = !empty($result['requires_confirmation']);
        $message = $requiresConfirmation
            ? __('Check your inbox — please click the link in our email to confirm your subscription.')
            : __("We'll email you when this product is back in stock.");

        return [
            'success' => true,
            'created' => $created,
            'message' => (string) $message,
        ];
    }
}
