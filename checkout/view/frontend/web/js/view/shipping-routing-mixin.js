define([
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/checkout-data',
    'Magento_Checkout/js/model/address-converter',
    'RefactoredGroup_AutoFflCore/js/checkout/routing',
    'mage/translate',
    'RefactoredGroup_AutoFflCore/js/checkout/destination',
    'Magento_Checkout/js/model/shipping-service',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Customer/js/customer-data',
    'Magento_Checkout/js/model/shipping-rate-service',
    'Magento_Checkout/js/action/create-shipping-address',
    'Magento_Customer/js/model/customer',
    'uiRegistry',
    'ko'
], function (quote, checkoutData, converter, routing, $t, destination, shippingService, selectShippingAddress, customerData, shippingRateService, createShippingAddress, customer, registry, ko) {
    'use strict';

    function addressFormData(data) {
        var form = JSON.parse(JSON.stringify(data || {}));
        // Saved customer addresses use null for empty optional text fields.
        // Magento's UI length validators require strings, including in a
        // hidden new-address form. Keep IDs and custom attribute types intact.
        ['firstname', 'middlename', 'lastname', 'prefix', 'suffix', 'company',
            'city', 'postcode', 'telephone', 'fax', 'vat_id', 'region', 'region_code'].forEach(function (field) {
            if (form[field] === null) {
                form[field] = '';
            }
        });
        if (form.street && typeof form.street === 'object') {
            Object.keys(form.street).forEach(function (line) {
                if (form.street[line] === null) {
                    form.street[line] = '';
                }
            });
        }
        return form;
    }

    return function (Component) {
        return Component.extend({
            initialize: function () {
                this.autofflDestination = destination;
                this.autofflHideShippingRegion = ko.observable(false);
                this.autofflConfig = window.checkoutConfig.autofflRouting || {};
                var savedForm = checkoutData.getShippingAddressFromData();
                if (this.autofflConfig.enabled && savedForm) {
                    // Native initialize restores checkout data before our own
                    // subscriptions run. Repair snapshots saved by older code.
                    checkoutData.setShippingAddressFromData(addressFormData(savedForm));
                }
                this._super();
                this.autofflChecking = false;
                this.autofflSwitching = false;
                this.autofflPreviousRequirement = destination.requiresDealer();
                shippingRateService.registerProcessor('autoffl-pending', {
                    getRates: function () { shippingService.setShippingRates([]); }
                });
                this.autofflRevision = destination.revision.subscribe(this.refreshDestination.bind(this));
                destination.setRequirement(destination.requiresDealer());
                if (destination.requiresDealer()) {
                    this.clearDealerAddress();
                }
                if (this.autofflConfig.enabled && this.autofflConfig.ammoOnly) {
                    this.autofflDirectoryData = customerData.get('directory-data');
                    this.autofflDirectorySubscription = this.autofflDirectoryData.subscribe(this.syncAmmoShippingState.bind(this));
                    this.autofflSubscription = quote.shippingAddress.subscribe(this.checkAmmoOnlyDestination.bind(this));
                    this.checkAmmoOnlyDestination(quote.shippingAddress());
                }
                var self = this;
                registry.async('checkoutProvider')(function (provider) {
                    if (self.autofflDestroyed) {
                        return;
                    }
                    self.autofflProvider = provider;
                    self.autofflRegionNamespace = self.name + '.autofflAddress';
                    provider.on('shippingAddress', function () {
                        self.syncAmmoShippingState();
                        self.syncDealerRecipient();
                    }, self.autofflRegionNamespace);
                    self.syncAmmoShippingState();
                    self.syncDealerRecipient();
                });
                return this;
            },

            syncDealerRecipient: function () {
                var address = quote.shippingAddress();
                var data = this.autofflProvider && this.autofflProvider.get('shippingAddress');
                if (!destination.requiresDealer() || !routing.isDealer(address) || !data ||
                    routing.dealerLicense(data) !== routing.dealerLicense(address)) {
                    return;
                }
                var firstname = String(data.firstname || '').trim();
                var lastname = String(data.lastname || '').trim();
                if (address.firstname !== firstname || address.lastname !== lastname) {
                    // Preserve the native address key and rate cache. Name edits
                    // update the selected card without creating another address.
                    selectShippingAddress(Object.assign({}, address, { firstname: firstname, lastname: lastname }));
                    checkoutData.setShippingAddressFromData(JSON.parse(JSON.stringify(data)));
                    checkoutData.setNewCustomerShippingAddress(JSON.parse(JSON.stringify(data)));
                    checkoutData.setFflDealerAddressIdentity(data);
                }
            },

            syncAmmoShippingState: function () {
                if (this.autofflSyncingRegion) {
                    return;
                }
                var provider = this.autofflProvider;
                var data = provider && provider.get('shippingAddress');
                var directory = this.autofflDirectoryData && this.autofflDirectoryData() || {};
                var regions = directory.US && directory.US.regions || {};
                var state = destination.state();
                var id = Object.keys(regions).filter(function (key) { return regions[key].code === state; })[0];
                var managed = !!(this.autofflConfig.enabled && this.autofflConfig.ammoOnly &&
                    !destination.requiresDealer() && !routing.isDealer(quote.shippingAddress()) &&
                    data && data.country_id === 'US');
                // The ammo dropdown owns the state input even while empty or
                // directory data is loading. Populate native fields only once
                // the selected state can be resolved to a Magento region.
                this.autofflHideShippingRegion(managed);
                if (!managed || !id) {
                    return;
                }
                // Keep Magento's required region fields populated for native
                // validation and rates, while the shopper uses one dropdown.
                this.autofflSyncingRegion = true;
                try {
                    var values = { region_id: id, region: regions[id].name, region_code: state };
                    Object.keys(values).forEach(function (key) {
                        if (String(data[key] || '') !== String(values[key])) {
                            provider.set('shippingAddress.' + key, values[key]);
                        }
                    });
                } finally {
                    this.autofflSyncingRegion = false;
                }
            },

            destinationKey: function (address) {
                return address ? [address.countryId, address.regionCode, address.regionId].join('|') : '';
            },

            hasDealerAddress: function () {
                return routing.isDealer(quote.shippingAddress());
            },

            rememberDealerBillingAddress: function () {
                var address = quote.billingAddress();
                if (!destination.requiresDealer() || !address || routing.isDealer(address)) {
                    return;
                }
                // Native shipping submission clears quote billing before its
                // resolver runs. Persist an explicit customer billing selection
                // so the resolver can restore it without using dealer shipping.
                var data = addressFormData(converter.quoteAddressToFormAddressData(address));
                checkoutData.setBillingAddressFromData(data);
                if (address.getType() === 'customer-address') {
                    checkoutData.setSelectedBillingAddress(address.getKey());
                } else {
                    checkoutData.setNewCustomerBillingAddress(data);
                    checkoutData.setSelectedBillingAddress('new-customer-billing-address');
                }
            },

            rememberCustomerAddress: function (address) {
                if (this.autofflPreviousRequirement) {
                    return;
                }
                var form = this.source && this.source.get && this.source.get('shippingAddress');
                var type = address && address.getType();
                var homeAddress = address && type !== 'autoffl-pending' && !routing.isDealer(address);
                var useForm = form && (this.isFormInline || type === 'new-customer-address');
                if (!homeAddress && !useForm) {
                    return;
                }
                var data = homeAddress ? converter.quoteAddressToFormAddressData(address) : {};
                // Magento keeps edits in checkoutProvider until native validation
                // creates the quote address. Capture those edits before hiding
                // the form, rather than restoring the previous quote values.
                if (useForm) {
                    data = Object.assign({}, data, form);
                }
                // checkoutProvider mutates nested form data as fields change.
                // The original home address must be an independent snapshot.
                data = addressFormData(data);
                delete data.is_ffl;
                delete data.dealer_license;
                delete data.ffl_dealer_data;
                ['custom_attributes', 'extension_attributes'].forEach(function (key) {
                    if (data[key]) {
                        delete data[key].ffl_license;
                        delete data[key].ffl_dealer_data;
                    }
                });
                this.autofflCustomerAddress = homeAddress ? address : null;
                checkoutData.setAutofflCustomerShippingAddress(data);
                checkoutData.setShippingAddressFromData(data);
                // Dealer delivery must not replace the shopper's billing address.
                if (!quote.billingAddress() && !checkoutData.getBillingAddressFromData()) {
                    checkoutData.setBillingAddressFromData(data);
                }
            },

            clearDealerAddress: function () {
                this.rememberCustomerAddress(quote.shippingAddress());
                checkoutData.setNewCustomerShippingAddress(null);
                checkoutData.setSelectedShippingAddress(null);
                checkoutData.setSelectedShippingRate(null);
                quote.shippingMethod(null);
                shippingService.setShippingRates([]);
                // Magento's native address subscribers require an Address object.
                // A pending address clears stale dealer data without requesting
                // shipping rates for an incomplete address.
                var pendingAddress = converter.formAddressDataToQuoteAddress({ country_id: 'US' });
                pendingAddress.getType = function () { return 'autoffl-pending'; };
                pendingAddress.canUseForBilling = function () { return false; };
                quote.shippingAddress(pendingAddress);
            },

            refreshDestination: function () {
                this.autofflSwitching = true;
                var address = quote.shippingAddress();
                this.rememberCustomerAddress(address);
                this.clearDealerAddress();
                if (!destination.requiresDealer()) {
                    var data = addressFormData(checkoutData.getAutofflCustomerShippingAddress());
                    var state = destination.state();
                    var directory = customerData.get('directory-data')() || {};
                    var regions = directory.US && directory.US.regions || {};
                    Object.keys(regions).some(function (id) {
                        if (regions[id].code !== state) {
                            return false;
                        }
                        data.region_id = id;
                        data.region = regions[id].name;
                        data.region_code = state;
                        return true;
                    });
                    data.country_id = 'US';
                    // A state selected in the routing dropdown is also the state
                    // shown in Magento's restored native shipping form.
                    checkoutData.setShippingAddressFromData(data);
                    if (this.source) {
                        this.source.set('shippingAddress', data);
                    }
                    var original = this.autofflCustomerAddress;
                    var restored = original && original.getType() === 'customer-address' &&
                        original.regionCode === state ? original : (customer.isLoggedIn()
                            ? createShippingAddress(data) : converter.formAddressDataToQuoteAddress(data));
                    // Guest home delivery uses the inline form. Adding this
                    // address to addressList also renders a duplicate card.
                    selectShippingAddress(restored);
                    checkoutData.setSelectedShippingAddress(restored.getKey());
                }
                this.autofflDestinationKey = this.destinationKey(quote.shippingAddress());
                this.autofflPreviousRequirement = destination.requiresDealer();
                this.autofflSwitching = false;
                this.errorValidationMessage(false);
            },

            checkAmmoOnlyDestination: function (address) {
                var key = this.destinationKey(address);
                if (this.autofflSwitching || this.autofflPreviousRequirement || !address || address.getType() === 'autoffl-pending' || routing.isDealer(address) ||
                    address.countryId !== 'US' || (!address.regionCode && !address.regionId) ||
                    key === this.autofflDestinationKey) {
                    return;
                }
                this.autofflDestinationKey = key;
                var state = address.regionCode;
                if (!state && address.regionId) {
                    var directory = customerData.get('directory-data')() || {};
                    var region = directory.US && directory.US.regions || {};
                    state = region[address.regionId] && region[address.regionId].code;
                }
                if (this.isFormInline && destination.state()) {
                    // Delayed native form/rate updates must not replace the
                    // destination chosen in the ammunition dropdown.
                    this.syncAmmoShippingState();
                    return;
                }
                this.rememberCustomerAddress(address);
                destination.update(state || '', address);
            },

            setShippingInformation: function () {
                this.syncAmmoShippingState();
                this.syncDealerRecipient();
                var self = this;
                var nativeProceed = this._super.bind(this);
                var proceed = function () {
                    self.rememberDealerBillingAddress();
                    return nativeProceed();
                };
                var address = quote.shippingAddress();
                if (destination.saving() || destination.error()) {
                    this.errorValidationMessage(destination.error() || $t('Saving your delivery state. Please try again in a moment.'));
                    return false;
                }
                if (destination.requiresDealer() && !routing.isDealer(address)) {
                    this.errorValidationMessage($t('Please select a licensed dealer for this order.'));
                    return false;
                }
                if (destination.requiresDealer() && (!String(address.firstname || '').trim() || !String(address.lastname || '').trim())) {
                    this.errorValidationMessage($t('Enter the recipient first and last name before continuing.'));
                    return false;
                }
                if (!this.autofflConfig.enabled || routing.isDealer(address)) {
                    return proceed();
                }
                if (this.autofflChecking || !this.validateShippingInformation()) {
                    return false;
                }
                address = quote.shippingAddress();
                var key = this.destinationKey(address);
                var revision = destination.revision();
                this.autofflChecking = true;
                return routing.check(this.autofflConfig, address).done(function (result) {
                    if (revision !== destination.revision() || key !== self.destinationKey(quote.shippingAddress())) {
                        self.errorValidationMessage($t('Your delivery address changed. Please continue again.'));
                        return;
                    }
                    self.rememberCustomerAddress(address);
                    if (result.route === 'standard') {
                        if (result.requiresDealer === destination.requiresDealer()) {
                            proceed();
                        } else {
                            destination.setRequirement(!!result.requiresDealer);
                            self.refreshDestination();
                        }
                    } else {
                        routing.continueTo(result);
                    }
                }).fail(function (response) {
                    self.errorValidationMessage(response.responseJSON && response.responseJSON.error ||
                        $t('Ammunition shipping could not be checked. Please try again.'));
                }).always(function () { self.autofflChecking = false; });
            },

            destroy: function () {
                this.autofflDestroyed = true;
                if (this.autofflProvider) {
                    this.autofflProvider.off(this.autofflRegionNamespace);
                }
                if (this.autofflDirectorySubscription) {
                    this.autofflDirectorySubscription.dispose();
                }
                this.autofflRevision.dispose();
                if (this.autofflSubscription) {
                    this.autofflSubscription.dispose();
                }
                return this._super();
            }
        });
    };
});
