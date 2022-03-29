/**
 * Copyright © Razoyo (https://www.razoyo.com)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Ui/js/modal/modal',
    'Razoyo_AutoFflCheckoutMultiShipping/js/cart/select-dealer-button'
], function ($, Component, ko, modal, dealerButton) {
    'use strict';

    var LatLngList = [];

    return Component.extend({
        defaults: {
            template: 'Razoyo_AutoFflCheckoutMultiShipping/cart/dealers-popup'
        },
        currentFflItemId: ko.observable(),
        fflResults: ko.observable(),
        modalOptions: {
            type: 'slide',
            responsive: true,
            innerScroll: true,
            buttons: false
        },
        googleMap: null,

        /** @inheritdoc */
        initialize: function () {
            this._super();
            var self = this;

            // Watch for changes in the current selected item
            dealerButton().currentFflItemId.subscribe(function (value) {
                self.currentFflItemId(value);
            });

            return this;
        },
        /**
         * Render Modal UI and Google Maps
         */
        renderDealersModal: function () {
            var self = this;
            modal(this.modalOptions, $('#dealers-popup'));

            $.getScript(self.google_maps_url + '?key=' + self.google_maps_api_key)
                .done(function (script, textStatus) {
                    self.initMap();
                })
                .fail(function (jqxhr, settings, exception) {
                    console.log('Could not load GoogleMaps')
                });
        },
        /**
         * Center map after creating markers
         */
        centerMap: function () {
            var self = this;
            var bounds = new google.maps.LatLngBounds();

            for (var i = 0, LtLgLen = LatLngList.length; i < LtLgLen; i++) {
                bounds.extend(LatLngList[i]);
            }
            self.googleMap.fitBounds(bounds);
        },
        /**
         * Select a dealer, close the modal, and save the address
         * @param dealer
         */
        selectDealer: function (dealer) {
            var self = this;
            var selectedDealer = this.fflResults()[dealer];
            // Close Modal
            $("#dealers-popup").modal("closeModal");

            /**
             * Send a request to Magento and create the address in the backend.
             * This address won't be visible in the customer address book.
             *
             * See \Razoyo\AutoFflCheckoutMultiShipping\Controller\Index\Index
             */
            $.ajax({
                url: self.create_address_url,
                data: {...selectedDealer, ...{form_key: self.form_key}},
                type: 'post',
                success: function (result) {
                    var parsedResult = JSON.parse(result);
                    // Assign the address to the elements on the Cart page
                    dealerButton().dealerAddress[self.currentFflItemId()](parsedResult.name);
                    dealerButton().dealerAddressId[self.currentFflItemId()](parsedResult.id);
                }
            });
        },
        /**
         * Send API request to FFL and retrieve a list of dealers
         */
        getFflResults: function () {
            var self = this;
            var searchString = $('#ffl-input-search').val();
            var searchRadius = $('#ffl-miles-search').val();

            $.ajax({
                url: self.ffl_api_url + '?location=' + searchString + '&radius=' + searchRadius,
                headers: {"store-hash": self.store_hash},
                success: function (result) {

                    if (result.dealers.length > 0) {
                        self.parseDealersResult(result.dealers);
                        self.centerMap();
                    } else {
                        $('#ffl_no_dealers').show();
                    }
                }
            });
        },
        /**
         * Parse API results and create markers on the map
         * @param dealers
         */
        parseDealersResult: function (dealers) {
            var self = this;
            $(dealers).each(function (i, dealer) {
                // Format address to display in the results list
                dealers[i].formatted_address = dealer.premise_street + ', ' + dealer.premise_city + ', ' + dealer.premise_state + ' ' + dealer.premise_zip;

                // Add marker to the map
                self.addMarker({lat: dealer.lat, lng: dealer.lng});
            });

            self.fflResults(dealers);
        },
        /**
         * Add marker to the map
         * @param location
         */
        addMarker: function (location) {
            var self = this;
            new google.maps.Marker({
                position: location,
                map: self.googleMap,
            });
            LatLngList.push(new google.maps.LatLng(location.lat, location.lng));
        },
        /**
         * Init Google Maps
         */
        initMap: function () {
            // Init Google Maps
            const myLatLng = {lat: 40.363, lng: -95.044};
            this.googleMap = new google.maps.Map(document.getElementById("ffl-map"), {
                zoom: 4,
                center: myLatLng,
            });
        }
    });
});
