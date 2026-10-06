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

        dealerLicense: function (address) {
            var attributes = address && (address.extensionAttributes || address.extension_attributes);
            var custom = address && (address.customAttributes || address.custom_attributes);
            var license = Array.isArray(custom) ? custom.find(function (attribute) {
                return attribute.attribute_code === 'ffl_license' && attribute.value;
            }) : custom && custom.ffl_license;
            return (license && (license.value || license)) || (address && address.dealer_license) ||
                (attributes && attributes.ffl_license) || '';
        },

        isDealer: function (address) {
            var attributes = address && (address.extensionAttributes || address.extension_attributes);
            return !!(this.dealerLicense(address) || (address && address.ffl_dealer_data) ||
                (attributes && attributes.ffl_dealer_data));
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
                ? $t('Some of your items require shipment to an FFL dealer. You will need to order them separately.')
                : $t('Some items in your order must ship to an FFL dealer.');
            if (!unavailable && result.requiresLogin) {
                message += ' ' + $t('Sign in or create an account to continue with multiple shipping addresses, or return to the cart and checkout FFL-required items separately.');
            }
            confirm({
                title: $t('Shipping options'),
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
