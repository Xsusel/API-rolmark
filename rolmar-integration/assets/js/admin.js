/**
 * Rolmar Integration - Admin JavaScript
 */
(function ($) {
    'use strict';

    var pollInterval = null;

    // Test Connection
    $('#rolmar-test-connection').on('click', function () {
        var $btn = $(this);
        var $result = $('#rolmar-test-result');

        $btn.prop('disabled', true);
        $result.removeClass('success error').text(rolmarAdmin.i18n.testing);

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_test_connection',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            if (response.success) {
                $result.addClass('success').text(response.data);
            } else {
                $result.addClass('error').text(response.data);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $result.addClass('error').text(rolmarAdmin.i18n.error);
        });
    });

    // Sync Products
    $('#rolmar-sync-products').on('click', function () {
        if (!confirm(rolmarAdmin.i18n.confirmSync)) {
            return;
        }

        var $btn = $(this);
        disableSyncButtons(true);
        showProgress(rolmarAdmin.i18n.syncing);

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_manual_sync',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            if (response.success) {
                startPolling();
            } else {
                showProgress(response.data, true);
                disableSyncButtons(false);
            }
        }).fail(function () {
            showProgress(rolmarAdmin.i18n.syncError, true);
            disableSyncButtons(false);
        });
    });

    // Sync Stock
    $('#rolmar-sync-stock').on('click', function () {
        disableSyncButtons(true);
        showProgress(rolmarAdmin.i18n.syncing);

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_sync_stock',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            if (response.success) {
                startPolling();
            } else {
                showProgress(response.data, true);
                disableSyncButtons(false);
            }
        }).fail(function () {
            showProgress(rolmarAdmin.i18n.syncError, true);
            disableSyncButtons(false);
        });
    });

    // Sync Photos
    $('#rolmar-sync-photos').on('click', function () {
        disableSyncButtons(true);
        showProgress(rolmarAdmin.i18n.syncing);

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_sync_photos',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            if (response.success) {
                startPolling();
            } else {
                showProgress(response.data, true);
                disableSyncButtons(false);
            }
        }).fail(function () {
            showProgress(rolmarAdmin.i18n.syncError, true);
            disableSyncButtons(false);
        });
    });

    function disableSyncButtons(disabled) {
        $('#rolmar-sync-products, #rolmar-sync-stock, #rolmar-sync-photos').prop('disabled', disabled);
    }

    function showProgress(message, isError) {
        var $progress = $('#rolmar-sync-progress');
        var $message = $('#rolmar-sync-message');

        $progress.show();
        $message.removeClass('success error');

        if (isError) {
            $message.addClass('error');
        }

        $message.text(message);
    }

    function updateProgressBar(percent) {
        $('#rolmar-progress-fill').css('width', Math.min(percent, 100) + '%');
    }

    function startPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }

        pollInterval = setInterval(function () {
            $.post(rolmarAdmin.ajaxUrl, {
                action: 'rolmar_get_sync_status',
                nonce: rolmarAdmin.nonce
            }, function (response) {
                if (!response.success) {
                    return;
                }

                var data = response.data;
                var progress = data.progress || {};

                if (progress.message) {
                    showProgress(progress.message);
                }

                if (progress.total > 0 && progress.processed > 0) {
                    var percent = (progress.processed / progress.total) * 100;
                    updateProgressBar(percent);
                }

                if (!data.in_progress || progress.status === 'done' || progress.status === 'error') {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    disableSyncButtons(false);

                    if (progress.status === 'done') {
                        showProgress(progress.message || rolmarAdmin.i18n.syncDone);
                        $('#rolmar-sync-message').addClass('success');
                        updateProgressBar(100);
                    } else if (progress.status === 'error') {
                        showProgress(progress.message || rolmarAdmin.i18n.syncError, true);
                    }
                }
            });
        }, 3000);
    }

    // Auto-poll if sync is already in progress on page load.
    $(document).ready(function () {
        if ($('#rolmar-sync-progress').is(':visible')) {
            startPolling();
        }
    });

})(jQuery);
