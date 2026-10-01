define(['jquery', 'Magento_Ui/js/modal/confirm', 'mage/translate'], function ($, confirm, $t) {
    'use strict';

    return {
        addressData: function (address) {
            var data = {};
            ['firstname', 'lastname', 'company', 'street', 'city', 'postcode', 'telephone',
                'countryId', 'regionCode', 'regionId', 'customerAddressId'].forEach(function (field) {
                data[field] = address[field];
            });
            return data;
        },

        isDealer: function (address) {
            var attributes = address && address.extension_attributes;
            var custom = address && address.customAttributes;
            var license = Array.isArray(custom) ? custom.some(function (attribute) {
                return attribute.attribute_code === 'ffl_license' && attribute.value;
            }) : custom && custom.ffl_license;
            return !!(address && (license || address.dealer_license || address.ffl_dealer_data ||
                (attributes && (attributes.ffl_license || attributes.ffl_dealer_data))));
        },

        check: function (config, address, state) {
            return $.ajax({
                url: config.stateUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    form_key: config.formKey,
                    state: state || '',
                    address: address ? JSON.stringify(this.addressData(address)) : '{}'
                }
            });
        },

        continueTo: function (result) {
            if (result.route !== 'multishipping' && result.route !== 'unavailable') {
                window.location.assign(result.url);
                return;
            }
            var unavailable = result.route === 'unavailable';
            var message = unavailable
                ? $t('These items need separate shipping addresses. This store requires you to place separate orders for them.')
                : $t('Items requiring an FFL must ship to a licensed dealer. Your other items can ship to your address.');
            if (!unavailable && result.requiresLogin) {
                message += ' ' + $t('Sign in or create an account to continue with multiple shipping addresses. Your entered address will be kept.');
            }
            confirm({
                title: $t('Your order needs separate shipping addresses'),
                content: message,
                buttons: [{
                    text: $t('Back'),
                    class: 'action-secondary action-dismiss',
                    click: function (event) { this.closeModal(event); }
                }, {
                    text: unavailable ? $t('Return to cart') : $t('Continue to multishipping'),
                    class: 'action-primary action-accept',
                    click: function (event) { this.closeModal(event, true); }
                }],
                actions: { confirm: function () { window.location.assign(result.url); } }
            });
        }
    };
});
