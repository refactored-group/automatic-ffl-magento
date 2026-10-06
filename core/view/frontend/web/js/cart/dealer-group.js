define(['jquery', 'uiRegistry'], function ($, registry) {
    'use strict';

    function sizeNotice(notice) {
        var height = Math.ceil(notice.getBoundingClientRect().height);
        if (height) {
            notice.closest('tr').style.setProperty('--automaticffl-notice-height', height + 'px');
        }
    }

    function refresh(element) {
        var table = $(element);
        var rows = table.find('tbody > tr.automaticffl-product-row');
        var requiredRows = rows.filter('.ffl-item');
        var lead = requiredRows.first().attr('data-dealer-slot');

        table.find('.automaticffl-dealer-slot').each(function () {
            var slot = $(this).attr('data-dealer-slot');
            $(this).prop('hidden', slot !== lead);
            registry.async('selectDealerButton-' + slot)(function (button) {
                button.recipientActive(slot === lead);
            });
        });
        rows.removeClass('automaticffl-ffl-group-start automaticffl-ffl-group-end');
        requiredRows.first().addClass('automaticffl-ffl-group-start');
        rows.each(function (index) {
            var row = $(this);
            row.find('.automaticffl-ffl-group-notice').prop('hidden', row.attr('data-dealer-slot') !== lead);
            if (row.attr('data-dealer-slot') === lead) {
                row.find('.automaticffl-ffl-group-notice').each(function () { sizeNotice(this); });
            }
            if (row.hasClass('ffl-item')) {
                row.attr('aria-describedby', 'automaticffl-ffl-shipment-' + lead);
            } else {
                row.removeAttr('aria-describedby');
            }
            if (row.hasClass('ffl-item') && !rows.eq(index + 1).hasClass('ffl-item')) {
                row.addClass('automaticffl-ffl-group-end');
            }
        });
    }

    function initialize(_config, element) {
        refresh(element);
        // Keep every column below the shared notice when it wraps or the viewport changes.
        if (typeof ResizeObserver !== 'undefined') {
            var observer = new ResizeObserver(function (entries) {
                entries.forEach(function (entry) { sizeNotice(entry.target); });
            });
            $(element).find('.automaticffl-ffl-group-notice').each(function () { observer.observe(this); });
        }
        $(element).closest('form').on('automaticffl:destination-changed automaticffl:dealer-selected', function () {
            refresh(element);
        });
    }

    initialize.refresh = refresh;
    return initialize;
});
