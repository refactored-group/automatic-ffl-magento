/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Checkout/js/checkout-data',
    'RefactoredGroup_AutoFflCore/js/checkout/helper/shipping-mode'
], function ($, Component, ko, checkoutData, shippingMode) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'RefactoredGroup_AutoFflCore/cart/select-dealer-button',
            collectRecipientName: false,
            isRequiredFfl: true,
            recipient_firstname: '',
            recipient_lastname: ''
        },
        // These observables will be shared across all instances of this UI Component
        currentFflItemId: ko.observable(),
        currentRoutingState: ko.observable(''),
        currentFullAddress: ko.observable(),
        dealerAddress: ko.observableArray(),
        dealerDetails: ko.observableArray(),
        dealerAddressId: ko.observableArray(),
        recipientFirstName: ko.observableArray(),
        recipientLastName: ko.observableArray(),
        recipientEdited: ko.observableArray(),
        currentRecipientFirstName: ko.observable(),
        currentRecipientLastName: ko.observable(),
        currentRecipientAddressId: ko.observable(),
        currentRecipientOverride: ko.observable(false),
        dealerSelectionPending: ko.observable(false),
        dealerSelectionError: ko.observable(''),
        dealerRequiredSlots: {},
        fflButtonLabel: null,
        /** @inheritdoc */
        initialize: function () {
            this._super();
            var self = this;

            // Observables initialized here won't be shared across other instances of this component
            this.fflButtonLabel = ko.observable(null);
            this.shippingAddressId = ko.observable(this.isRequiredFfl !== false
                ? this.selected_address_id || null : this.home_address_id);
            this.fflButtonLabel(this.selected_address_id ? 'Change Dealer' : 'Select Dealer');
            this.dealerAddress[this.dealerButtonId] = ko.observable(this.selected_address_label || '');
            this.dealerDetails[this.dealerButtonId] = ko.observable(this.selected_dealer_label || '');
            this.dealerAddressId[this.dealerButtonId] = ko.observable(this.selected_address_id || null);

            this.recipientFirstName[this.dealerButtonId] = ko.observable(this.recipient_firstname);
            this.recipientLastName[this.dealerButtonId] = ko.observable(this.recipient_lastname);
            this.recipientEdited[this.dealerButtonId] = ko.observable(!!this.recipient_edited);
            this.recipientActive = ko.observable(!!this.isRecipientLead);
            this.editingRecipient = ko.observable(!this.validRecipient(this.recipient_firstname, this.recipient_lastname));
            this.draftFirstName = ko.observable(this.recipient_firstname);
            this.draftLastName = ko.observable(this.recipient_lastname);
            this.recipientError = ko.observable('');
            this.recipientForm = $('#checkout_multishipping_form');
            this.recipientSubmitListener = function (event) {
                if (self.recipientActive() && self.editingRecipient()) {
                    event.preventDefault();
                    self.recipientError('Save the recipient first and last name to continue.');
                }
            };
            this.recipientForm.on('submit', this.recipientSubmitListener);
            this.recipientSubscriptions = ['recipientFirstName', 'recipientLastName'].map(function (field) {
                return self[field][self.dealerButtonId].subscribe(function (value) {
                    var id = self.dealerAddressId[self.dealerButtonId]();
                    if (!id) {
                        return;
                    }
                    Object.keys(self.dealerAddressId).filter(function (key) { return /^\d+$/.test(key); }).forEach(function (key) {
                        if (self.dealerAddressId[key]() === id && self[field][key]) {
                            self[field][key](value);
                        }
                    });
                });
            });

            // Update "Select Dealer button label when a dealer is selected
            this.dealerAddressId[this.dealerButtonId].subscribe(function (value) {
                self.fflButtonLabel(value ? 'Change Dealer' : 'Select Dealer');
                if (self.isRequiredFfl !== false) {
                    self.shippingAddressId(value);
                }
            });

            self.addDealerIdToStorage(this.dealerButtonId);

            return this;
        },
        /**
         * Adds the dealer ID to the localStorage
         */
        addDealerIdToStorage: function (id) {
            if (id === undefined) return;

            this.dealerRequiredSlots[id] = this.isRequiredFfl !== false;
            checkoutData.setFflQuoteLineItemId(Object.keys(this.dealerRequiredSlots)
                .filter(function (key) { return this.dealerRequiredSlots[key]; }, this).map(Number));
        },

        setDestination: function (addressId, state, required) {
            var self = this;
            this.isRequiredFfl = required;
            this.home_address_id = addressId;
            this.routingState = state;
            this.recipient_address_id = addressId;
            this.addDealerIdToStorage(this.dealerButtonId);
            if (required) {
                var shared = Object.keys(this.dealerRequiredSlots).find(function (key) {
                    return Number(key) !== Number(self.dealerButtonId) && self.dealerRequiredSlots[key] &&
                        self.dealerAddressId[key] && self.dealerAddressId[key]();
                });
                if (shared !== undefined) {
                    this.dealerAddress[this.dealerButtonId](this.dealerAddress[shared]());
                    this.dealerDetails[this.dealerButtonId](this.dealerDetails[shared]());
                    this.dealerAddressId[this.dealerButtonId](this.dealerAddressId[shared]());
                    this.recipientFirstName[this.dealerButtonId](this.recipientFirstName[shared]());
                    this.recipientLastName[this.dealerButtonId](this.recipientLastName[shared]());
                    this.recipientEdited[this.dealerButtonId](this.recipientEdited[shared]());
                }
                this.shippingAddressId(this.dealerAddressId[this.dealerButtonId]());
            } else {
                this.dealerAddressId[this.dealerButtonId](null);
                this.dealerAddress[this.dealerButtonId]('');
                this.dealerDetails[this.dealerButtonId]('');
                this.shippingAddressId(addressId);
            }
        },
        /**
         * Open modal and set current selected item
         */
        openSelectDealerModal: function () {
            if (this.dealerSelectionPending()) {
                return;
            }
            if (this.recipientForm.length && (this.editingRecipient() || !this.validRecipient(this.recipientFirstName[this.dealerButtonId](),
                this.recipientLastName[this.dealerButtonId]()))) {
                this.editingRecipient(true);
                this.recipientError('Enter and save the recipient first and last name before choosing a dealer.');
                return;
            }
            this.dealerSelectionError('');
            this.currentFflItemId(this.dealerButtonId);
            this.currentRoutingState(this.routingState || '');
            this.currentRecipientFirstName(this.recipientFirstName[this.dealerButtonId]());
            this.currentRecipientLastName(this.recipientLastName[this.dealerButtonId]());
            this.currentRecipientAddressId(this.recipient_address_id);
            this.currentRecipientOverride(this.recipientEdited[this.dealerButtonId]());
            $("#dealers-popup").modal("openModal");
        },

        validRecipient: function (first, last) {
            return [first, last].every(function (value) {
                return typeof value === 'string' && value.trim() && value.trim().length <= 255 &&
                    !/[\x00-\x1f\x7f]/.test(value);
            }) && !(first.trim().toLowerCase() === 'ffl' && last.trim().toLowerCase() === 'dealer');
        },

        changeRecipientName: function () {
            this.draftFirstName(this.recipientFirstName[this.dealerButtonId]());
            this.draftLastName(this.recipientLastName[this.dealerButtonId]());
            this.recipientError('');
            this.editingRecipient(true);
        },

        saveRecipientName: function () {
            if (!this.validRecipient(this.draftFirstName(), this.draftLastName())) {
                this.recipientError('Enter the recipient first and last name.');
                return;
            }
            var self = this;
            var first = this.draftFirstName().trim();
            var last = this.draftLastName().trim();
            Object.keys(this.dealerRequiredSlots).filter(function (id) {
                return self.dealerRequiredSlots[id];
            }).forEach(function (id) {
                self.recipientFirstName[id](first);
                self.recipientLastName[id](last);
                self.recipientEdited[id](true);
                if (self.dealerDetails[id]()) {
                    self.dealerAddress[id](first + ' ' + last + ', ' + self.dealerDetails[id]());
                }
            });
            this.editingRecipient(false);
            this.recipientError('');
            this.recipientForm.trigger('automaticffl:recipient-changed');
        },

        destroy: function () {
            this.recipientSubscriptions.forEach(function (subscription) { subscription.dispose(); });
            this.recipientForm.off('submit', this.recipientSubmitListener);
            return this._super();
        }
    });
});
