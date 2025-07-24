/**
 * JavaScript for managing unassigned Teams attendance records
 * @module     mod_teamsattendance/unassigned_manager
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/ajax', 'core/notification', 'core/str'],
function($, Ajax, Notification, Str) {
    'use strict';

    /**
     * UnassignedRecordsManager constructor
     * @param {Object} config Configuration object
     */
    var UnassignedRecordsManager = function(config) {
        this.currentPage = 0;
        this.currentFilter = this.getFilterFromURL(); // READ FROM URL
        this.currentPageSize = 50; // Default 50
        this.selectedRecords = new Set();
        this.isLoading = false;
        this.cmId = config.cmId;
        this.sesskey = config.sesskey || M.cfg.sesskey;
        this.strings = config.strings || {};
        this.availableUsers = [];

        this.init();
        this.loadAvailableUsers();
    };

    UnassignedRecordsManager.prototype = {
        /**
         * Get filter parameter from current URL
         * @return {string} Filter value from URL or 'all' as default
         */
        getFilterFromURL: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var filter = urlParams.get('filter');
            return filter || 'all';
        },

        /**
         * Update URL with current filter without reloading page
         * @param {string} filter Filter value
         */
        updateURL: function(filter) {
            var url = new URL(window.location);
            if (filter && filter !== 'all') {
                url.searchParams.set('filter', filter);
            } else {
                url.searchParams.delete('filter');
            }
            window.history.replaceState({}, '', url);
        },

        /**
         * Update statistics counters
         */
        updateStatistics: function() {
            var self = this;
            $.ajax({
                url: window.location.href,
                data: {ajax: 1, action: 'get_statistics'},
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#name-suggestions-count').text(data.name_suggestions);
                        $('#email-suggestions-count').text(data.email_suggestions);
                        var noSuggestions = data.total_unassigned - data.name_suggestions - data.email_suggestions;
                        $('#no-suggestions-count').text(noSuggestions);
                    }
                }
            });
        },
        
        /**
         * Initialize the manager
         */
        init: function() {
            this.syncSelectValues();
            this.bindEvents();
            this.bindSearchEvents();
            this.loadPage(0);
            this.updateStatistics();
        },

        /**
         * Sync select values with current state
         */
        syncSelectValues: function() {
            $('#filter-select').val(this.currentFilter);
            $('#page-size-select').val(this.currentPageSize);
        },

        /**
         * Get current filters as object
         * @return {Object} Current filters
         */
        getCurrentFilters: function() {
            var filters = {};
            
            // Check for search inputs
            var nameSearch = $('#name-search').val();
            var emailSearch = $('#email-search').val();
            
            if (nameSearch && nameSearch.trim()) {
                filters.suggestion_type = 'name:' + nameSearch.trim();
                return filters;
            }
            
            if (emailSearch && emailSearch.trim()) {
                filters.suggestion_type = 'email:' + emailSearch.trim();
                return filters;
            }
            
            if (this.currentFilter !== 'all') {
                // Convert URL filter format to backend format
                switch (this.currentFilter) {
                    case 'name_suggestions':
                        filters.suggestion_type = 'name_based';
                        break;
                    case 'email_suggestions':
                        filters.suggestion_type = 'email_based';
                        break;
                    case 'without_suggestions':
                        filters.suggestion_type = 'none';
                        break;
                }
            }
            
            return filters;
        },

        /**
         * Apply current settings from both selects
         */
        applyCurrentSettings: function() {
            // Read current values from both selects
            this.currentFilter = $('#filter-select').val();
            var pageSizeValue = $('#page-size-select').val();
            
            // Update URL to reflect current filter
            this.updateURL(this.currentFilter);
            
            // Handle "all" pagesize
            if (pageSizeValue === 'all') {
                this.currentPageSize = 999999; // Large number for "all"
            } else {
                this.currentPageSize = parseInt(pageSizeValue);
            }
            
            // Reset to first page
            this.currentPage = 0;
            this.selectedRecords.clear();
            this.updateBulkButton();
            
            // Load data with current settings
            this.loadPage(0);
            this.updateStatistics();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Both selects trigger the same update function
            $('#filter-select, #page-size-select').on('change', function() {
                self.applyCurrentSettings();
            });

            // Bulk assign button
            $('#bulk-assign-btn').on('click', function() {
                if (self.selectedRecords.size > 0) {
                    self.performBulkAssignment();
                }
            });
        },

        /**
         * Bind search input events
         */
        bindSearchEvents: function() {
            var self = this;
            var searchTimeout;
            
            $('#name-search, #email-search').on('input', function() {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(function() {
                    self.currentPage = 0;
                    self.selectedRecords.clear();
                    self.updateBulkButton();
                    self.loadPage(0);
                }, 500);
            });
        },

        /**
         * Load available users
         */
        loadAvailableUsers: function() {
            var self = this;
            $.ajax({
                url: window.location.href,
                data: {ajax: 1, action: 'get_available_users'},
                success: function(response) {
                    if (response.success) {
                        self.availableUsers = response.users;
                    }
                }
            });
        },

        /**
         * Load a page of records
         * @param {number} page Page number
         * @param {boolean} forceRefresh Force refresh from server
         */
        loadPage: function(page, forceRefresh) {
            if (this.isLoading) {
                return;
            }

            var self = this;
            this.isLoading = true;
            $('#loading-indicator').show();

            var filters = this.getCurrentFilters();
            var actualPageSize = this.currentPageSize === 999999 ? 'all' : this.currentPageSize;
            
            var filtersHash = JSON.stringify(filters);
            var cacheKey = 'page_' + page + '_' + filtersHash + '_' + actualPageSize;

            if (!forceRefresh && sessionStorage.getItem(cacheKey)) {
                var cachedData = JSON.parse(sessionStorage.getItem(cacheKey));
                this.renderPage(cachedData);
                this.isLoading = false;
                $('#loading-indicator').hide();
                return;
            }

            $.ajax({
                url: window.location.href,
                method: 'GET',
                data: {
                    ajax: 1,
                    action: 'load_page',
                    page: page,
                    per_page: actualPageSize,
                    filters: JSON.stringify(filters)
                },
                success: function(response) {
                    if (response.success) {
                        sessionStorage.setItem(cacheKey, JSON.stringify(response.data));
                        self.renderPage(response.data);
                    } else {
                        self.showError('Failed to load data: ' + (response.error || 'Unknown error'));
                    }
                },
                error: function(xhr, status, error) {
                    self.showError('Connection error: ' + error);
                },
                complete: function() {
                    self.isLoading = false;
                    $('#loading-indicator').hide();
                }
            });
        },

        /**
         * Render page data
         * @param {Object} data Page data
         */
        renderPage: function(data) {
            this.currentPage = data.pagination.page;
            this.renderTable(data.records);
            this.renderPagination(data.pagination);
            this.updateBulkButton();
            this.bindTableEvents();
        },

        /**
         * Render records table
         * @param {Array} records Array of records
         */
        renderTable: function(records) {
            var html = '<div class="table-responsive">';
            html += '<table class="table table-striped table-hover">';
            html += '<thead class="thead-dark">';
            html += '<tr>';
            html += '<th>' + (this.strings.teams_user_id || 'ID Utente Teams') + '</th>';
            html += '<th>' + (this.strings.attendance_duration || 'Durata Presenza') + '</th>';
            html += '<th><input type="checkbox" id="select-all"></th>';
            html += '<th>' + (this.strings.suggested_match || 'Corrispondenza Suggerita') + '</th>';
            html += '<th>' + (this.strings.actions || 'Azioni') + '</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            if (records.length === 0) {
                html += '<tr><td colspan="5" class="text-center text-muted">';
                html += (this.strings.no_records_found || 'Nessun record trovato');
                html += '</td></tr>';
            } else {
                for (var i = 0; i < records.length; i++) {
                    html += this.renderTableRow(records[i]);
                }
            }

            html += '</tbody></table></div>';
            $('#records-container').html(html);
        },

        /**
         * Render a table row
         * @param {Object} record Record data
         * @return {string} HTML string
         */
        renderTableRow: function(record) {
            var rowClass = 'suggestion-none-row';
            if (record.has_suggestion && record.suggestion) {
                if (record.suggestion_type === 'name') {
                    rowClass = 'suggestion-name-row';
                } else if (record.suggestion_type === 'email') {
                    rowClass = 'suggestion-email-row';
                }
            }

            var html = '<tr data-record-id="' + record.id + '" class="' + rowClass + '">';
            html += '<td>' + this.escapeHtml(record.teams_user_id) + '</td>';
            html += '<td>' + this.formatDuration(record.attendance_duration) + '</td>';

            html += '<td>';
            var isChecked = record.has_suggestion && record.suggestion ? ' checked="checked"' : '';
            html += '<input type="checkbox" class="record-checkbox" value="' + record.id + '"' + isChecked + '>';
            html += '</td>';

            html += '<td>';
            if (record.has_suggestion && record.suggestion) {
                var user = record.suggestion.user;
                var confidence = record.suggestion.confidence;
                var type = record.suggestion_type;

                html += '<span class="badge badge-' + (confidence === 'high' ? 'success' : 'warning') + '">';
                html += this.escapeHtml(user.firstname + ' ' + user.lastname);
                html += '</span>';
                html += '<br><small class="text-muted">' + type + ' match</small>';
            } else {
                html += '<span class="text-muted">' + (this.strings.no_suggestion || 'Nessun suggerimento') + '</span>';
            }
            html += '</td>';

            html += '<td>';
            if (record.has_suggestion) {
                html += '<button class="btn btn-sm btn-success apply-suggestion-btn" ';
                html += 'data-record-id="' + record.id + '" ';
                html += 'data-user-id="' + record.suggestion.user.id + '">';
                html += (this.strings.apply_suggestion || 'Applica suggerimento');
                html += '</button>';
            } else {
                html += '<select class="form-control form-control-sm manual-user-select" ';
                html += 'data-record-id="' + record.id + '">';
                html += '<option value="">' + (this.strings.select_user || 'Seleziona utente') + '</option>';

                for (var i = 0; i < this.availableUsers.length; i++) {
                    var user = this.availableUsers[i];
                    html += '<option value="' + user.id + '">';
                    html += this.escapeHtml(user.name);
                    html += '</option>';
                }

                html += '</select>';
                html += ' <button class="btn btn-sm btn-primary manual-assign-btn" ';
                html += 'data-record-id="' + record.id + '" disabled>';
                html += (this.strings.assign || 'Assegna');
                html += '</button>';
            }
            html += '</td>';

            html += '</tr>';
            return html;
        },

        /**
         * Render pagination controls
         */
        renderPagination: function(pagination) {
            var self = this;
            
            if (pagination.total_count === 0) {
                $('#pagination-container').html('<div class="text-center mt-2 text-muted">Nessun record trovato per il filtro selezionato</div>');
                return;
            }
            
            var html = '<nav aria-label="Pagination">';
            
            // Show pagination only if not showing all and multiple pages
            if (!pagination.show_all && pagination.total_pages > 1) {
                html += '<ul class="pagination justify-content-center">';

                html += '<li class="page-item ' + (pagination.has_previous ? '' : 'disabled') + '">';
                html += '<a class="page-link" href="#" data-page="' + (pagination.page - 1) + '">';
                html += (this.strings.previous || 'Precedente');
                html += '</a></li>';

                var startPage = Math.max(0, pagination.page - 2);
                var endPage = Math.min(pagination.total_pages - 1, pagination.page + 2);

                for (var i = startPage; i <= endPage; i++) {
                    html += '<li class="page-item ' + (i === pagination.page ? 'active' : '') + '">';
                    html += '<a class="page-link" href="#" data-page="' + i + '">' + (i + 1) + '</a>';
                    html += '</li>';
                }

                html += '<li class="page-item ' + (pagination.has_next ? '' : 'disabled') + '">';
                html += '<a class="page-link" href="#" data-page="' + (pagination.page + 1) + '">';
                html += (this.strings.next || 'Successivo');
                html += '</a></li>';
                
                html += '</ul>';
            }

            // Info display
            html += '<div class="text-center mt-2">';
            
            if (pagination.show_all || this.currentPageSize === 999999) {
                html += '<strong>' + pagination.total_count + ' record trovati (tutti visualizzati)</strong>';
            } else if (pagination.total_pages > 1) {
                html += 'Pagina ' + (pagination.page + 1) + ' di ' + pagination.total_pages + ' - ';
                html += pagination.total_count + ' record totali';
            } else {
                html += pagination.total_count + ' record trovati';
            }
            
            html += '</div>';
            html += '</nav>';

            $('#pagination-container').html(html);

            // Bind pagination clicks
            if (!pagination.show_all && pagination.total_pages > 1) {
                $('.page-link').on('click', function(e) {
                    e.preventDefault();
                    var page = parseInt($(this).data('page'));
                    if (page >= 0 && page < pagination.total_pages) {
                        self.loadPage(page);
                    }
                });
            }
        },

        /**
         * Bind table event handlers
         */
        bindTableEvents: function() {
            var self = this;

            $('.record-checkbox:checked').each(function() {
                self.selectedRecords.add(parseInt($(this).val()));
            });
            self.updateBulkButton();

            var totalCheckboxes = $('.record-checkbox').length;
            var checkedCheckboxes = $('.record-checkbox:checked').length;
            if (totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes) {
                $('#select-all').prop('checked', true);
            }

            $('#select-all').on('change', function(e) {
                var isChecked = $(this).prop('checked');
                $('.record-checkbox').prop('checked', isChecked);

                if (isChecked) {
                    $('.record-checkbox').each(function() {
                        self.selectedRecords.add(parseInt($(this).val()));
                    });
                } else {
                    self.selectedRecords.clear();
                }
                self.updateBulkButton();
            });

            $('.record-checkbox').on('change', function(e) {
                var recordId = parseInt($(this).val());

                if ($(this).prop('checked')) {
                    self.selectedRecords.add(recordId);
                } else {
                    self.selectedRecords.delete(recordId);
                }

                self.updateBulkButton();

                var totalCheckboxes = $('.record-checkbox').length;
                var checkedCheckboxes = $('.record-checkbox:checked').length;
                $('#select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
            });

            $('.apply-suggestion-btn').on('click', function(e) {
                var recordId = $(this).data('record-id');
                var userId = $(this).data('user-id');
                self.applySingleSuggestion(recordId, userId, $(this));
            });

            $('.manual-user-select').on('change', function(e) {
                var userId = $(this).val();
                var recordId = $(this).data('record-id');
                var assignBtn = $(this).siblings('.manual-assign-btn');

                if (userId) {
                    assignBtn.prop('disabled', false);
                } else {
                    assignBtn.prop('disabled', true);
                }
            });

            $('.manual-assign-btn').on('click', function(e) {
                var recordId = $(this).data('record-id');
                var select = $(this).siblings('.manual-user-select');
                var userId = select.val();

                if (userId) {
                    self.applySingleSuggestion(recordId, userId, $(this));
                }
            });
        },

        /**
         * Update bulk assignment button
         */
        updateBulkButton: function() {
            var count = this.selectedRecords.size;
            $('#bulk-assign-btn').prop('disabled', count === 0);
            $('#bulk-assign-btn').text((this.strings.apply_selected || 'Applica selezionati') + ' (' + count + ')');
        },

        /**
         * Apply single suggestion
         * @param {number} recordId Record ID
         * @param {number} userId User ID
         * @param {jQuery} button Button element
         */
        applySingleSuggestion: function(recordId, userId, button) {
            var self = this;
            button.prop('disabled', true).text((this.strings.applying || 'Applicando') + '...');

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    ajax: 1,
                    action: 'assign_user',
                    recordid: recordId,
                    userid: userId,
                    sesskey: this.sesskey
                },
                success: function(response) {
                    if (response.success) {
                        $('tr[data-record-id="' + recordId + '"]').fadeOut();
                        self.selectedRecords.delete(recordId);
                        self.updateBulkButton();
                        sessionStorage.clear();
                        self.updateStatistics(); 
                        self.showSuccess(response.message);
                    } else {
                        self.showError(response.error);
                        button.prop('disabled', false).text(self.strings.apply_suggestion || 'Applica suggerimento');
                    }
                },
                error: function() {
                    self.showError('Connection error');
                    button.prop('disabled', false).text(self.strings.apply_suggestion || 'Applica suggerimento');
                }
            });
        },

        /**
         * Perform bulk assignment
         */
        performBulkAssignment: function() {
            if (this.selectedRecords.size === 0) {
                return;
            }

            var self = this;
            $('#progress-container').show();
            $('#bulk-assign-btn').prop('disabled', true);

            var assignments = {};
            this.selectedRecords.forEach(function(recordId) {
                var row = $('tr[data-record-id="' + recordId + '"]');

                var button = row.find('.apply-suggestion-btn');
                if (button.length) {
                    assignments[recordId] = button.data('user-id');
                } else {
                    var select = row.find('.manual-user-select');
                    if (select.length && select.val()) {
                        assignments[recordId] = select.val();
                    }
                }
            });

            $.ajax({
                url: window.location.href,
                method: 'POST',
                data: {
                    ajax: 1,
                    action: 'bulk_assign',
                    assignments: assignments,
                    sesskey: this.sesskey
                },
                success: function(response) {
                    if (response.success) {
                        var result = response.data;

                        $('#progress-bar').css('width', '100%').addClass('bg-success');
                        $('#progress-text').text('Complete: ' + result.successful + '/' + result.total + ' successful');

                        self.selectedRecords.clear();
                        sessionStorage.clear();
                        self.updateStatistics();

                        setTimeout(function() {
                            $('#progress-container').hide();
                            self.loadPage(self.currentPage, true);
                        }, 2000);

                        self.showSuccess('Bulk assignment completed: ' + result.successful + ' successful, ' + result.failed + ' failed');
                    } else {
                        self.showError(response.error);
                    }
                },
                error: function() {
                    self.showError('Connection error during bulk assignment');
                },
                complete: function() {
                    $('#bulk-assign-btn').prop('disabled', false);
                }
            });
        },

        /**
         * Format duration in seconds to human readable format
         * @param {number} seconds Duration in seconds
         * @return {string} Formatted duration
         */
        formatDuration: function(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            return hours + 'h ' + minutes + 'm';
        },

        /**
         * Escape HTML to prevent XSS
         * @param {string} text Text to escape
         * @return {string} Escaped text
         */
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        /**
         * Show success message
         * @param {string} message Success message
         */
        showSuccess: function(message) {
            this.showToast(message, 'success', 5000);
        },

        /**
         * Show error message
         * @param {string} message Error message
         */
        showError: function(message) {
            this.showToast(message, 'danger', 8000);
        },

        /**
         * Show toast notification
         * @param {string} message Message to show
         * @param {string} type Alert type (success, danger, warning, info)
         * @param {number} duration Duration in milliseconds
         */
        showToast: function(message, type, duration) {
            var toast = $('<div class="alert alert-' + type + ' alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;"><button type="button" class="close" data-dismiss="alert">&times;</button>' + message + '</div>');
            $('body').append(toast);
            setTimeout(function() {
                toast.remove();
            }, duration);
        }
    };

    return {
        /**
         * Initialize the unassigned records manager
         * @param {Object} config Configuration object
         * @return {UnassignedRecordsManager} Manager instance
         */
        init: function(config) {
            return new UnassignedRecordsManager(config);
        }
    };
});
