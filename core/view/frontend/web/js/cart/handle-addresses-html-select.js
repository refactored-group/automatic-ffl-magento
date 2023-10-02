/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'Magento_Checkout/js/checkout-data'
], function ($, checkoutData) {
    'use strict';

    return function (config, element) {
        /**
         * Attach an onChange event listener on the address select dropdown.
         * The value of all other select.ship_address elements will be based from this.
         */
        $(element).on('change', function (event) {
            if (!checkoutData.isFflProceedToCheckoutButtonPressed()) return;
            const id = $(this).val();
            $('body').find('select.ship_address').val(id);
        });

    };
});
