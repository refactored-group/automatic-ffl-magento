/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'uiRegistry',
    'RefactoredGroup_AutoFflCore/js/checkout/helper/shipping-mode',
    'RefactoredGroup_AutoFflCore/js/cart/dealer-group',
    'mage/translate'
], function ($, registry, shippingMode, dealerGroup) {
    'use strict';

    return function (config, element) {
        if (config.routeAmmo) {
            var row = $(element).closest('tr');
            var picker = row.find('.automaticffl-ammo-address-picker');
            var summary = row.find('.automaticffl-ammo-ffl-summary');
            var reason = row.find('.automaticffl-ammo-ffl-reason');
            var required = false;
            var button;
            function render() {
                row.toggleClass('ffl-item', required).toggleClass('non-ffl-item', !required);
                picker.prop('hidden', required);
                summary.prop('hidden', !required);
                dealerGroup.refresh(row.closest('table')[0]);
            }
            function apply(save) {
                var policy = config.addressPolicies[element.value];
                required = !!(policy && policy.required);
                reason.text(required ? $.mage.__('Your %1 destination requires ammunition to ship to an FFL.')
                    .replace('%1', policy.stateName || policy.state) : '');
                if (button) {
                    button.setDestination(element.value, policy ? policy.state : '', required);
                }
                render();
                if (save) {
                    $(element.form).trigger('automaticffl:destination-changed');
                }
            }
            registry.async('selectDealerButton-' + config.dealerButtonId)(function (component) {
                button = component;
                apply(false);
            });
            row.find('.automaticffl-change-destination').on('click', function () {
                picker.prop('hidden', false);
                $(element).trigger('focus');
            });
            $(element).on('change', function () { apply(true); });
            $(element.form).on('automaticffl:dealer-selected', render);
            return;
        }
        /**
         * Attach an onChange event listener on the address select dropdown.
         * The value of all other select.ship_address elements will be based from this.
         */
        $(element).on('change', function (event) {
            if (!shippingMode.isMultishipping()) {
                const id = $(this).val();
                $('body').find('select.ship_address').val(id);
            }
        });

    };
});
