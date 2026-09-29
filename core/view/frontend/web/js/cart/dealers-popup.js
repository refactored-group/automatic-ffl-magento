/**
 * Shared dealer-map adapter for Magento checkout and multishipping.
 */
define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Ui/js/modal/modal',
    'RefactoredGroup_AutoFflCore/js/cart/select-dealer-button',
    'Magento_Checkout/js/checkout-data'
], function ($, Component, ko, modal, dealerButton, checkoutData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'RefactoredGroup_AutoFflCore/cart/dealers-popup'
        },
        currentFflItemId: ko.observable(),
        selectionError: ko.observable(''),
        modalActive: false,
        applyingSelection: false,

        initialize: function () {
            this._super();
            this.currentItemSubscription = dealerButton().currentFflItemId.subscribe(
                this.currentFflItemId.bind(this)
            );
            this.messageListener = this.onIframeMessage.bind(this);
            window.addEventListener('message', this.messageListener);
            return this;
        },

        renderDealersModal: function () {
            var self = this;
            modal({
                type: 'slide',
                responsive: true,
                innerScroll: true,
                buttons: false,
                opened: function () {
                    self.modalActive = true;
                    self.selectionError('');
                },
                closed: function () {
                    self.modalActive = false;
                }
            }, $('#dealers-popup'));
        },

        onIframeMessage: function (event) {
            var iframe = document.getElementById('automaticffl-map-iframe');
            if (!this.modalActive || this.applyingSelection || !iframe ||
                event.origin !== this.iframeOrigin || event.source !== iframe.contentWindow ||
                !event.data || typeof event.data !== 'object') {
                return;
            }

            if (event.data.type === 'closeModal') {
                $('#dealers-popup').modal('closeModal');
                return;
            }

            if (event.data.type !== 'dealerUpdate') {
                return;
            }

            var dealer = this.normalizeDealer(event.data.value);
            if (!dealer) {
                this.selectionError('The selected dealer data is incomplete. Please try again.');
                return;
            }

            this.applyingSelection = true;
            this.selectionError('');
            try {
                this.applySelectedDealer(dealer);
            } catch (_error) {
                this.selectionError('The dealer selection could not be applied. Please try again.');
                this.applyingSelection = false;
            }
        },

        normalizeDealer: function (value) {
            if (!value || typeof value !== 'object' || !/^[1-9]\d*$/.test(String(value.id)) ||
                typeof value.fflID !== 'string' || !value.fflID.trim() ||
                typeof value.address1 !== 'string' || !value.address1.trim() ||
                typeof value.city !== 'string' || !value.city.trim() ||
                typeof value.stateOrProvinceCode !== 'string' ||
                !/^[A-Z]{2}$/.test(value.stateOrProvinceCode) ||
                typeof value.postalCode !== 'string' || !value.postalCode.trim() ||
                value.countryCode !== 'US') {
                return null;
            }
            return {
                id: String(value.id),
                license: value.fflID.trim(),
                uuid: typeof value.uuid === 'string' ? value.uuid : null,
                expirationDate: /^\d{4}-\d{2}-\d{2}$/.test(value.expirationDate || '')
                    ? value.expirationDate : null,
                company: typeof value.company === 'string' ? value.company : '',
                firstName: typeof value.firstName === 'string' ? value.firstName : null,
                lastName: typeof value.lastName === 'string' ? value.lastName : null,
                phone: typeof value.phone === 'string' ? value.phone : '',
                address1: value.address1,
                address2: typeof value.address2 === 'string' ? value.address2 : '',
                city: value.city,
                state: value.stateOrProvinceCode,
                postalCode: value.postalCode,
                countryCode: 'US'
            };
        },

        applySelectedDealer: function (dealer) {
            var self = this;
            var selectedItemId = this.currentFflItemId();
            dealer.routingState = dealerButton().currentRoutingState();
            var groupedItemIds = checkoutData.getFflQuoteLineItemId();
            groupedItemIds = Array.isArray(groupedItemIds) ? groupedItemIds.slice() : [];
            var replacedAddressId = dealerButton().dealerAddressId[selectedItemId]
                ? dealerButton().dealerAddressId[selectedItemId]() : null;

            $.ajax({
                url: this.create_address_url,
                type: 'post',
                data: {
                    form_key: this.form_key,
                    ffl_dealer_data: JSON.stringify(dealer),
                    dealer_id: dealer.id,
                    replaces_address_id: replacedAddressId,
                    license: dealer.license,
                    uuid: dealer.uuid,
                    expiration_date: dealer.expirationDate,
                    business_name: dealer.company,
                    phone_number: dealer.phone,
                    premise_street: dealer.address1,
                    premise_street_2: dealer.address2,
                    premise_city: dealer.city,
                    premise_state: dealer.state,
                    premise_zip: dealer.postalCode,
                    recipient_first_name: dealer.firstName,
                    recipient_last_name: dealer.lastName
                }
            }).done(function (result) {
                var address;
                try {
                    address = typeof result === 'string' ? JSON.parse(result) : result;
                } catch (_error) {
                    address = null;
                }
                if (!address || !address.id || !address.name) {
                    self.selectionError('The dealer address could not be saved. Please try again.');
                    self.applyingSelection = false;
                    return;
                }
                (groupedItemIds.length ? groupedItemIds : [selectedItemId]).forEach(function (itemId) {
                    if (dealerButton().dealerAddress[itemId] && dealerButton().dealerAddressId[itemId]) {
                        dealerButton().dealerAddress[itemId](address.name);
                        dealerButton().dealerAddressId[itemId](address.id);
                    }
                });
                self.modalActive = false;
                $('#dealers-popup').modal('closeModal');
                if (window.location.href.indexOf('multishipping/checkout/shipping') !== -1) {
                    window.location.reload();
                }
                self.applyingSelection = false;
            }).fail(function () {
                self.selectionError('The dealer address could not be saved. Please try again.');
                self.applyingSelection = false;
            });
        },

        destroy: function () {
            window.removeEventListener('message', this.messageListener);
            if (this.currentItemSubscription) {
                this.currentItemSubscription.dispose();
            }
            return this._super();
        }
    });
});
