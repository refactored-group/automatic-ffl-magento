define(['jquery', 'mage/translate'], function ($) {
    'use strict';

    return function (_config, form) {
        var version = 0;
        var savedVersion = 0;
        var saving = false;
        var dealerPending = false;
        var submitAfterSave = false;
        var startDealer = null;
        var error = $(form).find('#ffl-routing-error');

        function dispatchDealer() {
            if (!saving && startDealer) {
                var start = startDealer;
                startDealer = null;
                start(version);
            }
        }

        function settled() {
            dispatchDealer();
            if (dealerPending) {
                return;
            }
            if (savedVersion !== version) {
                save();
            } else {
                $(form).attr('aria-busy', 'false');
                if (submitAfterSave) {
                    submitAfterSave = false;
                    $(form).trigger('submit');
                }
            }
        }

        function save() {
            if (saving || dealerPending || savedVersion === version) {
                return;
            }
            saving = true;
            var savingVersion = version;
            var data = $(form).serializeArray().filter(function (field) {
                return field.name !== 'continue' && field.name !== 'new_address';
            });
            data.push({name: 'continue', value: 0}, {name: 'new_address', value: 0},
                {name: 'ffl_async_routing', value: 1});
            error.prop('hidden', true);
            $(form).attr('aria-busy', 'true');
            $.ajax({url: form.action, type: 'POST', data: data, dataType: 'json'}).done(function (result) {
                saving = false;
                if (!result || !result.success) {
                    if (startDealer) {
                        dispatchDealer();
                        return;
                    }
                    failed(result && result.message);
                    return;
                }
                savedVersion = savingVersion;
                settled();
            }).fail(function (xhr) {
                saving = false;
                if (startDealer) {
                    dispatchDealer();
                    return;
                }
                if (savingVersion !== version) {
                    save();
                    return;
                }
                failed(xhr.responseJSON && xhr.responseJSON.message);
            });
        }

        function failed(message) {
            submitAfterSave = false;
            $(form).attr('aria-busy', 'false');
            error.text(message || $.mage.__('The shipping destination could not be saved. Please try again.'))
                .prop('hidden', false);
        }

        $(form).on('automaticffl:destination-changed automaticffl:recipient-changed', function () {
            version++;
            save();
        });
        $(form).on('automaticffl:dealer-selected', function (_event, result) {
            dealerPending = false;
            if (result && result.persisted) {
                savedVersion = Math.max(savedVersion, result.version);
            } else {
                version++;
            }
            settled();
        });
        $(form).on('automaticffl:dealer-saving', function (_event, start) {
            dealerPending = true;
            startDealer = start || null;
            $(form).attr('aria-busy', 'true');
            dispatchDealer();
        });
        $(form).on('automaticffl:dealer-save-failed', function () {
            dealerPending = false;
            startDealer = null;
            submitAfterSave = false;
            $(form).attr('aria-busy', saving ? 'true' : 'false');
            save();
        });
        $(form).on('submit', function (event) {
            if (saving || dealerPending || savedVersion !== version) {
                event.preventDefault();
                submitAfterSave = true;
                save();
            }
        });
    };
});
