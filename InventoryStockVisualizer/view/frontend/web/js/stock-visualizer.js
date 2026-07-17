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
         * Boot the quantity widget. In instant mode the values load on page load, so a
         * skeleton bridges the fetch. In on-demand mode only the call-to-action is shown;
         * the values render already filled on click, so no skeleton is exposed.
         *
         * @private
         */
        _create: function () {
            this.$deferred = this.element.find('[data-sv-agg], .sva-body');

            if (this.options.mode === 'instant') {
                this._fetch();
            } else {
                this._renderCta();
            }
        },

        /**
         * Show only the call-to-action; the status word (in stock / out of stock) stays
         * visible because salability is known and cached server-side. The volatile numbers
         * stay hidden until the fetch returns, so no skeleton is shown before the click.
         *
         * @private
         */
        _renderCta: function () {
            var self = this,
                button = $('<button type="button" class="action stock-visualizer-cta"></button>')
                    .text($t('Check availability'));

            this.$deferred.hide();
            button.on('click', function () {
                button.prop('disabled', true).addClass('sv-cta-loading').text($t('Checking availability…'));
                self.$cta = button;
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

            this.element.addClass('sv-loading');
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
         * Fill the volatile numbers onto the server-rendered scaffold and reconcile the
         * cached status pill with the live salable quantity. The in-stock/out-of-stock
         * word is rendered by PHP and cached; on a failed fetch it is left untouched.
         *
         * @param {Object|null} data
         * @private
         */
        _fill: function (data) {
            var status = this.element.find('[data-sv-status]');

            this.element.removeClass('sv-loading');

            if (!data) {
                if (this.$cta) {
                    this.$cta.prop('disabled', false).removeClass('sv-cta-loading').text($t('Check availability'));
                    this.$cta = null;
                } else {
                    status.find('[data-sv-agg]').empty();
                    this.element.find('[data-sv-value]').empty();
                }

                return;
            }

            var salable = data.qty > 0;

            status.removeClass('level-high level-out')
                .addClass(salable ? 'level-high' : 'level-out');
            status.find('.sv-word').text(salable ? $t('In stock') : $t('Out of stock'));
            status.find('[data-sv-agg]').text(this._formatQty(data.qty));

            if (this.options.scope === 'per_source') {
                this._fillSources(data.sources || {});
            }

            this._revealDeferred();
        },

        /**
         * Reveal the now-filled numbers and drop the call-to-action (on-demand only).
         * In instant mode there is no call-to-action and the values are already visible.
         *
         * @private
         */
        _revealDeferred: function () {
            if (this.$cta) {
                this.$deferred.show();
                this.$cta.remove();
                this.$cta = null;
            }
        },

        /**
         * Fill per-source value cells and size each meter as a share of the total.
         * Empty sources are collapsed; when the whole breakdown would be empty, a single
         * note is shown so the panel is never an empty frame under the aggregate.
         *
         * @param {Object} sources
         * @private
         */
        _fillSources: function (sources) {
            var self = this,
                total = 0,
                visible = 0;

            $.each(sources, function (code, qty) {
                total += qty > 0 ? qty : 0;
            });

            this.element.find('[data-sv-source]').each(function () {
                var row = $(this),
                    code = row.attr('data-sv-source'),
                    qty = typeof sources[code] !== 'undefined' ? sources[code] : 0,
                    width = total > 0 && qty > 0 ? Math.round(qty / total * 100) : 0;

                row.find('[data-sv-value]').text(self._formatQty(qty));
                row.find('[data-sv-meter]').css('width', width + '%');

                if (self.options.hideEmptySources && qty <= 0) {
                    row.addClass('sv-collapsed');
                } else {
                    row.removeClass('sv-collapsed');
                    visible++;
                }
            });

            this.element.find('[data-sv-empty]').toggleClass('sv-collapsed', visible > 0);
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
