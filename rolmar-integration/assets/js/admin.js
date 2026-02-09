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

    // --- Category Tree ---

    // Refresh category tree from API.
    $('#rolmar-refresh-tree').on('click', function () {
        var $btn = $(this);
        var $spinner = $('#rolmar-tree-spinner');
        var $status = $('#rolmar-tree-status');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $status.removeClass('success error').text(rolmarAdmin.i18n.loadingTree);

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_load_category_tree',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $('#rolmar-category-tree').html(response.data.html);
                $status.addClass('success').text(rolmarAdmin.i18n.treeLoaded);
                restoreCheckedState();
            } else {
                $status.addClass('error').text(response.data || rolmarAdmin.i18n.treeError);
            }
        }).fail(function () {
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');
            $status.addClass('error').text(rolmarAdmin.i18n.treeError);
        });
    });

    // Toggle tree node expand/collapse.
    $(document).on('click', '.rolmar-tree-toggle', function () {
        var $li = $(this).closest('.rolmar-tree-node');
        $li.toggleClass('rolmar-tree-open');
    });

    // Parent-child checkbox logic + sync hidden field.
    $(document).on('change', '.rolmar-cat-checkbox', function () {
        var $this = $(this);
        var isChecked = $this.is(':checked');

        // Check/uncheck all descendant checkboxes.
        $this.closest('.rolmar-tree-node').find('.rolmar-cat-checkbox').prop('checked', isChecked);

        // Update parent states (indeterminate / checked).
        updateParentCheckboxes($this);

        syncCategorySelection();
    });

    function updateParentCheckboxes($child) {
        var $parentLi = $child.closest('.rolmar-tree-list').closest('.rolmar-tree-node');
        if (!$parentLi.length) {
            return;
        }

        var $parentCheckbox = $parentLi.children('label').find('.rolmar-cat-checkbox');
        var $childCheckboxes = $parentLi.children('.rolmar-tree-list').find('.rolmar-cat-checkbox');
        var totalChildren = $childCheckboxes.length;
        var checkedChildren = $childCheckboxes.filter(':checked').length;

        if (checkedChildren === 0) {
            $parentCheckbox.prop('checked', false).prop('indeterminate', false);
        } else if (checkedChildren === totalChildren) {
            $parentCheckbox.prop('checked', true).prop('indeterminate', false);
        } else {
            $parentCheckbox.prop('checked', false).prop('indeterminate', true);
        }

        // Recurse up the tree.
        updateParentCheckboxes($parentCheckbox);
    }

    function syncCategorySelection() {
        var selected = [];
        $('.rolmar-cat-checkbox:checked').each(function () {
            selected.push($(this).data('path'));
        });
        $('#rolmar_allowed_categories').val(JSON.stringify(selected));
    }

    function restoreCheckedState() {
        var raw = $('#rolmar_allowed_categories').val();
        var allowed = [];
        try {
            allowed = JSON.parse(raw);
        } catch (e) {
            allowed = [];
        }

        if (!allowed || !allowed.length) {
            return;
        }

        // Check saved paths.
        $('.rolmar-cat-checkbox').each(function () {
            var path = $(this).data('path');
            if ($.inArray(path, allowed) !== -1) {
                $(this).prop('checked', true);
            }
        });

        // Update parent indeterminate states bottom-up.
        // Process deepest nodes first by iterating leaf checkboxes.
        $('.rolmar-cat-checkbox:checked').each(function () {
            updateParentCheckboxes($(this));
        });

        // Auto-expand nodes that have checked children.
        $('.rolmar-cat-checkbox:checked').each(function () {
            $(this).parents('.rolmar-tree-node').addClass('rolmar-tree-open');
        });
    }

    // Auto-poll if sync is already in progress on page load.
    $(document).ready(function () {
        if ($('#rolmar-sync-progress').is(':visible')) {
            startPolling();
        }

        // Restore category tree checkbox state on page load.
        if ($('#rolmar-category-tree .rolmar-cat-checkbox').length) {
            restoreCheckedState();
        }
    });

})(jQuery);
