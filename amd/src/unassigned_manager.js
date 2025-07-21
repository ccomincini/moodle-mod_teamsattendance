/**
 * Performance-optimized JavaScript for managing unassigned Teams attendance records
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
        this.currentFilter = 'all';
        this.currentPageSize = config.defaultPageSize || 20;
        this.selectedRecords = new Set();
        this.isLoading = false;
        this.cmId = config.cmId;
        this.sesskey = config.sesskey || M.cfg.sesskey;
        this.strings = config.strings || {};
        this.availableUsers = config.availableUsers || [];
        
        this.init();
    };

    UnassignedRecordsManager.prototype = {
        /**
         * Initialize the manager
         */
        init: function() {
            this.loadPage(0);
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Filter change inding del filtro per gestire name_suggestions/email_suggestions
            $('#filter-select').on('change', function() {
                self.currentFilter = $(this).val();
                self.currentPage = 0;
                self.selectedRecords.clear();
                self.updateBulkButton();
                self.updateFilterButtonStates(); // Aggiungere questa linea
                self.loadPage(0);
            });

            // Page size change
            $('#page-size-select').on('change', function() {
                self.currentPageSize = parseInt($(this).val());
                self.currentPage = 0;
                self.loadPage(0);
            });

            // Refresh button
            $('#refresh-btn').on('click', function() {
                self.loadPage(self.currentPage, true);
            });

            // Bulk assign button
            $('#bulk-assign-btn').on('click', function() {
                if (self.selectedRecords.size > 0) {
                    self.performBulkAssignment();
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
            
            var cacheKey = 'page_' + page + '_' + this.currentFilter + '_' + this.currentPageSize;
            
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
                    per_page: this.currentPageSize,
                    filter: this.currentFilter
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
            html += '<th><input type="checkbox" id="select-all"></th>';
            html += '<th>' + this.strings.teams_user_id + '</th>';
            html += '<th>' + this.strings.attendance_duration + '</th>';
            html += '<th>' + this.strings.suggested_match + '</th>';
            html += '<th>' + this.strings.actions + '</th>';
            html += '</tr>';
            html += '</thead>';
            html += '<tbody>';

            if (records.length === 0) {
                html += '<tr><td colspan="5" class="text-center text-muted">';
                html += this.strings.no_records_found;
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
            // Determine row class based on suggestion type nomi classi per correspondence con CSS
            var rowClass = '';
            if (record.has_suggestion && record.suggestion) {
                if (record.suggestion.type === 'name') {
                    rowClass = 'suggestion-name-row';
                } else if (record.suggestion.type === 'email') {
                    rowClass = 'suggestion-email-row';
                }
            } else {
                rowClass = 'suggestion-none-row';
            }
            
            var html = '<tr data-record-id="' + record.id + '" class="' + rowClass + '">';
            
            // Checkbox
            html += '<td>';
            html += '<input type="checkbox" class="record-checkbox" value="' + record.id + '">';
            html += '</td>';
            
            // Teams User ID
            html += '<td>' + this.escapeHtml(record.teams_user_id) + '</td>';
            
            // Duration
            html += '<td>' + this.formatDuration(record.attendance_duration) + '</td>';
            
            // Suggestion
            html += '<td>';
            if (record.has_suggestion && record.suggestion) {
                var user = record.suggestion.user;
                var confidence = record.suggestion.confidence;
                var type = record.suggestion.type;
                
                html += '<span class="badge badge-' + (confidence === 'high' ? 'success' : 'warning') + '">';
                html += this.escapeHtml(user.firstname + ' ' + user.lastname);
                html += '</span>';
                html += '<br><small class="text-muted">' + type + ' match</small>';
            } else {
                html += '<span class="text-muted">' + this.strings.no_suggestion + '</span>';
            }
            html += '</td>';
            
            // Actions
            html += '<td>';
            if (record.has_suggestion) {
                html += '<button class="btn btn-sm btn-success apply-suggestion-btn" ';
                html += 'data-record-id="' + record.id + '" ';
                html += 'data-user-id="' + record.suggestion.user.id + '">';
                html += this.strings.apply_suggestion;
                html += '</button>';
            } else {
                // Manual selection dropdown
                html += '<select class="form-control form-control-sm manual-user-select" ';
                html += 'data-record-id="' + record.id + '">';
                html += '<option value="">' + this.strings.select_user + '</option>';
                
                for (var i = 0; i < this.availableUsers.length; i++) {
                    var user = this.availableUsers[i];
                    html += '<option value="' + user.id + '">';
                    html += this.escapeHtml(user.lastname + ' ' + user.firstname);
                    html += '</option>';
                }
                
                html += '</select>';
                html += ' <button class="btn btn-sm btn-primary manual-assign-btn" ';
                html += 'data-record-id="' + record.id + '" disabled>';
                html += this.strings.assign;
                html += '</button>';
            }
            html += '</td>';
            
            html += '</tr>';
            return html;
        },

        /**
         * Render pagination controls
         * @param {Object} pagination Pagination data
         */
        renderPagination: function(pagination) {
            var self = this;
            var html = '<nav aria-label="Pagination">';
            html += '<ul class="pagination justify-content-center">';
            
            // Previous button
            html += '<li class="page-item ' + (pagination.has_previous ? '' : 'disabled') + '">';
            html += '<a class="page-link" href="#" data-page="' + (pagination.page - 1) + '">';
            html += this.strings.previous;
            html += '</a></li>';
            
            // Page numbers
            var startPage = Math.max(0, pagination.page - 2);
            var endPage = Math.min(pagination.total_pages - 1, pagination.page + 2);
            
            for (var i = startPage; i <= endPage; i++) {
                html += '<li class="page-item ' + (i === pagination.page ? 'active' : '') + '">';
                html += '<a class="page-link" href="#" data-page="' + i + '">' + (i + 1) + '</a>';
                html += '</li>';
            }
            
            // Next button
            html += '<li class="page-item ' + (pagination.has_next ? '' : 'disabled') + '">';
            html += '<a class="page-link" href="#" data-page="' + (pagination.page + 1) + '">';
            html += this.strings.next;
            html += '</a></li>';
            html += '</ul>';
            
            // Page info
            html += '<div class="text-center mt-2">';
            html += this.strings.page + ' ' + (pagination.page + 1) + ' ';
            html += this.strings.of + ' ' + pagination.total_pages + ' ';
            html += '(' + pagination.total_count + ' ' + this.strings.total_records + ')';
            html += '</div>';
            html += '</nav>';
            
            $('#pagination-container').html(html);
            
            // Bind pagination events
            $('.page-link').on('click', function(e) {
                e.preventDefault();
                var page = parseInt($(this).data('page'));
                if (page >= 0 && page < pagination.total_pages) {
                    self.loadPage(page);
                }
            });
        },

        /**
         * Bind table event handlers
         */
        bindTableEvents: function() {
            var self = this;

            // Select all checkbox
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
            
            // Individual checkboxes
            $('.record-checkbox').on('change', function(e) {
                var recordId = parseInt($(this).val());
                
                if ($(this).prop('checked')) {
                    self.selectedRecords.add(recordId);
                } else {
                    self.selectedRecords.delete(recordId);
                }
                
                self.updateBulkButton();
                
                // Update select all checkbox
                var totalCheckboxes = $('.record-checkbox').length;
                var checkedCheckboxes = $('.record-checkbox:checked').length;
                $('#select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
            });
            
            // Apply suggestion buttons
            $('.apply-suggestion-btn').on('click', function(e) {
                var recordId = $(this).data('record-id');
                var userId = $(this).data('user-id');
                self.applySingleSuggestion(recordId, userId, $(this));
            });
            
            // Manual user selection
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
            
            // Manual assign buttons
            $('.manual-assign-btn').on('click', function(e) {
                var recordId = $(this).data('record-id');
                var select = $(this).siblings('.manual-user-select');
                var userId = select.val();
                
                if (userId) {
                    self.applySingleSuggestion(recordId, userId, $(this));
                }
            });
        },

        * Update filter button states
        */
        updateFilterButtonStates: function() {
            // Aggiorna contatori nei bottoni filtro se necessario
            var filterSelect = $('#filter-select');
            var currentFilter = this.currentFilter;
            
            // Evidenzia filtro attivo
            filterSelect.addClass('filter-active');
        },

        /**
         * Update bulk assignment button
         */
        updateBulkButton: function() {
            var count = this.selectedRecords.size;
            $('#bulk-assign-btn').prop('disabled', count === 0);
            $('#bulk-assign-btn').text(this.strings.apply_selected + ' (' + count + ')');
        },

        /**
         * Apply single suggestion
         * @param {number} recordId Record ID
         * @param {number} userId User ID
         * @param {jQuery} button Button element
         */
        applySingleSuggestion: function(recordId, userId, button) {
            var self = this;
            button.prop('disabled', true).text(this.strings.applying + '...');
            
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
                        self.showSuccess(response.message);
                    } else {
                        self.showError(response.error);
                        button.prop('disabled', false).text(self.strings.apply_suggestion);
                    }
                },
                error: function() {
                    self.showError('Connection error');
                    button.prop('disabled', false).text(self.strings.apply_suggestion);
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
                
                // Check for automatic suggestion
                var button = row.find('.apply-suggestion-btn');
                if (button.length) {
                    assignments[recordId] = button.data('user-id');
                } else {
                    // Check for manual selection
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
            var toast = $('<div class="alert alert-' + type + ' alert-dismissible fade show position-fixed" ' +
                'style="top: 20px; right: 20px; z-index: 9999;">' +
                '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
                message + '</div>');
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
