<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Ui\Component\Listing\Column\Demand;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Per-row "Edit product" link in the demand heatmap. Lets the merchandiser
 * jump straight to the product to update inventory or trigger a reorder.
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
        foreach ($dataSource['data']['items'] as &$item) {
            $productId = (int) ($item['parent_product_id'] ?: $item['product_id']);
            if ($productId <= 0) {
                continue;
            }
            $item[$name]['edit_product'] = [
                'href' => $this->urlBuilder->getUrl('catalog/product/edit', ['id' => $productId]),
                'label' => __('Edit product'),
            ];
        }

        return $dataSource;
    }
}
