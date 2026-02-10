/**
 * Rolmar Integration - Admin JavaScript
 */
(function ($) {
    'use strict';

    console.log('[Rolmar] Admin JavaScript załadowany (v1.0.3)');

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
        console.log('[Rolmar] Odświeżanie drzewa kategorii...');
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
            console.log('[Rolmar] Odpowiedź AJAX:', response);
            $btn.prop('disabled', false);
            $spinner.removeClass('is-active');

            if (response.success) {
                $('#rolmar-category-tree').html(response.data.html);
                $status.addClass('success').text(rolmarAdmin.i18n.treeLoaded);

                // Debug: show HTML structure
                var htmlSample = response.data.html.substring(0, 500);
                console.log('[Rolmar] Przykład HTML:', htmlSample);

                // Restore previously checked categories (will also expand their parents).
                restoreCheckedState();

                // Count and display number of categories.
                var totalNodes = $('.rolmar-tree-node').length;
                var expandedNodes = $('.rolmar-tree-node.rolmar-tree-open').length;
                var nestedLists = $('.rolmar-tree-list .rolmar-tree-list').length;
                console.log('[Rolmar] Załadowano ' + totalNodes + ' węzłów, ' + expandedNodes + ' rozwiniętych');
                console.log('[Rolmar] Zagnieżdżonych list: ' + nestedLists);
                console.log('[Rolmar] Strzałki: ' + $('.rolmar-tree-toggle').length);
                $status.append(' (' + totalNodes + ' kategorii, ' + expandedNodes + ' rozwiniętych)');
            } else {
                console.error('[Rolmar] Błąd ładowania drzewa:', response.data);
                $status.addClass('error').text(response.data || rolmarAdmin.i18n.treeError);
            }
        }).fail(function (jqXHR, textStatus, errorThrown) {
            console.error('[Rolmar] Błąd AJAX:', textStatus, errorThrown);
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

    // Collapse all nodes.
    $('#rolmar-collapse-all').on('click', function () {
        console.log('[Rolmar] Zwijanie wszystkich kategorii...');
        $('.rolmar-tree-node').removeClass('rolmar-tree-open');
        console.log('[Rolmar] Zwinięto ' + $('.rolmar-tree-node').length + ' węzłów');
    });

    // Expand all nodes.
    $('#rolmar-expand-all').on('click', function () {
        console.log('[Rolmar] Rozwijanie wszystkich kategorii...');
        $('.rolmar-tree-node').addClass('rolmar-tree-open');
        console.log('[Rolmar] Rozwinięto ' + $('.rolmar-tree-node').length + ' węzłów');
    });

    // Parent-child checkbox logic + sync hidden field.
    $(document).on('change', '.rolmar-cat-checkbox', function () {
        var $this = $(this);
        var isChecked = $this.is(':checked');

        // Find all descendant checkboxes in the child tree list (not including this checkbox).
        var $treeNode = $this.closest('.rolmar-tree-node');
        var $childList = $treeNode.children('.rolmar-tree-list');

        if ($childList.length) {
            // Check/uncheck all descendant checkboxes in child nodes.
            $childList.find('.rolmar-cat-checkbox').prop('checked', isChecked);
        }

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
        var allChecked = [];

        // Collect all checked paths.
        $('.rolmar-cat-checkbox:checked').each(function () {
            allChecked.push($(this).data('path'));
        });

        // Optimize: remove child paths if parent is fully checked (all children are checked).
        // This reduces storage - e.g., checking "TEGER" saves ["TEGER"] instead of 500 child paths.
        var optimized = [];

        for (var i = 0; i < allChecked.length; i++) {
            var path = allChecked[i];
            var shouldInclude = true;

            // Get checkbox for this path to check if it's indeterminate.
            var $thisCheckbox = $('.rolmar-cat-checkbox[data-path="' + path + '"]');

            // If this checkbox is indeterminate (partially checked), don't include it.
            // Its children will be included individually instead.
            if ($thisCheckbox.prop('indeterminate')) {
                shouldInclude = false;
            } else {
                // Check if any parent of this path is fully checked (not indeterminate).
                var parts = path.split('/');
                for (var j = 1; j < parts.length; j++) {
                    var parentPath = parts.slice(0, j).join('/');

                    // Find parent checkbox.
                    var $parentCheckbox = $('.rolmar-cat-checkbox[data-path="' + parentPath + '"]');
                    if ($parentCheckbox.length && $parentCheckbox.is(':checked')) {
                        // If parent is checked AND not indeterminate (all children checked),
                        // then this child path is redundant - the parent path covers it.
                        if (!$parentCheckbox.prop('indeterminate')) {
                            shouldInclude = false;
                            break;
                        }
                    }
                }
            }

            if (shouldInclude) {
                optimized.push(path);
            }
        }

        $('#rolmar_allowed_categories').val(JSON.stringify(optimized));

        // Update selection count display.
        updateSelectionCount(optimized.length);
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

    function updateSelectionCount(count) {
        var $counter = $('#rolmar-category-count');
        if (!$counter.length) {
            return;
        }

        if (count === 0) {
            $counter.html('<em>' + rolmarAdmin.i18n.allCategories + '</em>');
        } else {
            $counter.text(count + ' ' + (count === 1 ? rolmarAdmin.i18n.categorySelected : rolmarAdmin.i18n.categoriesSelected));
        }
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

        // Update initial selection count.
        var initialCount = JSON.parse($('#rolmar_allowed_categories').val() || '[]').length;
        updateSelectionCount(initialCount);
    });

})(jQuery);
