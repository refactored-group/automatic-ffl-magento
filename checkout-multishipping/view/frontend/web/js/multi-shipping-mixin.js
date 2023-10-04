/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'Magento_Checkout/js/action/update-shopping-cart',
], function ($, updateShoppingCart) {
    'use strict';

    const multiShippingMixin = {
        /**
         * 
         * Removes the custom validation function call
         * and just submits the form normally.
         */
        _addNewAddress: function () {
            $(this.options.addNewAddressFlag).val(1);
            this.element
                .off('submit',  updateShoppingCart.onSubmit)
                .on('submit', function () {
                    $(document.body).trigger('processStart');
                })
                .trigger('submit');
            return this._super();
        }
    };

    return function (targetWidget) {
        $.widget('mage.multiShipping', targetWidget, multiShippingMixin);
        return $.mage.multiShipping;
    };
});