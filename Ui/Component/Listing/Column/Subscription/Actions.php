<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Ui\Component\Listing\Column\Subscription;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Per-row Cancel link in the subscription grid. Only rendered for rows that
 * aren't already in a terminal state (cancelled / bounced) — clicking on a
 * terminal row would just no-op via the resource model, so we keep the UI
 * honest by not offering the link at all.
 */
class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }

        $name = $this->getData('name');
        $terminal = [
            SubscriptionInterface::STATUS_CANCELLED,
            SubscriptionInterface::STATUS_BOUNCED,
        ];

        foreach ($dataSource['data']['items'] as &$item) {
            $id = (int) ($item[SubscriptionInterface::ENTITY_ID] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $status = (string) ($item[SubscriptionInterface::STATUS] ?? '');
            if (in_array($status, $terminal, true)) {
                continue;
            }

            $item[$name]['cancel'] = [
                'href' => $this->urlBuilder->getUrl('byte8/subscription/cancel', ['id' => $id]),
                'label' => __('Cancel'),
                'confirm' => [
                    'title' => __('Cancel subscription'),
                    'message' => __('Cancel this subscription? The customer will not receive a back-in-stock email.'),
                ],
            ];

            // Edit-product link uses parent_product_id for variants so the
            // admin lands on the configurable parent page rather than the
            // standalone simple. Falls back to product_id for true simples.
            $productId = (int) ($item['parent_product_id'] ?: $item['product_id'] ?? 0);
            if ($productId > 0) {
                $item[$name]['edit_product'] = [
                    'href' => $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $productId]),
                    'label' => __('Edit product'),
                ];
            }
        }

        return $dataSource;
    }
}
