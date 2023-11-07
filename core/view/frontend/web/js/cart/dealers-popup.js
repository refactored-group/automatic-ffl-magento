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
    'Magento_Checkout/js/checkout-data'
], function ($, Component, ko, modal, dealerButton, checkoutData) {

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
        currentInfowindow: false,
        blueMarkerUrl: 'https://maps.google.com/mapfiles/kml/paddle/blu-blank.png',
        redMarkerUrl: 'https://maps.google.com/mapfiles/kml/paddle/red-blank.png',

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

            return this;
        },
        onEnter: function(data, event){
            event.keyCode === 13 && this.getFflResults();
            return true;
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
                    /**
                     * Assign the address to the elements on the Cart page.
                     * 
                     * First, it checks if the "Check Out with Multiple Addresses" is clicked.
                     */
                    if (checkoutData.isProceedToCheckoutWithMultipleAddresses()) {
                        /**
                         * Default to setting the dealer address to individual text input.
                         */
                        dealerButton().dealerAddress[self.currentFflItemId()](parsedResult.name);
                        dealerButton().dealerAddressId[self.currentFflItemId()](parsedResult.id);
                    } else {
                        /**
                         * If true, fetch the row index of FFL items from localStorage.
                         * Then iterate through these items and assign the value of the ID
                         * of the dealer address.
                         */
                        if (checkoutData.getFflQuoteLineItemId().length) {
                            checkoutData.getFflQuoteLineItemId().forEach(element => {
                                dealerButton().dealerAddress[element](parsedResult.name);
                                dealerButton().dealerAddressId[element](parsedResult.id);
                            });
                        }
                    }

                    // If we are on the multi-shipping checkout shipping page, reload
                    if (window.location.href.includes('multishipping/checkout/shipping')) {
                        location.reload();
                    }
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
         * Returns phone number in the format (xxx)-xxx-xxxx
         *
         * @param phoneNumberString
         * @returns {string|null}
         */
        formatPhoneNumber: function (phoneNumberString) {
            const cleaned = ('' + phoneNumberString).replace(/\D/g, '');
            const match = cleaned.match(/^(\d{3})(\d{3})(\d{4})$/);
            if (match) {
                return '(' + match[1] + ')' + match[2] + '-' + match[3];
            }
            return null;
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
                dealers[i].phone_number = self.formatPhoneNumber(dealers[i].phone_number);
                dealers[i].license = dealer.license;

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
                self.addMarker(dealers[i], i);
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
                '<div style="display: none"><div id="popupcontent' + dealer.index + '" class="popupContent">' +
                '<div id="siteNotice' + dealer.index + '">' +
                "</div>" +
                '<h2 id="firstHeading" class="firstHeading">' + dealer.business_name_formatted + '</h2>' +
                '<div id="bodyContent">' +
                "<p>" + dealer.formatted_address + "</p>" +
                '<p><b>Phone: </b><a href="tel:+1' + dealer.phone_number + '">' + dealer.phone_number + "</a></p>" +
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
                if (self.currentInfowindow) {
                    self.currentInfowindow.close();
                }
                infowindow.open({
                    anchor: marker,
                    map: self.googleMap,
                    shouldFocus: false,
                });
                self.currentInfowindow = infowindow;
            });
        },
        /**
         * Add marker to the map
         * @param location
         */
        addMarker: function (dealer, zIndex) {
            var self = this;
            var marker = new google.maps.Marker({
                position: {lat: dealer.lat, lng: dealer.lng},
                zIndex,
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

            this.initMapByCustomerLocation();
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
                    // hide the toast message
                    $('#ffl-floating-toast').hide();
                }
            });
        },
        /**
         * This function centers the Google map
         * based on the user's current location.
         * 
         * First, it detects the location using browser Geolocation API.
         * If the browser is denied to get the user's location,
         * the script will use the customer's shipping address.
         */
        initMapByCustomerLocation: function () {
            const self = this;

            // Check if navigator.geolocation is supported by the browser
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        self.fetchMapByCoordinates({
                            lat: position.coords.latitude,
                            lng: position.coords.longitude
                        });
                    }
                );
                navigator.permissions
                    .query({ name: "geolocation" })
                    .then((result) => {
                        if (result.state !== 'granted') {
                            self.fetchMapByCustomerShippingAddress();
                        }
                    });
            } else {
                self.fetchMapByCustomerShippingAddress();
            }
        },
        // Load map using longitude and latitude coordinates
        fetchMapByCoordinates: function (position) {
            const latlng = new google.maps.LatLng(position.lat, position.lng),
                  geocoder = new google.maps.Geocoder(),
                  self = this;
            geocoder.geocode({'latLng': latlng}, function (results, status) {
                if (status == google.maps.GeocoderStatus.OK) {
                    if (results[0]) {
                        let address = null;
                        for (j = 0; j < results[0].address_components.length; j++) {
                            if (results[0].address_components[j].types[0].includes('postal_code')) {
                                address = results[0].address_components[j].short_name;
                                $('body').find('#ffl-input-search').val(address /** contains postalCode */);
                            }
                        }
                        if (address) {
                            self.getFflResults();
                        }
                    }
                } else {
                    console.warn('Unable to get customer location using default shipping address.');
                }
            });
        },
        // Load map using customer default shipping address
        fetchMapByCustomerShippingAddress: function () {
            const self = this,
                  address = self.customer_address;
            if (!address) {
                console.warn('Unable to get customer location using default shipping address.');
            } else {
                $('body').find('#ffl-input-search').val(address /** contains postalCode */);
                self.getFflResults();
            }
        }
    });
});
