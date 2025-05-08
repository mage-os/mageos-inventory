/*
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
define([
    'Magento_Ui/js/form/components/fieldset',
    'ko'
], function (Fieldset, ko) {
    'use strict';

    /**
 * Copyright 2019 Adobe
 * All Rights Reserved.
 */
    return Fieldset.extend(ko).extend(
        {
            /**
             * Convert `visible` value from string ('1', '0') to bool (true, false)
             */
            initialize: function () {
                this._super();

                // eslint-disable-next-line vars-on-top
                var visible = this.visible;

                this.visible = ko.computed({
                    /**
                     * @returns {Boolean}
                     */
                    read: function () {
                        return visible();
                    },

                    /**
                     * @param {String} value
                     */
                    write: function (value) {
                        value = Boolean(value) === value ? value : Boolean(parseInt(value, 10));
                        visible(value);
                    },
                    owner: this
                });
                this.visible(visible());
            }
        }
    );
});
