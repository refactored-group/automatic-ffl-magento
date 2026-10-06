define(['jquery', 'ko', 'RefactoredGroup_AutoFflCore/js/checkout/routing'], function ($, ko, routing) {
    'use strict';

    var checkout = window.checkoutConfig || {};
    var config = checkout.autofflRouting || {};
    var pending = null;
    var active = false;
    var sequence = 0;
    var destination = {
        state: ko.observable(config.selectedState || ''),
        requiresDealer: ko.observable(!!(checkout.customerData || {}).is_ffl),
        saving: ko.observable(false),
        error: ko.observable(''),
        revision: ko.observable(0),

        setRequirement: function (required) {
            if (checkout.customerData) {
                checkout.customerData.is_ffl = required ? 1 : 0;
            }
            $('body').toggleClass('automaticffl-requires-dealer', required);
            this.requiresDealer(required);
        },

        update: function (state, address) {
            this.state(state);
            this.error('');
            var current = ++sequence;
            if (!state) {
                pending = null;
                this.error('Select a delivery state.');
                this.revision(this.revision() + 1);
                return;
            }
            // Rules are projected from this cart's server analysis. The server still
            // verifies the destination before native checkout can advance.
            if (config.ammoOnly && Array.isArray(config.dealerStates)) {
                this.setRequirement(config.dealerStates.indexOf(state) !== -1);
            }
            this.revision(this.revision() + 1);
            pending = { state: state, address: address, sequence: current };
            this.saving(true);
            this.flush();
        },

        flush: function () {
            if (active || !pending) {
                return;
            }
            var change = pending;
            pending = null;
            active = true;
            // Serialize quote mutations and coalesce rapid changes. Aborting an
            // HTTP request does not stop Magento from saving an older state.
            routing.check(config, change.address, change.state).done(function (result) {
                if (change.sequence !== sequence) {
                    return;
                }
                var changed = destination.requiresDealer() !== !!result.requiresDealer;
                destination.setRequirement(!!result.requiresDealer);
                if (changed) {
                    destination.revision(destination.revision() + 1);
                }
                if (result.route !== 'standard') {
                    routing.continueTo(result);
                }
            }).fail(function (response) {
                if (change.sequence === sequence) {
                    destination.error(response.responseJSON && response.responseJSON.error ||
                        'The delivery state could not be saved. Please select the state again.');
                }
            }).always(function () {
                active = false;
                if (pending) {
                    destination.flush();
                } else {
                    destination.saving(false);
                }
            });
        }
    };
    return destination;
});
