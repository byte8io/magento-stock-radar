/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */
define([
    'jquery',
    'mage/translate'
], function ($, $t) {
    'use strict';

    return function (config, element) {
        var $root = $(element);
        var $form = $root.find('form');
        var $message = $root.find('[data-role="message"]');
        var $skuField = $root.find('[data-role="sku"]');
        var $submit = $root.find('button[type="submit"]');
        var variantSkuMap = config.variantSkuMap || {};

        // Magento Swatches publishes `updateProductSummary` on body after the
        // user selects swatch options. The payload includes `productId` (the
        // resolved simple variant). We turn that into the simple SKU via the
        // server-emitted variantSkuMap and bridge to our own custom event so
        // the rest of the form picks it up uniformly.
        $('body').on('updateProductSummary', function (event, data) {
            var productId = data && (data.productId || data.product_id);
            if (!productId) {
                return;
            }
            var sku = variantSkuMap[productId];
            if (sku) {
                $skuField.val(sku);
                $('body').trigger('byte8:stockradar:variant', sku);
            }
        });

        // Also accept variant updates dispatched manually (e.g. by Hyva or a
        // custom theme that doesn't go through updateProductSummary).
        $('body').on('byte8:stockradar:variant', function (event, sku) {
            if (sku) {
                $skuField.val(sku);
            }
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            $message.removeClass('error success').text('');
            $submit.prop('disabled', true);

            $.ajax({
                url: config.url,
                method: 'POST',
                dataType: 'json',
                data: {
                    sku: $skuField.val() || config.defaultSku,
                    email: $form.find('input[name="email"]').val(),
                    form_key: $.mage.cookies.get('form_key')
                }
            }).done(function (response) {
                if (response && response.success) {
                    $message.addClass('success').text(response.message);
                    $form.find('input[name="email"]').val('');
                } else {
                    $message.addClass('error').text(response.message || $t('Subscription failed.'));
                }
            }).fail(function (xhr) {
                var msg = $t('Subscription failed.');
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
                $message.addClass('error').text(msg);
            }).always(function () {
                $submit.prop('disabled', false);
            });
        });
    };
});
