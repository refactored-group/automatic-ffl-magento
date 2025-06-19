/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'RefactoredGroup_AutoFflCore/js/checkout/helper/shipping-mode'
], function ($, shippingMode) {
    'use strict';

    return function (config, element) {
        /**
         * If "Check Out with Multiple Addresses" is clicked, ignore
         */
        if (shippingMode.isMultishipping()) {
            return;
        }

        /**
         * Add new class name to enable custom styling
         */
        $(element).addClass('ffl-shipping-address-table');
    };
});
