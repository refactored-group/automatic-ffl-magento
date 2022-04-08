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
        /** @inheritdoc */
        initialize: function () {
            this._super();
            var self = this;

            // Observables initialized here won't be shared across other instances of this component
            this.fflButtonLabel = ko.observable(null);
            this.fflButtonLabel('Select Dealer');
            this.dealerAddress[this.dealerButtonId] = ko.observable();
            this.dealerAddressId[this.dealerButtonId] = ko.observable();

            // Update "Select Dealer" button label when a dealer is selected
            this.dealerAddressId[this.dealerButtonId].subscribe(function (value) {
                self.fflButtonLabel('Change Dealer');
            });

            return this;
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
