/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */

define([
    'jquery',
    'Magento_Customer/js/customer-data',
    'jquery-ui-modules/widget'
], function ($, customerData) {
    'use strict';

    return function (widget) {

        $.widget('mage.multiShipping', widget, {
            _Rebuild: function () {
                return this._super();
            }
        });
    }
});
