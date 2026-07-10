/**
 * Rolmar Integration - Admin JavaScript
 */
(function ($) {
    'use strict';

    console.log('[Rolmar] Admin JavaScript załadowany (v1.6.0)');

    var pollInterval = null;

    /**
     * Escape a string for safe interpolation into HTML (text or attribute context).
     * Debug views render strings coming from the wholesaler API — never trust them.
     */
    function esc(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    /**
     * Escape a value for use inside a double-quoted jQuery attribute selector,
     * e.g. $('[data-path="' + attrEscape(path) + '"]'). Category paths from the
     * API may contain quotes/backslashes which would otherwise break the
     * selector (and silently wipe the saved mapping).
     */
    function attrEscape(value) {
        return String(value === null || value === undefined ? '' : value)
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '\\"');
    }

    // --- WooCommerce Category Mapping — Searchable Tag Picker ---

    var wcCats = (rolmarAdmin && rolmarAdmin.wcCategories) || [];

    /**
     * Build the tag-picker widget HTML inside a .rolmar-cat-mapping container.
     */
    function initTagPicker($container) {
        if ($container.find('.rolmar-picker').length) return; // Already initialised.

        var path = $container.data('path');
        var html = '<div class="rolmar-picker" data-path="' + esc(path) + '">';
        html += '<div class="rolmar-picker-tags"></div>';
        html += '<div class="rolmar-picker-input-wrap">';
        html += '<input type="text" class="rolmar-picker-search" placeholder="Szukaj kategorii WC..." autocomplete="off" />';
        html += '</div>';
        html += '<div class="rolmar-picker-dropdown"></div>';
        html += '</div>';
        $container.empty().append(html);
    }

    /**
     * Render selected tags inside the picker.
     */
    function renderTags($picker, selectedIds) {
        var $tagsWrap = $picker.find('.rolmar-picker-tags');
        $tagsWrap.empty();

        selectedIds.forEach(function (id) {
            var cat = findCatById(id);
            if (!cat) return;
            var tag = '<span class="rolmar-picker-tag" data-id="' + id + '">';
            tag += '<span class="rolmar-picker-tag-text">' + $('<span>').text(cat.display).html() + '</span>';
            tag += '<span class="rolmar-picker-tag-remove" data-id="' + id + '">&times;</span>';
            tag += '</span>';
            $tagsWrap.append(tag);
        });
    }

    /**
     * Show filtered dropdown.
     */
    function showDropdown($picker, query) {
        var $dropdown = $picker.find('.rolmar-picker-dropdown');
        var selectedIds = getPickerSelectedIds($picker);
        query = (query || '').toLowerCase();

        var filtered = wcCats.filter(function (cat) {
            // Don't show already selected.
            if (selectedIds.indexOf(cat.id) !== -1) return false;
            if (!query) return true;
            return cat.display.toLowerCase().indexOf(query) !== -1 ||
                   cat.name.toLowerCase().indexOf(query) !== -1;
        });

        if (!filtered.length) {
            $dropdown.html('<div class="rolmar-picker-empty">' + (query ? 'Brak wyników' : 'Brak kategorii') + '</div>');
        } else {
            var html = '';
            // Limit display to first 15 matches for performance.
            var shown = filtered.slice(0, 15);
            shown.forEach(function (cat) {
                html += '<div class="rolmar-picker-option" data-id="' + cat.id + '">';
                html += $('<span>').text(cat.display).html();
                html += '</div>';
            });
            if (filtered.length > 15) {
                html += '<div class="rolmar-picker-more">...i ' + (filtered.length - 15) + ' więcej — wpisz dokładniej</div>';
            }
            $dropdown.html(html);
        }

        $dropdown.addClass('rolmar-picker-dropdown-open');
    }

    function hideDropdown($picker) {
        $picker.find('.rolmar-picker-dropdown').removeClass('rolmar-picker-dropdown-open').empty();
    }

    function findCatById(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < wcCats.length; i++) {
            if (wcCats[i].id === id) return wcCats[i];
        }
        return null;
    }

    function getPickerSelectedIds($picker) {
        var ids = [];
        $picker.find('.rolmar-picker-tag').each(function () {
            ids.push(parseInt($(this).data('id'), 10));
        });
        return ids;
    }

    /**
     * Show/hide mapping widget based on checkbox state.
     */
    function toggleMappingDropdown(path, checked) {
        var $container = $('.rolmar-cat-mapping[data-path="' + attrEscape(path) + '"]');
        var autoCreate = $('#rolmar_auto_create_categories').val();

        // When auto-create is on, never show mapping pickers.
        if (autoCreate === 'yes') {
            $container.hide();
            return;
        }

        if (checked) {
            initTagPicker($container);
            $container.show();
        } else {
            $container.hide();
        }
    }

    /**
     * Sync all category mappings to hidden field.
     */
    function syncCategoryMapping() {
        var mapping = {};
        $('.rolmar-picker').each(function () {
            var $picker = $(this);
            var path = $picker.data('path');
            var ids = getPickerSelectedIds($picker);
            if (ids.length > 0) {
                mapping[path] = ids;
            }
        });
        $('#rolmar_category_mapping').val(JSON.stringify(mapping));
    }

    /**
     * Restore saved mapping from hidden field.
     */
    function restoreMappingState() {
        var raw = $('#rolmar_category_mapping').val();
        var mapping = {};
        try { mapping = JSON.parse(raw) || {}; } catch (e) { mapping = {}; }

        $.each(mapping, function (path, wcIds) {
            var $container = $('.rolmar-cat-mapping[data-path="' + attrEscape(path) + '"]');
            if (!$container.length) return;

            initTagPicker($container);
            var $picker = $container.find('.rolmar-picker');
            renderTags($picker, wcIds);

            var $checkbox = $('.rolmar-cat-checkbox[data-path="' + attrEscape(path) + '"]');
            if ($checkbox.is(':checked')) {
                $container.show();
            }
        });
    }

    // --- Tag Picker Event Handlers ---

    // Focus search on click anywhere in picker.
    $(document).on('click', '.rolmar-picker', function (e) {
        if (!$(e.target).hasClass('rolmar-picker-tag-remove')) {
            $(this).find('.rolmar-picker-search').focus();
        }
    });

    // Search input -> show/filter dropdown.
    $(document).on('input', '.rolmar-picker-search', function () {
        var $picker = $(this).closest('.rolmar-picker');
        showDropdown($picker, $(this).val());
    });

    // Focus -> show dropdown.
    $(document).on('focus', '.rolmar-picker-search', function () {
        var $picker = $(this).closest('.rolmar-picker');
        showDropdown($picker, $(this).val());
    });

    // Click outside -> close dropdown.
    $(document).on('mousedown', function (e) {
        if (!$(e.target).closest('.rolmar-picker').length) {
            $('.rolmar-picker-dropdown').removeClass('rolmar-picker-dropdown-open').empty();
        }
    });

    // Select option from dropdown.
    $(document).on('mousedown', '.rolmar-picker-option', function (e) {
        e.preventDefault(); // Prevent blur on search input.
        var $picker = $(this).closest('.rolmar-picker');
        var id = parseInt($(this).data('id'), 10);
        var ids = getPickerSelectedIds($picker);
        ids.push(id);
        renderTags($picker, ids);
        var $search = $picker.find('.rolmar-picker-search');
        $search.val('');
        showDropdown($picker, '');
        syncCategoryMapping();
    });

    // Remove tag.
    $(document).on('click', '.rolmar-picker-tag-remove', function () {
        var $picker = $(this).closest('.rolmar-picker');
        $(this).closest('.rolmar-picker-tag').remove();
        syncCategoryMapping();
    });

    // Keyboard navigation in dropdown.
    $(document).on('keydown', '.rolmar-picker-search', function (e) {
        var $picker = $(this).closest('.rolmar-picker');
        var $dropdown = $picker.find('.rolmar-picker-dropdown');
        var $active = $dropdown.find('.rolmar-picker-option.active');

        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if ($active.length) {
                var $next = $active.removeClass('active').next('.rolmar-picker-option');
                if ($next.length) $next.addClass('active');
                else $dropdown.find('.rolmar-picker-option').first().addClass('active');
            } else {
                $dropdown.find('.rolmar-picker-option').first().addClass('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if ($active.length) {
                var $prev = $active.removeClass('active').prev('.rolmar-picker-option');
                if ($prev.length) $prev.addClass('active');
                else $dropdown.find('.rolmar-picker-option').last().addClass('active');
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if ($active.length) {
                $active.trigger('mousedown');
            }
        } else if (e.key === 'Escape') {
            hideDropdown($picker);
        } else if (e.key === 'Backspace' && !$(this).val()) {
            // Remove last tag on backspace in empty field.
            $picker.find('.rolmar-picker-tag').last().remove();
            syncCategoryMapping();
        }
    });

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
        updateProgressBar(0); // Reset stale width from a previous run.
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
        updateProgressBar(0);
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
        updateProgressBar(0);
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
    $(document).on('click', '.rolmar-tree-toggle', function (e) {
        e.preventDefault();
        e.stopPropagation();
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

    // Parent-child checkbox logic + sync hidden field + toggle mapping.
    // Uses PATH-BASED matching (not DOM traversal) for maximum reliability.
    $(document).on('change', '.rolmar-cat-checkbox', function (e) {
        var $this = $(this);
        var isChecked = $this.is(':checked');
        var path = $this.data('path');
        var pathPrefix = path + '/';

        console.log('[Rolmar] Checkbox change:', path, isChecked ? 'CHECKED' : 'UNCHECKED');

        // Find ALL descendant checkboxes by path prefix matching.
        // This is more reliable than DOM traversal.
        var descendantCount = 0;
        var autoCreate = $('#rolmar_auto_create_categories').val();

        $('.rolmar-cat-checkbox').each(function () {
            var childPath = $(this).data('path');
            if (childPath && childPath !== path && childPath.indexOf(pathPrefix) === 0) {
                $(this).prop('checked', isChecked).prop('indeterminate', false);
                descendantCount++;

                // Toggle mapping dropdown (skip if auto-create is on for performance).
                if (autoCreate !== 'yes') {
                    try {
                        toggleMappingDropdown(childPath, isChecked);
                    } catch (err) {
                        console.warn('[Rolmar] toggleMappingDropdown error for', childPath, err);
                    }
                }
            }
        });

        console.log('[Rolmar] Set', descendantCount, 'descendants to', isChecked ? 'CHECKED' : 'UNCHECKED', 'for:', path);

        // Toggle mapping dropdown for this checkbox.
        toggleMappingDropdown(path, isChecked);

        // Update parent states (indeterminate / checked).
        try {
            updateParentCheckboxes($this);
        } catch (err) {
            console.error('[Rolmar] updateParentCheckboxes error:', err);
        }

        syncCategorySelection();
        syncCategoryMapping();
    });

    // Build a lookup map: path → checkbox element (for fast access).
    function buildCheckboxMap() {
        var map = {};
        $('.rolmar-cat-checkbox').each(function () {
            var p = $(this).data('path');
            if (p) {
                map[p] = $(this);
            }
        });
        return map;
    }

    function updateParentCheckboxes($child) {
        var childPath = $child.data('path');
        if (!childPath || childPath.indexOf('/') === -1) {
            // Top-level category, no parent.
            return;
        }

        // Find parent path by removing last segment.
        var parts = childPath.split('/');
        parts.pop();
        var parentPath = parts.join('/');

        // Find parent checkbox by iterating (safe, no CSS.escape needed).
        var $parentCheckbox = null;
        $('.rolmar-cat-checkbox').each(function () {
            if ($(this).data('path') === parentPath) {
                $parentCheckbox = $(this);
                return false; // break
            }
        });

        if (!$parentCheckbox || !$parentCheckbox.length) {
            return;
        }

        // Count direct children of this parent by path prefix.
        var parentPrefix = parentPath + '/';
        var totalDirectChildren = 0;
        var checkedDirectChildren = 0;
        var parentDepth = parts.length;

        $('.rolmar-cat-checkbox').each(function () {
            var p = $(this).data('path');
            if (p && p.indexOf(parentPrefix) === 0) {
                var childParts = p.split('/');
                if (childParts.length === parentDepth + 1) {
                    totalDirectChildren++;
                    if ($(this).is(':checked')) {
                        checkedDirectChildren++;
                    }
                }
            }
        });

        if (checkedDirectChildren === 0) {
            $parentCheckbox.prop('checked', false).prop('indeterminate', false);
        } else if (checkedDirectChildren === totalDirectChildren) {
            $parentCheckbox.prop('checked', true).prop('indeterminate', false);
        } else {
            $parentCheckbox.prop('checked', false).prop('indeterminate', true);
        }

        // Recurse up the tree.
        updateParentCheckboxes($parentCheckbox);
    }

    function syncCategorySelection() {
        var allChecked = [];

        // Collect all checked (and not indeterminate) paths.
        $('.rolmar-cat-checkbox').each(function () {
            var $cb = $(this);
            if ($cb.is(':checked') && !$cb.prop('indeterminate')) {
                allChecked.push($cb.data('path'));
            }
        });

        // Optimize: remove child paths if parent is fully checked.
        // E.g., checking "TEGER" saves ["TEGER"] instead of all child paths.
        var optimized = [];

        for (var i = 0; i < allChecked.length; i++) {
            var path = allChecked[i];
            var coveredByParent = false;

            // Check if any ancestor path is also in the checked list.
            var parts = path.split('/');
            for (var j = 1; j < parts.length; j++) {
                var ancestorPath = parts.slice(0, j).join('/');
                if (allChecked.indexOf(ancestorPath) !== -1) {
                    coveredByParent = true;
                    break;
                }
            }

            if (!coveredByParent) {
                optimized.push(path);
            }
        }

        var jsonVal = JSON.stringify(optimized);
        $('#rolmar_allowed_categories').val(jsonVal);
        console.log('[Rolmar] syncCategorySelection —', allChecked.length, 'checked →', optimized.length, 'optimized paths saved:', jsonVal.substring(0, 300));

        // Update selection count display.
        updateSelectionCount(optimized.length);
    }

    function restoreCheckedState() {
        var raw = $('#rolmar_allowed_categories').val();
        console.log('[Rolmar] restoreCheckedState — raw hidden field value:', raw);
        var allowed = [];
        try {
            allowed = JSON.parse(raw);
        } catch (e) {
            allowed = [];
        }

        if (!allowed || !allowed.length) {
            console.log('[Rolmar] restoreCheckedState — no saved categories, skipping');
            restoreMappingState();
            return;
        }

        console.log('[Rolmar] restoreCheckedState — restoring', allowed.length, 'paths:', allowed);

        // Check saved paths.
        var matchCount = 0;
        $('.rolmar-cat-checkbox').each(function () {
            var path = $(this).data('path');
            if ($.inArray(path, allowed) !== -1) {
                $(this).prop('checked', true);
                matchCount++;
            }
        });
        console.log('[Rolmar] restoreCheckedState — matched', matchCount, 'of', allowed.length, 'paths in tree (' + $('.rolmar-cat-checkbox').length + ' total checkboxes)');

        // Also check all children of checked parent paths.
        allowed.forEach(function (parentPath) {
            $('.rolmar-cat-checkbox').each(function () {
                var p = $(this).data('path');
                if (p && p.indexOf(parentPath + '/') === 0) {
                    $(this).prop('checked', true);
                }
            });
        });

        // Update parent indeterminate states bottom-up.
        $('.rolmar-cat-checkbox:checked').each(function () {
            updateParentCheckboxes($(this));
        });

        // Show mapping dropdowns for checked categories.
        $('.rolmar-cat-checkbox:checked').each(function () {
            toggleMappingDropdown($(this).data('path'), true);
        });

        // Auto-expand nodes that have checked children.
        $('.rolmar-cat-checkbox:checked').each(function () {
            $(this).parents('.rolmar-tree-node').addClass('rolmar-tree-open');
        });

        // Restore saved WC category mapping.
        restoreMappingState();
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

    // Run Diagnostics Button
    $('#rolmar-run-diagnostics').on('click', function () {
        var $btn = $(this);
        var $result = $('#rolmar-diagnostics-result');

        $btn.prop('disabled', true).text('Diagnostyka w toku...');
        $result.html('<div class="rolmar-diag-loading"><span class="spinner is-active" style="float:none; margin:0 8px 0 0;"></span> Sprawdzanie... (moze to potrwac do 2 minut)</div>');

        $.ajax({
            url: rolmarAdmin.ajaxUrl,
            type: 'POST',
            timeout: 120000, // 2 minutes — diagnostics makes many external HTTP requests
            data: {
                action: 'rolmar_run_diagnostics',
                nonce: rolmarAdmin.nonce
            },
            success: function (response) {
            $btn.prop('disabled', false).text('Uruchom diagnostyke');

            if (response.success && response.data.checks) {
                var checks = response.data.checks;
                var html = '<div class="rolmar-diag-container">';
                html += '<h3 class="rolmar-diag-title">Wyniki diagnostyki</h3>';
                html += '<table class="rolmar-diag-table widefat">';
                html += '<thead><tr>';
                html += '<th style="width: 30px;"></th>';
                html += '<th style="width: 250px;">Test</th>';
                html += '<th>Wynik</th>';
                html += '<th>Uwagi</th>';
                html += '</tr></thead><tbody>';

                var okCount = 0;
                var warnCount = 0;
                var errCount = 0;

                checks.forEach(function (check) {
                    var icon = '';
                    var rowClass = '';
                    switch (check.status) {
                        case 'ok':
                            icon = '<span class="rolmar-diag-icon rolmar-diag-ok">&#10004;</span>';
                            rowClass = 'rolmar-diag-row-ok';
                            okCount++;
                            break;
                        case 'warning':
                            icon = '<span class="rolmar-diag-icon rolmar-diag-warn">&#9888;</span>';
                            rowClass = 'rolmar-diag-row-warn';
                            warnCount++;
                            break;
                        case 'error':
                            icon = '<span class="rolmar-diag-icon rolmar-diag-err">&#10008;</span>';
                            rowClass = 'rolmar-diag-row-err';
                            errCount++;
                            break;
                        case 'info':
                            icon = '<span class="rolmar-diag-icon rolmar-diag-info">i</span>';
                            rowClass = 'rolmar-diag-row-info';
                            break;
                    }

                    html += '<tr class="' + rowClass + '">';
                    html += '<td style="text-align:center;">' + icon + '</td>';
                    html += '<td><strong>' + esc(check.name) + '</strong></td>';
                    html += '<td style="max-width:350px; word-break:break-word;">' + esc(check.value) + '</td>';
                    // If hint is very long (URL test details), make it expandable.
                    // Truncate the RAW string, then escape — the other way
                    // around mis-measures length and can cut entities in half.
                    var rawHint = check.hint || '';
                    var hintHtml = esc(rawHint);
                    if (rawHint.length > 120) {
                        var shortHint = esc(rawHint.substring(0, 100)) + '...';
                        hintHtml = '<span class="rolmar-diag-hint-short">' + shortHint + '</span>';
                        hintHtml += '<details style="margin-top:4px;"><summary style="cursor:pointer;font-size:11px;color:#0073aa;">Pokaz szczegoly</summary>';
                        hintHtml += '<div style="margin-top:6px;font-size:11px;line-height:1.6;white-space:pre-wrap;word-break:break-all;background:#f9f9f9;padding:8px;border:1px solid #e0e0e0;border-radius:3px;">' + esc(rawHint.replace(/ \|\| /g, '\n')) + '</div></details>';
                    }
                    html += '<td style="color:#666; font-size:12px; max-width:300px;">' + hintHtml + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';

                // Summary bar
                html += '<div class="rolmar-diag-summary">';
                if (errCount > 0) {
                    html += '<span class="rolmar-diag-badge rolmar-diag-badge-err">' + errCount + ' blad(y)</span> ';
                }
                if (warnCount > 0) {
                    html += '<span class="rolmar-diag-badge rolmar-diag-badge-warn">' + warnCount + ' ostrzezenie(a)</span> ';
                }
                html += '<span class="rolmar-diag-badge rolmar-diag-badge-ok">' + okCount + ' OK</span>';

                if (errCount === 0 && warnCount === 0) {
                    html += '<p class="rolmar-diag-allgood">Wszystko dziala poprawnie! Mozesz uruchomic synchronizacje zdjec.</p>';
                } else if (errCount > 0) {
                    html += '<p class="rolmar-diag-problem">Wykryto problemy. Napraw bledy zaznaczone na czerwono przed synchronizacja.</p>';
                }
                html += '</div></div>';

                $result.html(html);
            } else {
                $result.html('<div class="notice notice-error"><p>Blad: ' + esc(response.data.message || 'Nieznany blad') + '</p></div>');
            }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $btn.prop('disabled', false).text('Uruchom diagnostyke');
                var msg = 'Blad polaczenia z serwerem.';
                if (textStatus === 'timeout') {
                    msg = 'Przekroczono czas oczekiwania (120s). Serwer moze miec problemy z polaczeniami wychodzacymi — kazdy test czeka na odpowiedz zewnetrzna.';
                } else if (textStatus === 'error' && jqXHR.status) {
                    msg = 'Blad HTTP ' + jqXHR.status + ': ' + errorThrown;
                } else if (textStatus === 'parsererror') {
                    msg = 'Serwer zwrocil nieprawidlowa odpowiedz (parsererror). Sprawdz logi PHP.';
                }
                $result.html('<div class="notice notice-error"><p>' + msg + '</p></div>');
            }
        });
    });

    // Debug Images Button
    $('#rolmar-debug-images').on('click', function () {
        var $btn = $(this);
        var $result = $('#rolmar-debug-result');

        $btn.prop('disabled', true).text('Testowanie...');
        $result.html('<p>Pobieranie danych z API...</p>');

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_debug_images',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false).text('Testuj obrazki (pierwsze 5 produktów)');

            if (response.success && response.data.results) {
                var html = '<div class="notice notice-info" style="padding: 10px;">';
                html += '<p><strong>Znaleziono ' + response.data.results.length + ' produktów. Sprawdzanie URLi obrazków...</strong></p>';
                html += '<table class="widefat striped" style="margin-top: 10px; font-size: 12px;">';
                html += '<thead><tr>';
                html += '<th style="width: 80px;">Index</th>';
                html += '<th>Nazwa produktu</th>';
                html += '<th style="width: 350px;">Oryginalny URL</th>';
                html += '<th style="width: 350px;">Czysty URL</th>';
                html += '<th style="width: 60px;">HTTP</th>';
                html += '<th style="width: 100px;">Status</th>';
                html += '</tr></thead><tbody>';

                response.data.results.forEach(function (item) {
                    var statusColor = item.status === 'OK' ? 'green' : (item.status === 'EMPTY' ? 'orange' : 'red');
                    var statusText = esc(item.status);
                    if (item.error) {
                        statusText += '<br><small style="font-weight:normal;">' + esc(item.error) + '</small>';
                    }

                    html += '<tr>';
                    html += '<td><code>' + esc(item.index) + '</code></td>';
                    html += '<td style="font-size: 11px;">';
                    html += '<strong>' + esc(item.name) + '</strong><br>';
                    html += '<small style="color:#666;">SKU: ' + esc(item.sku) + '</small><br>';
                    if (item.alt_photos && item.alt_photos.length > 0) {
                        html += '<small style="color:blue;">⚠️ Ma ' + item.alt_photos.length + ' alternatywnych zdjęć!</small><br>';
                    }
                    if (item.all_fields && item.all_fields.length > 0) {
                        html += '<details style="margin-top:5px;"><summary style="cursor:pointer;font-size:10px;color:#666;">Pokaż wszystkie pola API (' + item.all_fields.length + ')</summary>';
                        html += '<code style="font-size:9px;">' + esc(item.all_fields.join(', ')) + '</code></details>';
                    }
                    html += '</td>';
                    html += '<td style="font-size: 10px; word-break: break-all; max-width: 350px;">' + (item.original_url ? esc(item.original_url) : '<em>brak</em>') + '</td>';
                    html += '<td style="font-size: 10px; word-break: break-all; max-width: 350px;">';
                    if (item.original_url && item.original_url !== item.cleaned_url) {
                        html += '<span style="background: #fff3cd; padding: 2px 4px; font-size: 9px;">ZMIENIONY</span><br>';
                    }
                    html += (item.cleaned_url ? esc(item.cleaned_url) : '<em>brak</em>');
                    if (item.alt_photos && item.alt_photos.length > 0) {
                        html += '<details style="margin-top:5px;"><summary style="cursor:pointer;font-size:9px;">Alternatywne zdjęcia (' + item.alt_photos.length + ')</summary>';
                        html += '<ul style="margin:5px 0;padding-left:15px;font-size:9px;">';
                        item.alt_photos.forEach(function(photo) {
                            html += '<li style="word-break:break-all;">' + esc(photo) + '</li>';
                        });
                        html += '</ul></details>';
                    }
                    html += '</td>';
                    html += '<td style="text-align: center;">' + esc(item.http_code || '-') + '</td>';
                    html += '<td style="color: ' + statusColor + '; font-weight: bold; text-align: center;">' + statusText + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                html += '<p style="margin-top: 15px;"><strong>Legenda:</strong> ';
                html += '<span style="color: green;">●</span> OK = obrazek istnieje (200) | ';
                html += '<span style="color: red;">●</span> FAIL = 404 lub inny błąd | ';
                html += '<span style="color: orange;">●</span> EMPTY = brak URL w API';
                html += '</p></div>';
                $result.html(html);
            } else {
                $result.html('<div class="notice notice-error"><p>Błąd: ' + esc(response.data.message || 'Nieznany błąd') + '</p></div>');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Testuj obrazki (pierwsze 5 produktów)');
            $result.html('<div class="notice notice-error"><p>Błąd połączenia z serwerem.</p></div>');
        });
    });

    // Debug getPhotos API Button
    $('#rolmar-debug-photos-api').on('click', function () {
        var $btn = $(this);
        var $result = $('#rolmar-debug-result');

        $btn.prop('disabled', true).text('Testowanie getPhotos...');
        $result.html('<p>Pobieranie danych z getPhotos API...</p>');

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_debug_photos_api',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false).text('Testuj getPhotos API ⭐');

            if (response.success && response.data.results) {
                var total = response.data.total_entries || 0;
                var html = '<div class="notice notice-success" style="padding: 10px;">';
                html += '<p><strong>✅ getPhotos API działa! Znaleziono ' + total + ' wpisów. Pokazuję pierwsze 5:</strong></p>';
                html += '<table class="widefat striped" style="margin-top: 10px; font-size: 12px;">';
                html += '<thead><tr>';
                html += '<th style="width: 100px;">SKU/Index</th>';
                html += '<th style="width: 80px;">Ilość zdjęć</th>';
                html += '<th>Pierwsze zdjęcie (URL)</th>';
                html += '<th style="width: 80px;">Test HTTP</th>';
                html += '<th style="width: 150px;">Produkt w WooCommerce</th>';
                html += '</tr></thead><tbody>';

                response.data.results.forEach(function (item) {
                    var statusColor = item.first_photo_status.includes('OK') ? 'green' : (item.first_photo_status.includes('BRAK') ? 'orange' : 'red');
                    var wcColor = item.wc_product_id ? 'green' : 'red';
                    var firstPhotoUrl = (item.photo_urls && item.photo_urls.length > 0) ? esc(item.photo_urls[0]) : '<em>brak</em>';

                    html += '<tr>';
                    html += '<td><code>' + esc(item.identifier) + '</code></td>';
                    html += '<td style="text-align: center;"><strong>' + esc(item.photo_count) + '</strong></td>';
                    html += '<td style="font-size: 10px; word-break: break-all; max-width: 400px;">' + firstPhotoUrl;

                    // Show all photo URLs in expandable section.
                    if (item.photo_urls && item.photo_urls.length > 1) {
                        html += '<details style="margin-top:5px;"><summary style="cursor:pointer;font-size:9px;">Wszystkie zdjęcia (' + item.photo_urls.length + ')</summary>';
                        html += '<ol style="margin:5px 0;padding-left:20px;font-size:9px;">';
                        item.photo_urls.forEach(function(url) {
                            html += '<li style="word-break:break-all;">' + esc(url) + '</li>';
                        });
                        html += '</ol></details>';
                    }
                    html += '</td>';

                    html += '<td style="text-align: center; color: ' + statusColor + '; font-weight: bold;">';
                    html += item.first_photo_http ? esc(item.first_photo_http) + '<br>' : '';
                    html += esc(item.first_photo_status) + '</td>';
                    html += '<td style="color: ' + wcColor + '; font-size: 11px;">' + esc(item.wc_status) + '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                html += '<p style="margin-top: 15px;"><strong>Legenda:</strong> ';
                html += '<span style="color: green;">●</span> OK = obrazek istnieje (200) | ';
                html += '<span style="color: red;">●</span> FAIL = 404 lub błąd | ';
                html += '<span style="color: orange;">●</span> BRAK = brak URL';
                html += '</p>';

                if (response.data.results.some(function(r) { return !r.wc_product_id; })) {
                    html += '<p style="color: red; font-weight: bold;">⚠️ UWAGA: Niektóre produkty z getPhotos NIE ISTNIEJĄ w WooCommerce! Najpierw uruchom "Importuj produkty".</p>';
                }

                html += '</div>';
                $result.html(html);
            } else {
                $result.html('<div class="notice notice-error"><p>Błąd: ' + esc(response.data.message || 'Nieznany błąd') + '</p></div>');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Testuj getPhotos API ⭐');
            $result.html('<div class="notice notice-error"><p>Błąd połączenia z serwerem.</p></div>');
        });
    });

    // Debug Existing Products Button
    $('#rolmar-debug-existing-products').on('click', function () {
        var $btn = $(this);
        var $result = $('#rolmar-debug-result');

        $btn.prop('disabled', true).text('⏳ Sprawdzanie...');
        $result.html('<p>Pobieranie Twoich produktów i sprawdzanie getPhotos API...</p>');

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_debug_existing_products',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false).text('🎯 Testuj TWOJE produkty');

            if (response.success && response.data.results) {
                var totalApiPhotos = response.data.total_api_photos || 0;
                var totalWcProducts = response.data.total_wc_products || 0;
                var html = '<div class="notice notice-success" style="padding: 10px;">';
                html += '<p><strong>✅ Sprawdzono ' + totalWcProducts + ' produktów z Twojego sklepu vs ' + totalApiPhotos + ' wpisów w getPhotos</strong></p>';
                html += '<table class="widefat striped" style="margin-top: 10px; font-size: 12px;">';
                html += '<thead><tr>';
                html += '<th style="width: 60px;">ID</th>';
                html += '<th>Nazwa produktu</th>';
                html += '<th style="width: 120px;">SKU</th>';
                html += '<th style="width: 150px;">Status w getPhotos</th>';
                html += '<th style="width: 60px;">Ilość foto</th>';
                html += '<th>Pierwsze zdjęcie</th>';
                html += '<th style="width: 80px;">HTTP Test</th>';
                html += '<th style="width: 100px;">Ma obrazek w WC?</th>';
                html += '<th style="width: 150px;">🔍 RAW API Data</th>';
                html += '</tr></thead><tbody>';

                var foundWithPhotos = 0;
                var foundNoPhotos = 0;
                var notFoundInApi = 0;

                response.data.results.forEach(function (item) {
                    var apiColor = 'red';
                    if (item.api_status.includes('Znaleziono ✅')) {
                        apiColor = 'green';
                        foundWithPhotos++;
                    } else if (item.api_status.includes('BRAK ZDJĘĆ')) {
                        apiColor = 'orange';
                        foundNoPhotos++;
                    } else {
                        notFoundInApi++;
                    }

                    var photoStatusColor = item.first_photo_status.includes('OK') ? 'green' : (item.first_photo_status === 'N/A' ? 'gray' : 'red');
                    var wcImageColor = item.has_wc_image.includes('TAK') ? 'green' : 'red';
                    var firstPhotoUrl = (item.photo_urls && item.photo_urls.length > 0) ? esc(item.photo_urls[0]) : '<em>brak</em>';

                    html += '<tr>';
                    html += '<td><strong>' + esc(item.product_id) + '</strong></td>';
                    html += '<td style="font-size: 11px;">' + esc(item.product_name) + '</td>';
                    html += '<td><code>' + esc(item.sku) + '</code></td>';
                    html += '<td style="color: ' + apiColor + '; font-weight: bold;">' + esc(item.api_status) + '</td>';
                    html += '<td style="text-align: center;"><strong>' + esc(item.photo_count) + '</strong></td>';
                    html += '<td style="font-size: 10px; word-break: break-all; max-width: 300px;">' + firstPhotoUrl;

                    // Show all photo URLs in expandable section.
                    if (item.photo_urls && item.photo_urls.length > 1) {
                        html += '<details style="margin-top:5px;"><summary style="cursor:pointer;font-size:9px;">Wszystkie (' + item.photo_urls.length + ')</summary>';
                        html += '<ol style="margin:5px 0;padding-left:20px;font-size:9px;">';
                        item.photo_urls.forEach(function(url) {
                            html += '<li style="word-break:break-all;">' + esc(url) + '</li>';
                        });
                        html += '</ol></details>';
                    }
                    html += '</td>';

                    html += '<td style="text-align: center; color: ' + photoStatusColor + '; font-weight: bold;">';
                    html += item.first_photo_http ? esc(item.first_photo_http) + '<br>' : '';
                    html += esc(item.first_photo_status) + '</td>';
                    html += '<td style="color: ' + wcImageColor + '; font-weight: bold; text-align: center;">' + esc(item.has_wc_image) + '</td>';

                    // RAW API Data column
                    html += '<td style="font-size: 10px;">';
                    if (item.api_status.includes('Znaleziono')) {
                        // Get the raw data from photo_index (we need to add this to the response)
                        html += '<details style="margin-top:5px;"><summary style="cursor:pointer;font-weight:bold;color:#2271b1;">📋 Pokaż RAW JSON</summary>';
                        html += '<pre style="margin:5px 0;padding:10px;background:#f5f5f5;border:1px solid #ddd;overflow:auto;max-height:300px;font-size:9px;font-family:monospace;">';
                        html += esc(JSON.stringify(item.raw_api_data || {}, null, 2));
                        html += '</pre></details>';
                    } else {
                        html += '<em style="color:#999;">Brak w API</em>';
                    }
                    html += '</td>';

                    html += '</tr>';
                });

                html += '</tbody></table>';

                // Summary statistics.
                html += '<div style="margin-top: 20px; padding: 10px; background: #f0f0f0; border-left: 4px solid #2271b1;">';
                html += '<p style="margin: 5px 0;"><strong>📊 Podsumowanie:</strong></p>';
                html += '<ul style="margin: 5px 0; padding-left: 20px;">';
                html += '<li><span style="color: green;">✅ Znalezione w getPhotos ze zdjęciami: <strong>' + foundWithPhotos + '</strong></span></li>';
                html += '<li><span style="color: orange;">🟠 Znalezione w getPhotos BEZ zdjęć: <strong>' + foundNoPhotos + '</strong></span></li>';
                html += '<li><span style="color: red;">❌ NIE znalezione w getPhotos: <strong>' + notFoundInApi + '</strong></span></li>';
                html += '</ul></div>';

                // Diagnosis.
                if (foundWithPhotos > 0) {
                    html += '<p style="margin-top: 15px; padding: 10px; background: #d4edda; border-left: 4px solid #28a745; color: #155724;"><strong>🎉 SUKCES!</strong> getPhotos ma zdjęcia dla Twoich produktów! Jeśli sync_photos nie działa, to problem jest w kodzie sync_photos() - naprawię to.</p>';
                } else if (foundNoPhotos > 0) {
                    html += '<p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; color: #856404;"><strong>⚠️ PROBLEM:</strong> Twoje produkty są w getPhotos, ale NIE MAJĄ zdjęć. Skontaktuj się z Rolmar - ich API nie zwraca obrazków dla tych SKU.</p>';
                } else {
                    html += '<p style="margin-top: 15px; padding: 10px; background: #f8d7da; border-left: 4px solid #dc3545; color: #721c24;"><strong>❌ PROBLEM:</strong> Żaden z Twoich produktów nie jest w getPhotos! Problem z dopasowaniem SKU lub getPhotos nie ma danych dla Twoich kategorii.</p>';
                }

                html += '</div>';
                $result.html(html);
            } else {
                $result.html('<div class="notice notice-error"><p>Błąd: ' + esc(response.data.message || 'Nieznany błąd') + '</p></div>');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('🎯 Testuj TWOJE produkty');
            $result.html('<div class="notice notice-error"><p>Błąd połączenia z serwerem.</p></div>');
        });
    });

    // Test Download Image Button
    $('#rolmar-test-download-image').on('click', function () {
        var $btn = $(this);
        var $result = $('#rolmar-download-test-result');

        $btn.prop('disabled', true).text('Pobieranie testowego zdjecia...');
        $result.html('<div class="notice notice-info" style="padding: 10px;"><span class="spinner is-active" style="float:none; margin:0 8px 0 0;"></span> Pobieram liste zdjec z API i probuje pobrac jedno zdjecie...</div>');

        $.ajax({
            url: rolmarAdmin.ajaxUrl,
            type: 'POST',
            timeout: 120000,
            data: {
                action: 'rolmar_test_download_image',
                nonce: rolmarAdmin.nonce
            },
            success: function (response) {
                $btn.prop('disabled', false).text('Pobierz testowe zdjecie');

                if (response.success) {
                    var d = response.data;
                    var html = '';
                    var okCount = 0;
                    var failCount = 0;
                    if (d.attempts) {
                        d.attempts.forEach(function (a) {
                            if (a.is_image) okCount++; else failCount++;
                        });
                    }

                    if (d.success) {
                        html += '<div class="notice notice-success" style="padding: 15px;">';
                        html += '<h3 style="margin-top:0; color: #00a32a;">&#10004; Znaleziono dzialajaca kombinacje!</h3>';
                    } else {
                        html += '<div class="notice notice-error" style="padding: 15px;">';
                        html += '<h3 style="margin-top:0; color: #d63638;">&#10008; Zadna kombinacja nie dziala</h3>';
                    }

                    // Summary for technician
                    html += '<div style="background:#f0f6fc; border:2px solid #2271b1; border-radius:6px; padding:15px; margin:10px 0;">';
                    html += '<h4 style="margin-top:0; color:#2271b1;">Podsumowanie testu:</h4>';
                    html += '<table style="border-collapse:collapse; width:100%;">';
                    html += '<tr><td style="padding:4px 10px 4px 0; font-weight:bold; white-space:nowrap;">Data/czas:</td><td>' + esc(d.timestamp) + '</td></tr>';
                    html += '<tr><td style="padding:4px 10px 4px 0; font-weight:bold; white-space:nowrap;">SKU:</td><td><code>' + esc(d.sku) + '</code></td></tr>';
                    // Link only http(s) URLs — esc() does not neutralize a
                    // javascript: scheme inside an href attribute.
                    var apiUrlCell = /^https?:\/\//i.test(d.original_url || '')
                        ? '<a href="' + esc(d.original_url) + '" target="_blank" rel="noopener noreferrer">' + esc(d.original_url) + '</a>'
                        : esc(d.original_url || '');
                    html += '<tr><td style="padding:4px 10px 4px 0; font-weight:bold; white-space:nowrap;">URL z API:</td><td style="word-break:break-all;">' + apiUrlCell + '</td></tr>';
                    html += '<tr><td style="padding:4px 10px 4px 0; font-weight:bold; white-space:nowrap;">IP serwera:</td><td>' + esc(d.server_ip) + '</td></tr>';
                    html += '<tr><td style="padding:4px 10px 4px 0; font-weight:bold; white-space:nowrap;">Testow:</td><td>' + (d.attempts ? d.attempts.length : 0) + ' (' + okCount + ' OK, ' + failCount + ' BLAD)</td></tr>';
                    if (d.success_combo) {
                        html += '<tr><td style="padding:4px 10px 4px 0; font-weight:bold; white-space:nowrap;">Dziala:</td><td><strong style="color:#00a32a;">' + esc(d.success_combo) + '</strong></td></tr>';
                    }
                    if (d.api_time_ms) {
                        html += '<tr><td style="padding:4px 10px 4px 0; font-weight:bold; white-space:nowrap;">Czas API:</td><td>' + esc(d.api_time_ms) + ' ms</td></tr>';
                    }
                    html += '</table>';
                    html += '</div>';

                    // Full matrix table (always open)
                    html += '<h4 style="margin:15px 0 5px;">Pelna matryca testow (URL &times; naglowki):</h4>';
                    html += '<table class="widefat striped" style="font-size:11px;">';
                    html += '<thead><tr>';
                    html += '<th>Wariant URL</th><th>Naglowki</th><th>HTTP</th><th>Rozmiar</th><th>Content-Type</th><th>CF-Cache</th><th>Czas</th><th>Obraz?</th><th>Pobierz</th><th>Blad/Tresc</th>';
                    html += '</tr></thead><tbody>';

                    if (d.attempts) {
                        d.attempts.forEach(function (a) {
                            var rowStyle = '';
                            if (a.is_image) rowStyle = 'background:#d4edda;';
                            else if (a.http_code === 200) rowStyle = 'background:#fff3cd;';
                            else if (a.http_code === 404) rowStyle = '';
                            else if (a.error) rowStyle = 'background:#f8d7da;';

                            html += '<tr style="' + rowStyle + '">';
                            html += '<td style="font-weight:bold;">' + esc(a.url_label || '-') + '</td>';
                            html += '<td>' + esc(a.hdr_label || '-') + '</td>';
                            html += '<td style="text-align:center; font-weight:bold;">' + esc(a.http_code || '-') + '</td>';
                            html += '<td style="text-align:center;">' + (a.size_kb ? esc(a.size_kb) + ' KB' : '-') + '</td>';
                            html += '<td style="font-size:10px;">' + esc(a.content_type || '-') + '</td>';
                            html += '<td style="font-size:10px;">' + esc(a.cf_cache_status || '-') + '</td>';
                            html += '<td style="text-align:center;">' + (a.time_ms ? esc(a.time_ms) + 'ms' : '-') + '</td>';
                            html += '<td style="text-align:center;">' + (a.is_image ? '&#10004;' : '&#10008;') + (a.dimensions ? '<br><small>' + esc(a.dimensions) + '</small>' : '') + '</td>';

                            // Download / preview button for successful image responses
                            if (a.is_image && a.url) {
                                var proxyUrl = rolmarAdmin.ajaxUrl + '?action=rolmar_proxy_photo&nonce=' + rolmarAdmin.nonce + '&url=' + encodeURIComponent(a.url);
                                html += '<td style="text-align:center;"><a href="' + proxyUrl + '" target="_blank" class="button button-small" title="Otworz w nowej karcie">Podglad</a></td>';
                            } else {
                                html += '<td style="text-align:center;">-</td>';
                            }

                            html += '<td style="font-size:10px; color:#d63638; max-width:250px; word-break:break-all;">' + esc(a.error || '') + '</td>';
                            html += '</tr>';
                        });
                    }

                    html += '</tbody></table>';

                    // Expandable: full URLs
                    html += '<details style="margin-top:10px;"><summary style="cursor:pointer; font-size:12px;">Pokaz pelne URL-e</summary>';
                    html += '<table class="widefat" style="font-size:10px; margin-top:5px;">';
                    if (d.attempts) {
                        d.attempts.forEach(function (a) {
                            html += '<tr><td style="font-weight:bold; white-space:nowrap;">' + esc(a.url_label || '') + '</td><td style="word-break:break-all;">' + esc(a.url || '') + '</td></tr>';
                        });
                    }
                    html += '</table></details>';
                    html += '</div>';

                    $result.html(html);
                } else {
                    $result.html('<div class="notice notice-error"><p>' + esc(response.data.message || 'Nieznany blad') + '</p></div>');
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                $btn.prop('disabled', false).text('Pobierz testowe zdjecie');
                var msg = 'Blad polaczenia z serwerem.';
                if (textStatus === 'timeout') {
                    msg = 'Przekroczono czas oczekiwania (120s). API getPhotos moze byc wolne — sprobuj ponownie (kolejne proby uzywaja cache).';
                }
                $result.html('<div class="notice notice-error"><p>' + msg + '</p></div>');
            }
        });
    });

    // --- Debug Category Structure ---
    $('#rolmar-debug-categories').on('click', function () {
        var $btn = $(this);
        var $result = $('#rolmar-debug-categories-result');

        $btn.prop('disabled', true).text('Pobieranie danych z API...');
        $result.html('<p><span class="spinner is-active" style="float:none;"></span> Pobieram produkty z API, to może chwilę potrwać...</p>');

        $.post(rolmarAdmin.ajaxUrl, {
            action: 'rolmar_debug_category_structure',
            nonce: rolmarAdmin.nonce
        }, function (response) {
            $btn.prop('disabled', false).text('Pokaż strukturę kategorii z API');

            if (!response.success) {
                $result.html('<div class="notice notice-error"><p>' + esc(response.data || 'Błąd') + '</p></div>');
                return;
            }

            var d = response.data;
            var html = '<div style="background:#fff;border:1px solid #ccd0d4;border-radius:4px;padding:20px;margin-top:10px;">';

            // Summary
            html += '<h3 style="margin-top:0;">Podsumowanie API</h3>';
            html += '<p><strong>Łączna liczba produktów:</strong> ' + d.total_products + '</p>';

            // Brands table
            html += '<h3>Marki (brand) — odpowiadają kategoriom z panelu Rolmar</h3>';
            html += '<table class="widefat striped" style="max-width:600px;"><thead><tr><th>Marka</th><th style="text-align:right;">Produkty</th></tr></thead><tbody>';
            $.each(d.brands, function (brand, count) {
                html += '<tr><td><strong>' + $('<span>').text(brand).html() + '</strong></td>';
                html += '<td style="text-align:right;">' + count + '</td></tr>';
            });
            html += '</tbody></table>';

            // Brand -> categories tree
            html += '<h3 style="margin-top:20px;">Drzewko: Marka &rarr; Kategorie (ścieżki z pola categories)</h3>';
            html += '<div style="max-height:400px;overflow-y:auto;border:1px solid #ddd;padding:10px;background:#f9f9f9;">';
            $.each(d.brand_tree, function (brand, cats) {
                var catCount = Object.keys(cats).length;
                var brandTotal = 0;
                $.each(cats, function (_, c) { brandTotal += c; });

                html += '<details style="margin-bottom:8px;">';
                html += '<summary style="cursor:pointer;font-weight:bold;font-size:14px;padding:4px 0;">';
                html += $('<span>').text(brand).html() + ' <span style="color:#999;font-weight:normal;">(' + brandTotal + ' produktów, ' + catCount + ' ścieżek kategorii)</span>';
                html += '</summary>';
                html += '<ul style="margin:5px 0 5px 20px;font-size:12px;">';
                $.each(cats, function (catPath, count) {
                    html += '<li><code>' + $('<span>').text(catPath).html() + '</code> <span style="color:#999;">(' + count + ')</span></li>';
                });
                html += '</ul></details>';
            });
            html += '</div>';

            // Samples
            html += '<h3 style="margin-top:20px;">Próbka produktów (10 pierwszych)</h3>';
            html += '<table class="widefat striped" style="font-size:12px;"><thead><tr><th>SKU</th><th>Nazwa</th><th>Brand</th><th>Categories</th></tr></thead><tbody>';
            d.samples.forEach(function (s) {
                html += '<tr><td><code>' + $('<span>').text(s.sku).html() + '</code></td>';
                html += '<td>' + $('<span>').text(s.name).html() + '</td>';
                html += '<td><strong>' + $('<span>').text(s.brand).html() + '</strong></td>';
                html += '<td style="font-size:11px;">' + $('<span>').text(JSON.stringify(s.categories)).html() + '</td></tr>';
            });
            html += '</tbody></table>';

            // Top category paths
            html += '<h3 style="margin-top:20px;">Najczęstsze ścieżki kategorii (top 50)</h3>';
            html += '<table class="widefat striped" style="max-width:700px;font-size:12px;"><thead><tr><th>Ścieżka</th><th style="text-align:right;">Produkty</th></tr></thead><tbody>';
            $.each(d.category_paths, function (path, count) {
                html += '<tr><td><code>' + $('<span>').text(path).html() + '</code></td>';
                html += '<td style="text-align:right;">' + count + '</td></tr>';
            });
            html += '</tbody></table>';

            html += '</div>';
            $result.html(html);
        }).fail(function () {
            $btn.prop('disabled', false).text('Pokaż strukturę kategorii z API');
            $result.html('<div class="notice notice-error"><p>Błąd połączenia</p></div>');
        });
    });

    // --- Cleanup all Rolmar data ---
    $('#rolmar-cleanup-all').on('click', function () {
        if (!confirm('UWAGA! To usunie WSZYSTKIE produkty zaimportowane z Rolmar, ich zdjęcia, kategorie i atrybuty.\n\nNIE da się tego cofnąć.\n\nCzy na pewno chcesz kontynuować?')) {
            return;
        }
        if (!confirm('Ostatnie potwierdzenie: Czy na PEWNO chcesz usunąć wszystkie dane Rolmar?')) {
            return;
        }

        var $btn = $(this);
        var $result = $('#rolmar-cleanup-result');

        $btn.prop('disabled', true).text('Usuwanie danych...');
        $result.html('<p><span class="spinner is-active" style="float:none;"></span> Usuwanie produktów, zdjęć, kategorii i atrybutów... To może potrwać kilka minut.</p>');

        $.ajax({
            url: rolmarAdmin.ajaxUrl,
            type: 'POST',
            timeout: 600000, // 10 minutes
            data: {
                action: 'rolmar_cleanup_all',
                nonce: rolmarAdmin.nonce
            },
            success: function (response) {
                $btn.prop('disabled', false).text('Usuń wszystkie dane Rolmar');
                if (response.success) {
                    var s = response.data.stats;
                    var html = '<div class="notice notice-success" style="padding: 10px;">';
                    html += '<p><strong>' + response.data.message + '</strong></p>';
                    html += '<ul>';
                    html += '<li>Produkty: ' + s.products_deleted + '</li>';
                    html += '<li>Zdjęcia: ' + s.images_deleted + '</li>';
                    html += '<li>Kategorie: ' + s.categories_deleted + '</li>';
                    html += '<li>Atrybuty: ' + s.attributes_deleted + '</li>';
                    html += '</ul></div>';
                    $result.html(html);
                } else {
                    $result.html('<div class="notice notice-error"><p>' + esc(response.data || 'Błąd') + '</p></div>');
                }
            },
            error: function (jqXHR, textStatus) {
                $btn.prop('disabled', false).text('Usuń wszystkie dane Rolmar');
                var msg = textStatus === 'timeout' ? 'Przekroczono czas (10 min). Spróbuj ponownie.' : 'Błąd połączenia.';
                $result.html('<div class="notice notice-error"><p>' + msg + '</p></div>');
            }
        });
    });

    // --- Auto-create categories toggle ---
    // When auto-create is "yes", hide only the mapping pickers (tag pickers),
    // but keep the category tree with checkboxes visible for filtering.
    function toggleMappingPickersVisibility() {
        var autoCreate = $('#rolmar_auto_create_categories').val();
        if (autoCreate === 'yes') {
            // Hide all mapping tag pickers.
            $('.rolmar-cat-mapping').hide();
            // Update description text.
            $('#rolmar-mapping-hint').hide();
        } else {
            // Show mapping pickers for checked categories.
            $('.rolmar-cat-checkbox:checked').each(function () {
                var path = $(this).data('path');
                var $container = $('.rolmar-cat-mapping[data-path="' + attrEscape(path) + '"]');
                initTagPicker($container);
                $container.show();
            });
            $('#rolmar-mapping-hint').show();
        }
    }

    $(document).on('change', '#rolmar_auto_create_categories', function () {
        toggleMappingPickersVisibility();
    });

    // --- Ensure hidden fields are synced before form submit ---
    $(document).on('submit', 'form', function () {
        if ($('.rolmar-cat-checkbox').length) {
            syncCategorySelection();
            syncCategoryMapping();

            var savedVal = $('#rolmar_allowed_categories').val();
            var checkedCount = $('.rolmar-cat-checkbox:checked').length;
            console.log('[Rolmar] FORM SUBMIT — checked checkboxes:', checkedCount, '— hidden field value:', savedVal);
        }
    });

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

        // Hide/show mapping pickers based on auto-create setting.
        toggleMappingPickersVisibility();
    });

})(jQuery);
