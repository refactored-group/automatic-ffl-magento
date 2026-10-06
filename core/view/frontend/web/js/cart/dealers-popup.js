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
                type: 'popup',
                modalClass: 'automaticffl-dealer-modal',
                responsive: false,
                innerScroll: false,
                buttons: false,
                opened: function () {
                    self.modalActive = true;
                    self.selectionError('');
                    $('body').addClass('automaticffl-map-open');
                },
                closed: function () {
                    self.modalActive = false;
                    $('body').removeClass('automaticffl-map-open');
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
            var button = dealerButton();
            var selectedItemId = this.currentFflItemId();
            dealer.routingState = button.currentRoutingState();
            var replacedAddressId = button.dealerAddressId[selectedItemId]
                ? button.dealerAddressId[selectedItemId]() : null;
            var previousLabel = button.dealerAddress[selectedItemId]();
            var previousDetails = button.dealerDetails[selectedItemId]();
            var firstName = button.currentRecipientFirstName();
            var lastName = button.currentRecipientLastName();
            var previewLabel = [firstName + ' ' + lastName, dealer.company, dealer.address1,
                dealer.city, dealer.state + ' ' + dealer.postalCode].filter(Boolean).join(', ');

            function failed() {
                if (button.dealerAddress[selectedItemId]() === previewLabel) {
                    button.dealerAddress[selectedItemId](previousLabel);
                    button.dealerDetails[selectedItemId](previousDetails);
                }
                button.dealerSelectionError('The dealer address could not be saved. Please select the dealer again.');
                button.dealerSelectionPending(false);
                self.applyingSelection = false;
                $('#checkout_multishipping_form').trigger('automaticffl:dealer-save-failed');
            }

            button.dealerSelectionError('');
            button.dealerSelectionPending(true);
            button.dealerAddress[selectedItemId](previewLabel);
            button.dealerDetails[selectedItemId]([dealer.company, dealer.address1, dealer.city,
                dealer.state + ' ' + dealer.postalCode].filter(Boolean).join(', '));
            self.modalActive = false;
            $('#dealers-popup').modal('closeModal');

            var form = $('#checkout_multishipping_form');
            function start(selectionVersion) {
                var data = form.length ? form.serializeArray().filter(function (field) {
                    return field.name !== 'continue' && field.name !== 'new_address' && field.name !== 'form_key';
                }) : [];
                var selection = {
                    form_key: self.form_key,
                    ffl_dealer_data: JSON.stringify(dealer),
                    replaces_address_id: replacedAddressId,
                    license: dealer.license,
                    recipient_first_name: firstName,
                    recipient_last_name: lastName,
                    recipient_address_id: button.currentRecipientAddressId(),
                    recipient_override: button.currentRecipientOverride() ? 1 : 0
                };
                Object.keys(selection).forEach(function (name) { data.push({name: name, value: selection[name]}); });
                try {
                    $.ajax({
                        url: self.create_address_url,
                        type: 'post',
                        data: data
                    }).done(function (result) {
                        var address;
                        try {
                            address = typeof result === 'string' ? JSON.parse(result) : result;
                        } catch (_error) {
                            address = null;
                        }
                        if (!address || !address.id || !address.name) {
                            failed();
                            return;
                        }
                        // Destination changes made during the save determine the final group membership.
                        var groupedItemIds = checkoutData.getFflQuoteLineItemId();
                        groupedItemIds = Array.isArray(groupedItemIds) ? groupedItemIds : [selectedItemId];
                        groupedItemIds.forEach(function (itemId) {
                            if (dealerButton().dealerAddress[itemId] && dealerButton().dealerAddressId[itemId]) {
                                dealerButton().recipientFirstName[itemId](address.firstname || firstName);
                                dealerButton().recipientLastName[itemId](address.lastname || lastName);
                                dealerButton().dealerAddress[itemId](address.name);
                                dealerButton().dealerDetails[itemId](address.dealer_label || address.name);
                                dealerButton().dealerAddressId[itemId](address.id);
                            }
                        });
                        button.dealerSelectionPending(false);
                        form.trigger('automaticffl:dealer-selected', [{persisted: address.assignments_saved === true,
                            version: selectionVersion}]);
                        if (window.location.href.indexOf('multishipping/checkout/shipping') !== -1) {
                            window.location.reload();
                        }
                        self.applyingSelection = false;
                    }).fail(failed);
                } catch (_error) {
                    failed();
                }
            }
            if (form.length) {
                form.trigger('automaticffl:dealer-saving', [start]);
            } else {
                start(0);
            }
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
