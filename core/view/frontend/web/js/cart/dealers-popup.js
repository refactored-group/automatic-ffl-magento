/**
 * Copyright © Refactored Group (https://www.refactored.group)
 * @copyright Copyright © 2022. All rights reserved.
 */
define([
    'jquery',
    'uiComponent',
    'ko',
    'Magento_Ui/js/modal/modal',
    'RefactoredGroup_AutoFflCore/js/cart/select-dealer-button',
    'uiRegistry',
], function ($, Component, ko, modal, dealerButton, checkoutData, createShippingAddress, selectShippingAddress, uiRegistry) {

    //@TODO: Move the address handling to a model
    return Component.extend({
        defaults: {
            template: 'RefactoredGroup_AutoFflCore/cart/dealers-popup'
        },
        currentFflItemId: ko.observable(),
        fflResults: ko.observable(),
        isSearchingMessageVisible: ko.observable(),
        isNoDealersMessageVisible: ko.observable(),
        isResultsVisible: ko.observable(),
        modalOptions: {
            type: 'slide',
            responsive: true,
            innerScroll: true,
            buttons: false
        },
        googleMap: null,
        mapPositionsList: [],
        mapMarkersList: [],
        localStorageKey: 'multishipping-addresses',
        blueMarkerUrl: 'http://maps.google.com/mapfiles/kml/paddle/blu-blank.png',
        redMarkerUrl: 'http://maps.google.com/mapfiles/kml/paddle/red-blank.png',

        /** @inheritdoc */
        initialize: function () {
            this._super();
            var self = this;

            // Watch for changes in the current selected item
            dealerButton().currentFflItemId.subscribe(function (value) {
                self.currentFflItemId(value);
            });

            // Hide messages
            self.isSearchingMessageVisible(false);
            self.isNoDealersMessageVisible(false);
            self.isResultsVisible(false);

            // Initialize local storage
            this.initLocalStorage();

            return this;
        },
        onEnter: function(data, event){
            event.keyCode === 13 && this.getFflResults();
            return true;
        },
        /**
         * Initialize local storage
         */
        initLocalStorage: function () {
            var addresses = window.localStorage.getItem(self.localStorageKey);
            if (!addresses || typeof window.checkoutConfig === 'undefined' || checkoutConfig.customerData.is_ffl != 1) {
                window.localStorage.setItem(self.localStorageKey, '{}');
            }
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

            for (var i = 0, LtLgLen = self.mapPositionsList.length; i < LtLgLen; i++) {
                bounds.extend(self.mapPositionsList[i]);
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
             * See \RefactoredGroup\AutoFflCheckoutMultiShipping\Controller\Index\Index
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

                    // Save new address into the local storage
                    self.saveToLocalStorage(parsedResult);

                }
            });
        },
        /**
         * Save dealer address to the local storage
         * @param parsedResult
         */
        saveToLocalStorage: function (parsedResult) {
            var self = this;
            var addresses = window.localStorage.getItem(self.localStorageKey);
            addresses = JSON.parse(addresses);
            addresses[self.currentFflItemId()] = {
                name: parsedResult.name,
                id: parsedResult.id
            };
            window.localStorage.setItem(self.localStorageKey, JSON.stringify(addresses));
        },
        /**
         * Send API request to FFL and retrieve a list of dealers
         */
        getFflResults: function () {
            var self = this;
            var searchString = $('#ffl-input-search').val();
            var searchRadius = $('#ffl-miles-search').val();

            //Display searching for dealers message
            self.isSearchingMessageVisible(true);
            self.isNoDealersMessageVisible(false);
            self.isResultsVisible(false);

            $.ajax({
                url: self.ffl_api_url + '?location=' + searchString + '&radius=' + searchRadius,
                headers: {"store-hash": self.store_hash, "origin": window.location.origin},
                success: function (result) {
                    //Hide searching for dealers message
                    self.isSearchingMessageVisible(false);
                    if (result && result.dealers.length > 0) {
                        self.parseDealersResult(result.dealers);
                        self.centerMap();
                        self.isResultsVisible(true);
                        self.isNoDealersMessageVisible(false);
                    } else {
                        self.isSearchingMessageVisible(false);
                        self.isResultsVisible(false);
                        self.isNoDealersMessageVisible(true);
                        self.removeMarkersFromMap();
                    }
                },
                error: function (result) {
                    self.isSearchingMessageVisible(false);
                    self.isResultsVisible(false);
                    self.isNoDealersMessageVisible(true);
                    self.removeMarkersFromMap();
                }
            });
        },
        removeMarkersFromMap: function () {
            var self = this;

            //Clear all markers
            for (var i = 0; i < self.mapMarkersList.length; i++) {
                self.mapMarkersList[i].setMap(null);
            }

            // Clear all positions
            self.mapPositionsList = [];
        },
        /**
         * Parse API results and create markers on the map
         * @param dealers
         */
        parseDealersResult: function (dealers) {
            var self = this;

            //Clear all markers
            self.removeMarkersFromMap();

            $(dealers).each(function (i, dealer) {
                // Format address to display in the results list
                dealers[i].id = (i + 1).toString();
                dealers[i].index = i.toString();
                dealers[i].formatted_address = dealer.premise_street + ', ' + dealer.premise_city + ', ' + dealer.premise_state + ' ' + dealer.premise_zip;
                dealers[i].business_name_formatted = dealers[i].id + '. ' + dealers[i].business_name;

                if (dealers[i].preferred) {
                    dealers[i].icon_url = self.blueMarkerUrl;
                    dealers[i].class = 'ffl-dealer-preferred';
                } else {
                    dealers[i].icon_url = self.redMarkerUrl;
                    dealers[i].class = 'ffl-dealer';
                }
            });
            self.fflResults(dealers);

            $(dealers).each(function (i, dealer) {
                // Add marker to the map
                self.addMarker(dealers[i]);
            });
        },
        /**
         * Add a popup to the marker
         * @param marker
         * @param dealer
         */
        addPopupToMarker: function (marker, dealer) {
            var self = this;
            const contentString =
                '<div style="display: none"><div id="popupcontent' + dealer.index + '">' +
                '<div id="siteNotice' + dealer.index + '">' +
                "</div>" +
                '<h2 id="firstHeading" class="firstHeading">' + dealer.business_name_formatted + '</h2>' +
                '<div id="bodyContent">' +
                "<p>" + dealer.formatted_address + "</p>" +
                "<p><b>License: </b>" + dealer.license + "</p>" +
                '<p><a href="#" data-bind="{click: function() {selectDealer(' + dealer.index + ')}}">' +
                "Select this dealer</a> " +
                "</p>" +
                "</div>" +
                "</div></div>";
            $('#popupcontent' + dealer.index).remove();
            $("body").append(contentString);
            var domElement = document.getElementById('popupcontent' + dealer.index);
            ko.applyBindings(this, domElement);

            const infowindow = new google.maps.InfoWindow({
                content: domElement,
            });
            marker.addListener("click", () => {
                infowindow.open({
                    anchor: marker,
                    map: self.googleMap,
                    shouldFocus: false,
                });
            });
        },
        /**
         * Add marker to the map
         * @param location
         */
        addMarker: function (dealer) {
            var self = this;
            var marker = new google.maps.Marker({
                position: {lat: dealer.lat, lng: dealer.lng},
                map: self.googleMap,
                label: dealer.id,
                icon: {
                    url: dealer.icon_url,
                    labelOrigin: new google.maps.Point(33, 20)
                },
            });

            this.addPopupToMarker(marker, dealer);
            this.mapMarkersList.push(marker);
            self.mapPositionsList.push(new google.maps.LatLng(dealer.lat, dealer.lng));
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
                mapTypeControlOptions: {
                    mapTypeIds: []
                },
                fullscreenControl: false,
                panControl: false,
                streetViewControl: false,
                mapTypeId: 'roadmap',
            });

            this.getToastMessage();

            var controlDiv = document.getElementById('ffl-floating-toast');
            this.googleMap.controls[google.maps.ControlPosition.RIGHT_TOP].push(controlDiv);
        },
        getToastMessage: function () {
            var self = this;
            $.ajax({
                url: self.stores_endpoint,
                headers: {"origin": window.location.origin},
                success: function (result) {
                    if (typeof result.announcement !== undefined && result.announcement != null) {
                        //Set the message on the toast
                        $('#ffl-toast-message').html(result.announcement);
                    } else {
                        $('#ffl-floating-toast').hide();
                    }
                },
                error: function (result) {
                    console.log(result);
                    // hide the toast message
                    $('#ffl-floating-toast').hide();
                }
            });
        },
    });
});
