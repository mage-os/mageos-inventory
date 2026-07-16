/**
 * Copyright 2026 Adobe
 * All Rights Reserved.
 */

define([
    'jquery',
    'mage/translate',
    'jquery-ui-modules/widget'
], function ($, $t) {
    'use strict';

    $.widget('mage.stockVisualizer', {
        options: {
            mode: 'on_demand',
            scope: 'aggregate',
            sku: '',
            hideEmptySources: true,
            ajaxUrl: ''
        },

        /**
         * Boot the quantity widget: fetch on load in instant mode, otherwise defer
         * behind a call-to-action. The label/scaffold is already server-rendered.
         *
         * @private
         */
        _create: function () {
            this.$body = this.element.find('.sv-status, .sva-body');

            if (this.options.mode === 'instant') {
                this._fetch();
            } else {
                this._renderCta();
            }
        },

        /**
         * Hide the values behind a call-to-action until the shopper asks.
         *
         * @private
         */
        _renderCta: function () {
            var self = this,
                button = $('<button type="button" class="action stock-visualizer-cta"></button>')
                    .text($t('Check availability'));

            this.$body.hide();
            button.on('click', function () {
                button.remove();
                self.$body.show();
                self._fetch();
            });
            this.element.append(button);
        },

        /**
         * Fetch the minimal quantity payload. `cache:true` avoids the global
         * ajaxSetup({cache:false}) buster so the shared cache can serve repeats.
         * Only the SKU is sent; the server resolves stock and product id from context.
         *
         * @private
         */
        _fetch: function () {
            var self = this;

            $.ajax({
                url: this.options.ajaxUrl,
                type: 'GET',
                dataType: 'json',
                cache: true,
                data: {
                    sku: this.options.sku
                }
            }).done(function (response) {
                self._fill(response && response.data ? response.data : null);
            }).fail(function () {
                self._fill(null);
            });
        },

        /**
         * Project the fetched quantities onto the server-rendered scaffold: the
         * aggregate status pill, the per-source values and the share-of-total meters.
         *
         * @param {Object|null} data
         * @private
         */
        _fill: function (data) {
            var self = this,
                status = this.element.find('[data-sv-status]');

            if (!data) {
                status.removeClass('level-high level-out').find('.sv-word').text($t('n/a'));
                status.find('[data-sv-agg]').text('');
                this.element.find('[data-sv-value]').text($t('n/a'));

                return;
            }

            status.removeClass('level-high level-out')
                .addClass(data.qty > 0 ? 'level-high' : 'level-out');
            status.find('.sv-word').text(data.qty > 0 ? $t('In stock') : $t('Out of stock'));
            status.find('[data-sv-agg]').text(this._formatQty(data.qty));

            if (this.options.scope === 'per_source') {
                this._fillSources(data.sources || {});
            }
        },

        /**
         * Fill per-source value cells and size each meter as a share of the total.
         *
         * @param {Object} sources
         * @private
         */
        _fillSources: function (sources) {
            var self = this,
                total = 0;

            $.each(sources, function (code, qty) {
                total += qty > 0 ? qty : 0;
            });

            this.element.find('[data-sv-source]').each(function () {
                var row = $(this),
                    code = row.attr('data-sv-source'),
                    qty = typeof sources[code] !== 'undefined' ? sources[code] : 0,
                    width = total > 0 && qty > 0 ? Math.round(qty / total * 100) : 0;

                if (self.options.hideEmptySources && qty <= 0) {
                    row.hide();

                    return;
                }
                row.show();
                row.find('[data-sv-value]').text(self._formatQty(qty));
                row.find('[data-sv-meter]').css('width', width + '%');
            });
        },

        /**
         * Format a quantity for display.
         *
         * @param {Number} qty
         * @return {String}
         * @private
         */
        _formatQty: function (qty) {
            return (Math.round(qty * 100) / 100).toString();
        }
    });

    return $.mage.stockVisualizer;
});
