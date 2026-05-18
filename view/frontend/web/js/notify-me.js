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
        var $form = $root.find('[data-role="form"]');
        var $formTitle = $root.find('[data-role="form-title"]');
        var $panel = $root.find('[data-role="subscribed-panel"]');
        var $message = $root.find('[data-role="message"]');
        var $skuField = $root.find('[data-role="sku"]');
        var $submit = $root.find('button[type="submit"]');
        var variantSkuMap = config.variantSkuMap || {};
        var subscribedSkus = (config.subscribedSkus || []).slice();
        var isConfigurable = config.isConfigurable === true;
        // Reverse-lookup: SKU → product_id, so we can stock-check the picked variant
        var skuToProductId = {};
        Object.keys(variantSkuMap).forEach(function (pid) {
            skuToProductId[variantSkuMap[pid]] = pid;
        });

        // Configurable PDPs: relocate the widget to the bottom of the product
        // info column so toggling its visibility on swatch click doesn't
        // shift the swatches/cart above. The block's natural layout slot
        // (`product.info.stock.sku`) is the right place on simple products
        // — there the form is always present, so no shift happens — but on
        // configurables we need the form below everything else to avoid the
        // up-and-down jump as the customer tries different variants.
        if (isConfigurable) {
            var $infoMain = $('.product-info-main, .product.info.main').first();
            if ($infoMain.length) {
                // jQuery's appendTo moves the element if it already exists
                // in the DOM, so this is a no-op when already at the bottom.
                $root.appendTo($infoMain).addClass('byte8-stock-radar--moved');
            }
        }

        // jQuery `fadeIn` sets inline `display: block`, which wins over the
        // user-agent `[hidden] { display: none }` rule — so once we've
        // faded in, simply re-applying the `hidden` attribute leaves the
        // element visible. We pair the attribute toggle with `.stop().hide()`
        // (which clears the inline style and sets display:none) so the two
        // mechanisms agree.

        function showSubscribedState() {
            $form.stop(true, true).hide().attr('hidden', 'hidden');
            $formTitle.attr('hidden', 'hidden');
            $panel.removeAttr('hidden').stop(true, true).hide().fadeIn(200);
            $root.addClass('byte8-stock-radar--subscribed');
        }

        function showFormState() {
            $panel.stop(true, true).hide().attr('hidden', 'hidden');
            $formTitle.removeAttr('hidden');
            $form.removeAttr('hidden').stop(true, true).hide().fadeIn(200);
            $root.removeClass('byte8-stock-radar--subscribed');
        }

        function hideAll() {
            $form.stop(true, true).hide().attr('hidden', 'hidden');
            $formTitle.attr('hidden', 'hidden');
            $panel.stop(true, true).hide().attr('hidden', 'hidden');
        }

        function refreshStateForCurrentSku() {
            var sku = $skuField.val() || config.defaultSku;
            if (sku && subscribedSkus.indexOf(sku) !== -1) {
                showSubscribedState();
                return;
            }
            // For configurables, only the swatch mixin reveals the form by
            // setting `byte8-stock-radar-revealed` on root after an OOS click.
            if (isConfigurable && !$root.hasClass('byte8-stock-radar--revealed')) {
                hideAll();
                return;
            }
            showFormState();
        }

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
                refreshStateForCurrentSku();
            }
        });

        $form.on('submit', function (event) {
            event.preventDefault();
            $message.removeClass('error success').text('');
            $submit.prop('disabled', true);

            var submittedSku = $skuField.val() || config.defaultSku;

            var captchaInput = $form.find('input[name^="captcha["]');
            var payload = {
                sku: submittedSku,
                email: $form.find('input[name="email"]').val(),
                website: $form.find('input[name="website"]').val() || '',
                form_key: $.mage.cookies.get('form_key')
            };
            if (captchaInput.length) {
                payload['captcha[byte8_stock_radar_subscribe]'] = captchaInput.val();
            }

            $.ajax({
                url: config.url,
                method: 'POST',
                dataType: 'json',
                data: payload
            }).done(function (response) {
                if (response && response.success) {
                    if (submittedSku && subscribedSkus.indexOf(submittedSku) === -1) {
                        subscribedSkus.push(submittedSku);
                    }
                    $form.find('input[name="email"]').val('');
                    $message.addClass('success').text(response.message);
                    showSubscribedState();
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
