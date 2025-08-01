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
        this.currentFilter = this.getFilterFromURL();
        this.currentPageSize = 50;
        this.selectedRecords = new Set();
        this.isLoading = false;
        this.cmId = config.cmId;
        this.sesskey = config.sesskey || M.cfg.sesskey;
        this.strings = config.strings || {};
        this.availableUsers = config.available_users || [];

        console.log('MANAGER INIT: constructor called with config:', config);
        console.log('MANAGER INIT: initial available users count:', this.availableUsers.length);
        
        window.unassignedManager = this;
        this.init();
        this.loadAvailableUsers();
    };

    UnassignedRecordsManager.prototype = {
        getFilterFromURL: function() {
            var urlParams = new URLSearchParams(window.location.search);
            var filter = urlParams.get('filter');
            return filter || 'all';
        },

        updateURL: function(filter) {
            var url = new URL(window.location);
            if (filter && filter !== 'all') {
                url.searchParams.set('filter', filter);
            } else {
                url.searchParams.delete('filter');
            }
            window.history.replaceState({}, '', url);
        },

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
        
        init: function() {
            this.syncSelectValues();
            this.bindEvents();
            this.loadPage(0);
            this.updateStatistics();
        },

        syncSelectValues: function() {
            $('#filter-select').val(this.currentFilter);
            $('#page-size-select').val(this.currentPageSize);
        },

        getCurrentFilters: function() {
            var filters = {};
            
            if (this.currentFilter !== 'all') {
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

        applyCurrentSettings: function() {
            var newFilter = $('#filter-select').val();
            var pageSizeValue = $('#page-size-select').val();
            
            var filterChanged = (newFilter !== this.currentFilter);
            var newPageSizeNum = pageSizeValue === 'all' ? 999999 : parseInt(pageSizeValue);
            var pageSizeChanged = (newPageSizeNum !== this.currentPageSize);
            
            if (filterChanged || pageSizeChanged) {
                sessionStorage.clear();
            }
            
            this.currentFilter = newFilter;
            this.updateURL(this.currentFilter);
            this.currentPageSize = newPageSizeNum;
            this.currentPage = 0;
            this.selectedRecords.clear();
            this.updateBulkButton();
            
            this.loadPage(0, true);
            this.updateStatistics();
        },

        bindEvents: function() {
            var self = this;
            
            $('#filter-select, #page-size-select').on('change', function(event) {
                self.applyCurrentSettings();
            });

            $('#bulk-assign-btn').on('click', function() {
                if (self.selectedRecords.size > 0) {
                    self.performBulkAssignment();
                }
            });
        },

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
                    filters: JSON.stringify(filters),
                    sesskey: this.sesskey
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

        renderPage: function(data) {
            this.currentPage = data.pagination.page;
            this.renderTable(data.records);
            this.renderPagination(data.pagination);
            this.updateBulkButton();
            this.bindTableEvents();
        },

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

        renderTableRow: function(record) {
            var rowClass = 'suggestion-none-row';
            if (record.has_suggestion && record.suggestion) {
                if (record.suggestion_type === 'name_based') {
                    rowClass = 'suggestion-name-row';
                } else if (record.suggestion_type === 'email_based') {
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
            html += '<div class="action-column d-flex flex-column gap-2">';
            
            // SUGGESTION BUTTON
            if (record.has_suggestion && record.suggestion) {
                html += '<button class="btn btn-sm btn-success apply-suggestion-btn" ';
                html += 'data-record-id="' + record.id + '" ';
                html += 'data-user-id="' + record.suggestion.user.id + '">';
                html += (this.strings.apply_suggestion || 'Applica suggerimento');
                html += '</button>';
            } else {
                html += '<button class="btn btn-sm btn-secondary apply-suggestion-btn" disabled>';
                html += (this.strings.no_suggestion || 'Nessun suggerimento');
                html += '</button>';
            }
            
            // SEARCHABLE USER SELECT
            html += '<div class="searchable-select-container" data-record-id="' + record.id + '" style="position: relative;">';
            html += '<input type="text" class="form-control form-control-sm user-search-input" ';
            html += 'placeholder="Cerca utente..." style="margin-bottom: 2px;">';
            html += '<div class="search-results-dropdown" style="display: none; max-height: 150px; overflow-y: auto; border: 1px solid #ccc; background: white; position: absolute; z-index: 1000; width: 100%;"></div>';
            html += '<button class="btn btn-sm btn-primary manual-assign-btn" ';
            html += 'data-record-id="' + record.id + '" disabled>';
            html += (this.strings.assign || 'Assegna');
            html += '</button>';
            html += '</div>';
            
            html += '</div>';
            html += '</td>';

            html += '</tr>';
            return html;
        },

        initSearchableDropdown: function(container) {
            var self = this;
            var recordId = container.data('record-id');
            var input = container.find('.user-search-input');
            var dropdown = container.find('.search-results-dropdown');
            var assignBtn = container.find('.manual-assign-btn');
            var selectedUserId = null;

            input.on('focus', function() {
                self.updateSearchResults(dropdown, '', assignBtn);
                dropdown.show();
            });

            input.on('blur', function() {
                setTimeout(function() {
                    dropdown.hide();
                }, 200);
            });

            input.on('input', function() {
                var query = $(this).val();
                self.updateSearchResults(dropdown, query, assignBtn);
                selectedUserId = null;
                assignBtn.prop('disabled', true);
            });

            dropdown.on('click', '.user-option', function() {
                var userId = $(this).data('user-id');
                var userName = $(this).text();
                selectedUserId = userId;
                input.val(userName);
                dropdown.hide();
                assignBtn.prop('disabled', false);
            });

            assignBtn.on('click', function() {
                if (selectedUserId) {
                    self.applySingleSuggestion(recordId, selectedUserId, $(this));
                }
            });
        },

        updateSearchResults: function(dropdown, query, assignBtn) {
            var self = this;
            var filteredUsers = this.availableUsers;

            if (query.trim()) {
                var queryLower = query.toLowerCase();
                filteredUsers = this.availableUsers.filter(function(user) {
                    return user.name.toLowerCase().indexOf(queryLower) !== -1;
                });
            }

            var html = '';
            if (filteredUsers.length === 0) {
                html = '<div class="user-option text-muted p-2">Nessun utente trovato</div>';
            } else {
                filteredUsers.forEach(function(user) {
                    html += '<div class="user-option p-2" data-user-id="' + user.id + '" ';
                    html += 'style="cursor: pointer; border-bottom: 1px solid #eee;">';
                    html += self.escapeHtml(user.name);
                    html += '</div>';
                });
            }

            dropdown.html(html);

            dropdown.find('.user-option').on('mouseenter', function() {
                $(this).css('background-color', '#f8f9fa');
            }).on('mouseleave', function() {
                $(this).css('background-color', 'white');
            });

            dropdown.show();
        },

        renderPagination: function(pagination) {
            var self = this;
            
            if (pagination.total_count === 0) {
                $('#pagination-container').html('<div class="text-center mt-2 text-muted">Nessun record trovato per il filtro selezionato</div>');
                return;
            }
            
            var html = '<nav aria-label="Pagination">';
            
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
                if (userId) {
                    self.applySingleSuggestion(recordId, userId, $(this));
                }
            });

            $('.searchable-select-container').each(function() {
                self.initSearchableDropdown($(this));
            });
        },

        updateBulkButton: function() {
            var count = this.selectedRecords.size;
            $('#bulk-assign-btn').prop('disabled', count === 0);
            $('#bulk-assign-btn').text((this.strings.apply_selected || 'Applica selezionati') + ' (' + count + ')');
        },

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
                if (button.length && !button.prop('disabled')) {
                    assignments[recordId] = button.data('user-id');
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

        formatDuration: function(seconds) {
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            return hours + 'h ' + minutes + 'm';
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        showSuccess: function(message) {
            this.showToast(message, 'success', 5000);
        },

        showError: function(message) {
            this.showToast(message, 'danger', 8000);
        },

        showToast: function(message, type, duration) {
            var toast = $('<div class="alert alert-' + type + ' alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;"><button type="button" class="close" data-dismiss="alert">&times;</button>' + message + '</div>');
            $('body').append(toast);
            setTimeout(function() {
                toast.remove();
            }, duration);
        }
    };

    return {
        init: function(config) {
            return new UnassignedRecordsManager(config);
        }
    };
});
