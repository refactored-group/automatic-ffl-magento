/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'uiComponent',
    'ko'
], function ($, Component, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Razoyo_AutoFflCore/cart/select-dealer-button'
        },
        // These observables will be shared across all instances of this UI Component
        currentFflItemId: ko.observable(),
        currentFullAddress: ko.observable(),
        dealerAddress: ko.observableArray(),
        dealerAddressId: ko.observableArray(),
        fflButtonLabel: null,
        localStorageKey: 'multishipping-addresses',
        /** @inheritdoc */
        initialize: function () {
            this._super();
            var self = this;

            // Observables initialized here won't be shared across other instances of this component
            this.fflButtonLabel = ko.observable(null);
            this.fflButtonLabel('Select Dealer');
            this.dealerAddress[this.dealerButtonId] = ko.observable();
            this.dealerAddressId[this.dealerButtonId] = ko.observable();

            // Initialize local storage
            this.initLocalStorage();

            // Update "Select Dealer" button label when a dealer is selected
            this.dealerAddressId[this.dealerButtonId].subscribe(function (value) {
                self.fflButtonLabel('Change Dealer');
            });

            //Verify if dealer address exists on local storage
            this.initAddresses()

            return this;
        },
        /**
         * Initialize addresses from local storage
         */
        initAddresses: function () {
            var self = this;
            var addresses = window.localStorage.getItem(self.localStorageKey);
            addresses = JSON.parse(addresses);
            if (typeof addresses[this.dealerButtonId] !== 'undefined') {
                this.dealerAddress[this.dealerButtonId](addresses[this.dealerButtonId].name);
                this.dealerAddressId[this.dealerButtonId](addresses[this.dealerButtonId].id);
            }
        },
        /**
         * Init local storage
         */
        initLocalStorage: function () {
            var self = this;
            var addresses = window.localStorage.getItem(self.localStorageKey);
            if (!addresses) {
                window.localStorage.setItem(self.localStorageKey, '{}');
            }
        },
        /**
         * Open modal and set current selected item
         */
        openSelectDealerModal: function () {
            this.currentFflItemId(this.dealerButtonId);
            $("#dealers-popup").modal("openModal");
        }
    });
});
