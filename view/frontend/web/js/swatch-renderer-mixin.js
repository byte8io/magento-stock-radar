/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 *
 * Lets customers click on out-of-stock configurable swatches so they can
 * subscribe to a back-in-stock alert for that exact variant.
 *
 * Why the click is intercepted entirely (no `_super()` for OOS picks):
 *  - Magento's `_OnClick` early-returns when the swatch has `.disabled`
 *    (vendor/magento/module-swatches/view/base/web/js/swatch-renderer.js).
 *  - Stripping `.disabled` and delegating to `_super()` works for "selecting"
 *    the option, but `_super` triggers the full configurable cascade —
 *    updating the price, switching the product image, enabling Add-to-Cart,
 *    and treating the OOS variant as a valid purchase. The customer could
 *    then add the unavailable variant to cart and only discover the problem
 *    at checkout.
 *  - So for OOS picks we do NOT delegate. We mark the swatch as "selected
 *    for notification" with our own class, resolve the simple SKU by
 *    intersecting `jsonConfig.attributes`, reveal the back-in-stock form,
 *    disable Add-to-Cart, and swap the stock label. None of Magento's other
 *    selection state is touched.
 *  - For in-stock picks we delegate normally and tear down any leftover
 *    notification-only state.
 *
 * The CSS rule `.swatch-option.disabled { pointer-events: auto; }` in
 * `view/frontend/web/css/source/_module.less` is required for any of this
 * to fire — without it the browser drops the click before JS runs.
 */
define(['jquery', 'mage/translate'], function ($, $t) {
    'use strict';

    var OOS_SELECTED = 'byte8-stock-radar-oos-selected';
    var CART_BLOCKED = 'byte8-stock-radar-cart-blocked';
    // Cover both Luma shapes:
    //   <div class="stock available" title="Availability">
    //   <div class="product attribute stock"><div class="value">In Stock</div></div>
    // The shared class is `.stock`; narrowing by `.product-info-main` keeps
    // us from accidentally matching stock indicators in related products or
    // upsell sliders.
    var STOCK_SELECTOR = '.product-info-main .stock, .product.info.main .stock';

    return function (widget) {
        $.widget('mage.SwatchRenderer', widget, {
            _OnClick: function ($this, $widget) {
                var $option = $($this);

                if (!$option.hasClass('disabled')) {
                    // In-stock pick — clear our OOS state FIRST, then let
                    // Magento handle the selection. Order matters: _super
                    // synchronously fires `updateProductSummary` which
                    // re-runs notify-me's state check — if the
                    // `byte8-stock-radar--revealed` class still exists at
                    // that point the form stays visible.
                    this._byte8StockRadarTeardown();
                    return this._super($this, $widget);
                }

                // OOS pick. Don't delegate to _super (see file header).
                this._byte8StockRadarSelectOos($option);
            },

            _byte8StockRadarSelectOos: function ($option) {
                var $widget = $(this.element);

                // Cosmetic selection — clear any prior OOS marker across all
                // attribute groups, then mark the clicked swatch. We do NOT
                // set data-option-selected because Magento would then treat
                // the OOS combo as a valid pick.
                $widget.find('.' + OOS_SELECTED).removeClass(OOS_SELECTED);
                $option.addClass(OOS_SELECTED);

                var $radar = $('.byte8-stock-radar');
                console.log('[Byte8 StockRadar] OOS swatch click', {
                    radarElementsFound: $radar.length,
                    optionId: $option.data('option-id'),
                    attributeId: $option.parents('.swatch-attribute').data('attribute-id'),
                });
                if (!$radar.length) {
                    console.warn('[Byte8 StockRadar] .byte8-stock-radar block not in DOM — likely full-page cache holding HTML from when isAvailable() was false. Try: bin/magento cache:flush');
                    return;
                }

                var simpleSku = this._byte8StockRadarResolveOosSimpleSku($option);
                console.log('[Byte8 StockRadar] resolved simple SKU:', simpleSku);
                if (!simpleSku) {
                    console.warn('[Byte8 StockRadar] could not resolve simple SKU for this combination. See _byte8StockRadarResolveOosSimpleSku in swatch-renderer-mixin.js');
                    return;
                }

                $radar.addClass('byte8-stock-radar--revealed');
                $('body').trigger('byte8:stockradar:variant', simpleSku);

                // Block Add-to-Cart. Try the standard Luma id first, then
                // fall back to the generic .action.tocart selector for themes
                // that don't use the default id.
                var $cart = $('#product-addtocart-button');
                if (!$cart.length) {
                    $cart = $('button.action.tocart, .action.primary.tocart');
                }
                console.log('[Byte8 StockRadar] add-to-cart buttons found:', $cart.length);
                $cart.prop('disabled', true).addClass('disabled ' + CART_BLOCKED);

                this._byte8StockRadarSwapStockLabel(true);
            },

            _byte8StockRadarTeardown: function () {
                $('.' + OOS_SELECTED).removeClass(OOS_SELECTED);

                // Hide the notify-me UI directly. The `--revealed` class is
                // notify-me.js's *gate* — removing it stops the form from
                // RE-showing on the next refresh, but it doesn't actively
                // hide already-displayed DOM. When the user picks only one
                // attribute after an OOS click, Magento can't resolve a
                // simple product so `updateProductSummary` either doesn't
                // fire or fires with no productId — meaning notify-me's
                // refreshStateForCurrentSku is never called and the form
                // sits there with its hidden attribute removed. So we both
                // clear the gate AND force-hide every panel.
                var $radar = $('.byte8-stock-radar');
                $radar.removeClass('byte8-stock-radar--revealed byte8-stock-radar--subscribed');
                // Use .stop(true,true).hide() rather than the `hidden`
                // attribute alone — jQuery's fadeIn sets inline display:block
                // in notify-me.js, and that beats the user-agent
                // [hidden]{display:none} rule. .hide() clears the inline
                // style and applies display:none, agreeing with the
                // accompanying hidden attribute.
                $radar.find('[data-role="form"], [data-role="form-title"], [data-role="subscribed-panel"]')
                    .stop(true, true)
                    .hide()
                    .attr('hidden', 'hidden');

                $('.' + CART_BLOCKED)
                    .prop('disabled', false)
                    .removeClass('disabled ' + CART_BLOCKED);
                this._byte8StockRadarSwapStockLabel(false);
            },

            /**
             * Toggle the PDP's stock label between its original "In stock"
             * markup and a Stock-Radar-injected "Out of stock" version.
             * Stashes the original HTML + classes on first call so we can
             * faithfully restore on teardown — preserves theme overrides.
             */
            _byte8StockRadarSwapStockLabel: function (toOos) {
                var $stock = $(STOCK_SELECTOR).first();
                if (!$stock.length) {
                    return;
                }

                if (toOos) {
                    if (!$stock.data('byte8-original-html')) {
                        $stock.data('byte8-original-html', $stock.html());
                        $stock.data('byte8-original-class', $stock.attr('class') || '');
                    }
                    $stock.attr('class', 'product attribute stock unavailable byte8-stock-radar-stock-oos');
                    $stock.html('<span class="value">' + $t('Out of stock') + '</span>');
                    return;
                }

                if ($stock.data('byte8-original-html')) {
                    $stock.html($stock.data('byte8-original-html'));
                    $stock.attr('class', $stock.data('byte8-original-class'));
                    $stock.removeData('byte8-original-html');
                    $stock.removeData('byte8-original-class');
                }
            },

            /**
             * Walk `jsonConfig.attributes` to find the simple product ID for
             * the OOS combination, then map it to the simple SKU using the
             * variantSkuMap that Block\Product\View\NotifyMe emits into the
             * notify-me widget's data-mage-init.
             */
            _byte8StockRadarResolveOosSimpleSku: function ($option) {
                var jsonConfig = this.options.jsonConfig;
                if (!jsonConfig || !jsonConfig.attributes) {
                    console.warn('[Byte8 StockRadar] resolve: no jsonConfig.attributes', jsonConfig);
                    return null;
                }

                var clickedAttrId = $option.parents('.swatch-attribute').data('attribute-id');
                var clickedOptionId = $option.data('option-id');

                // jsonConfig.attributes can arrive as either an object keyed
                // by attribute id ({"144":{...},"93":{...}}) or as a positional
                // array ([{id:144,...},{id:93,...}]). Magento has shipped both
                // shapes depending on version and plugins. Normalise by walking
                // entries and matching on each entry's own `id` field.
                var findAttr = function (target) {
                    var found = null;
                    Object.keys(jsonConfig.attributes).forEach(function (key) {
                        if (found) {
                            return;
                        }
                        var entry = jsonConfig.attributes[key];
                        if (!entry) {
                            return;
                        }
                        if (String(key) === String(target) || String(entry.id) === String(target)) {
                            found = entry;
                        }
                    });
                    return found;
                };

                var clickedAttr = findAttr(clickedAttrId);
                if (!clickedAttr) {
                    console.warn('[Byte8 StockRadar] resolve: attribute not found in jsonConfig', {clickedAttrId: clickedAttrId, available: Object.keys(jsonConfig.attributes)});
                    return null;
                }

                var clickedOption = (clickedAttr.options || []).filter(function (o) {
                    return String(o.id) === String(clickedOptionId);
                })[0];
                if (!clickedOption || !clickedOption.products) {
                    console.warn('[Byte8 StockRadar] resolve: option not found or no products', {clickedOptionId: clickedOptionId, optionsInAttr: clickedAttr.options});
                    return null;
                }

                var candidates = clickedOption.products.slice();
                console.log('[Byte8 StockRadar] resolve: candidates from clicked option', candidates);

                $(this.element).find('.swatch-attribute[data-option-selected]').each(function () {
                    var $sel = $(this);
                    var aid = $sel.data('attribute-id');
                    if (String(aid) === String(clickedAttrId)) {
                        return;
                    }
                    var oid = $sel.data('option-selected');
                    var attrConfig = findAttr(aid);
                    if (!attrConfig) {
                        return;
                    }
                    var matchOpt = (attrConfig.options || []).filter(function (o) {
                        return String(o.id) === String(oid);
                    })[0];
                    if (!matchOpt || !matchOpt.products) {
                        return;
                    }
                    candidates = candidates.filter(function (id) {
                        return matchOpt.products.indexOf(id) !== -1;
                    });
                    console.log('[Byte8 StockRadar] resolve: narrowed by attr ' + aid + ' option ' + oid, candidates);
                });

                if (candidates.length === 0) {
                    console.warn('[Byte8 StockRadar] resolve: no candidates after intersection — combo may not exist in jsonConfig');
                    return null;
                }

                var simpleProductId = candidates[0];

                var $radar = $('.byte8-stock-radar');
                if (!$radar.length) {
                    console.warn('[Byte8 StockRadar] resolve: .byte8-stock-radar not in DOM');
                    return null;
                }

                // Read variantSkuMap from data-byte8-variant-skus (which we
                // emit alongside data-mage-init in the phtml). We can't read
                // data-mage-init here because Magento removes the attribute
                // from the DOM after consuming it during widget bootstrap.
                var raw = $radar.attr('data-byte8-variant-skus');
                if (!raw) {
                    console.warn('[Byte8 StockRadar] resolve: .byte8-stock-radar has no data-byte8-variant-skus attribute');
                    return null;
                }
                try {
                    var map = JSON.parse(raw);
                    var sku = map[simpleProductId];
                    if (!sku) {
                        console.warn('[Byte8 StockRadar] resolve: variantSkuMap has no entry for product ' + simpleProductId, {map: map});
                        return null;
                    }
                    return sku;
                } catch (e) {
                    console.warn('[Byte8 StockRadar] resolve: data-byte8-variant-skus JSON parse failed', e);
                    return null;
                }
            }
        });

        return $.mage.SwatchRenderer;
    };
});
