/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery'
], function ($) {
    'use strict';

    return function (config, element) {
        /**
         * Only group rows when normal checkout redirected a mixed FFL cart here.
         */
        if (!config.groupedFflCheckout) {
            return;
        }

        /**
         * Add new class name to enable custom styling
         */
        $(element).addClass('ffl-shipping-address-table');
    };
});
