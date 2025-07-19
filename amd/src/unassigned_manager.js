/**
 * Performance-optimized JavaScript for managing unassigned Teams attendance records
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

class UnassignedRecordsManager {
    constructor(config) {
        this.currentPage = 0;
        this.currentFilter = 'all';
        this.currentPageSize = config.defaultPageSize;
        this.selectedRecords = new Set();
        this.isLoading = false;
        this.cmId = config.cmId;
        this.sesskey = config.sesskey;
        this.strings = config.strings;
        
        this.init();
    }

    init() {
        this.loadPage(0);
        this.bindEvents();
    }

    bindEvents() {
        // Filter change
        $('#filter-select').change(() => {
            this.currentFilter = $('#filter-select').val();
            this.currentPage = 0;
            this.selectedRecords.clear();
            this.updateBulkButton();
            this.loadPage(0);
        });

        // Page size change
        $('#page-size-select').change(() => {
            this.currentPageSize = parseInt($('#page-size-select').val());
            this.currentPage = 0;
            this.loadPage(0);
        });

        // Refresh button
        $('#refresh-btn').click(() => {
            this.loadPage(this.currentPage, true);
        });

        // Bulk assign button
        $('#bulk-assign-btn').click(() => {
            if (this.selectedRecords.size > 0) {
                this.performBulkAssignment();
            }
        });
    }

    loadPage(page, forceRefresh = false) {
        if (this.isLoading) return;
        
        this.isLoading = true;
        $('#loading-indicator').show();
        
        const cacheKey = `page_${page}_${this.currentFilter}_${this.currentPageSize}`;
        
        if (!forceRefresh && sessionStorage.getItem(cacheKey)) {
            const cachedData = JSON.parse(sessionStorage.getItem(cacheKey));
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
            success: (response) => {
                if (response.success) {
                    sessionStorage.setItem(cacheKey, JSON.stringify(response.data));
                    this.renderPage(response.data);
                } else {
                    this.showError('Failed to load data: ' + (response.error || 'Unknown error'));
                }
            },
            error: (xhr, status, error) => {
                this.showError('Connection error: ' + error);
            },
            complete: () => {
                this.isLoading = false;
                $('#loading-indicator').hide();
            }
        });
    }

    renderPage(data) {
        this.currentPage = data.pagination.page;
        this.renderTable(data.records);
        this.renderPagination(data.pagination);
        this.updateBulkButton();
        this.bindTableEvents();
    }

    renderTable(records) {
        let html = '<div class="table-responsive">';
        html += '<table class="table table-striped table-hover">';
        html += '<thead class="thead-dark">';
        html += '<tr>';
        html += '<th><input type="checkbox" id="select-all"></th>';
        html += `<th>${this.strings.teams_user_id}</th>`;
        html += `<th>${this.strings.attendance_duration}</th>`;
        html += `<th>${this.strings.suggested_match}</th>`;
        html += `<th>${this.strings.actions}</th>`;
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';

        if (records.length === 0) {
            html += '<tr><td colspan="5" class="text-center text-muted">';
            html += this.strings.no_records_found;
            html += '</td></tr>';
        } else {
            records.forEach((record) => {
                html += this.renderTableRow(record);
            });
        }

        html += '</tbody></table></div>';
        $('#records-container').html(html);
    }

    renderTableRow(record) {
        let html = `<tr data-record-id="${record.id}">`;
        
        // Checkbox
        html += '<td>';
        if (record.has_suggestion) {
            html += `<input type="checkbox" class="record-checkbox" value="${record.id}">`;
        }
        html += '</td>';
        
        // Teams User ID
        html += `<td>${this.escapeHtml(record.teams_user_id)}</td>`;
        
        // Duration
        html += `<td>${this.formatDuration(record.attendance_duration)}</td>`;
        
        // Suggestion
        html += '<td>';
        if (record.has_suggestion && record.suggestion) {
            const user = record.suggestion.user;
            const confidence = record.suggestion.confidence;
            const type = record.suggestion.type;
            
            html += `<span class="badge badge-${confidence === 'high' ? 'success' : 'warning'}">`;
            html += this.escapeHtml(user.firstname + ' ' + user.lastname);
            html += '</span>';
            html += `<br><small class="text-muted">${type} match</small>`;
        } else {
            html += `<span class="text-muted">${this.strings.no_suggestion}</span>`;
        }
        html += '</td>';
        
        // Actions
        html += '<td>';
        if (record.has_suggestion) {
            html += `<button class="btn btn-sm btn-success apply-suggestion-btn" `;
            html += `data-record-id="${record.id}" `;
            html += `data-user-id="${record.suggestion.user.id}">`;
            html += this.strings.apply_suggestion;
            html += '</button>';
        }
        html += '</td>';
        
        html += '</tr>';
        return html;
    }

    renderPagination(pagination) {
        let html = '<nav aria-label="Pagination">';
        html += '<ul class="pagination justify-content-center">';
        
        // Previous button
        html += `<li class="page-item ${pagination.has_previous ? '' : 'disabled'}">`;
        html += `<a class="page-link" href="#" data-page="${pagination.page - 1}">`;
        html += this.strings.previous;
        html += '</a></li>';
        
        // Page numbers
        const startPage = Math.max(0, pagination.page - 2);
        const endPage = Math.min(pagination.total_pages - 1, pagination.page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === pagination.page ? 'active' : ''}">`;
            html += `<a class="page-link" href="#" data-page="${i}">${i + 1}</a>`;
            html += '</li>';
        }
        
        // Next button
        html += `<li class="page-item ${pagination.has_next ? '' : 'disabled'}">`;
        html += `<a class="page-link" href="#" data-page="${pagination.page + 1}">`;
        html += this.strings.next;
        html += '</a></li>';
        html += '</ul>';
        
        // Page info
        html += '<div class="text-center mt-2">';
        html += `${this.strings.page} ${pagination.page + 1} `;
        html += `${this.strings.of} ${pagination.total_pages} `;
        html += `(${pagination.total_count} ${this.strings.total_records})`;
        html += '</div>';
        html += '</nav>';
        
        $('#pagination-container').html(html);
        
        // Bind pagination events
        $('.page-link').click((e) => {
            e.preventDefault();
            const page = parseInt($(e.target).data('page'));
            if (page >= 0 && page < pagination.total_pages) {
                this.loadPage(page);
            }
        });
    }

    bindTableEvents() {
        // Select all checkbox
        $('#select-all').change((e) => {
            const isChecked = $(e.target).prop('checked');
            $('.record-checkbox').prop('checked', isChecked);
            
            if (isChecked) {
                $('.record-checkbox').each((i, el) => {
                    this.selectedRecords.add(parseInt($(el).val()));
                });
            } else {
                this.selectedRecords.clear();
            }
            this.updateBulkButton();
        });
        
        // Individual checkboxes
        $('.record-checkbox').change((e) => {
            const recordId = parseInt($(e.target).val());
            
            if ($(e.target).prop('checked')) {
                this.selectedRecords.add(recordId);
            } else {
                this.selectedRecords.delete(recordId);
            }
            
            this.updateBulkButton();
            
            // Update select all checkbox
            const totalCheckboxes = $('.record-checkbox').length;
            const checkedCheckboxes = $('.record-checkbox:checked').length;
            $('#select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
        
        // Apply suggestion buttons
        $('.apply-suggestion-btn').click((e) => {
            const recordId = $(e.target).data('record-id');
            const userId = $(e.target).data('user-id');
            this.applySingleSuggestion(recordId, userId, $(e.target));
        });
    }

    updateBulkButton() {
        const count = this.selectedRecords.size;
        $('#bulk-assign-btn').prop('disabled', count === 0);
        $('#bulk-assign-btn').text(`${this.strings.apply_selected} (${count})`);
    }

    applySingleSuggestion(recordId, userId, button) {
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
            success: (response) => {
                if (response.success) {
                    $(`tr[data-record-id="${recordId}"]`).fadeOut();
                    this.selectedRecords.delete(recordId);
                    this.updateBulkButton();
                    sessionStorage.clear();
                    this.showSuccess(response.message);
                } else {
                    this.showError(response.error);
                    button.prop('disabled', false).text(this.strings.apply_suggestion);
                }
            },
            error: () => {
                this.showError('Connection error');
                button.prop('disabled', false).text(this.strings.apply_suggestion);
            }
        });
    }

    performBulkAssignment() {
        if (this.selectedRecords.size === 0) return;
        
        $('#progress-container').show();
        $('#bulk-assign-btn').prop('disabled', true);
        
        const assignments = {};
        this.selectedRecords.forEach(recordId => {
            const row = $(`tr[data-record-id="${recordId}"]`);
            const button = row.find('.apply-suggestion-btn');
            if (button.length) {
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
            success: (response) => {
                if (response.success) {
                    const result = response.data;
                    
                    $('#progress-bar').css('width', '100%').addClass('bg-success');
                    $('#progress-text').text(`Complete: ${result.successful}/${result.total} successful`);
                    
                    this.selectedRecords.clear();
                    sessionStorage.clear();
                    
                    setTimeout(() => {
                        $('#progress-container').hide();
                        this.loadPage(this.currentPage, true);
                    }, 2000);
                    
                    this.showSuccess(`Bulk assignment completed: ${result.successful} successful, ${result.failed} failed`);
                } else {
                    this.showError(response.error);
                }
            },
            error: () => {
                this.showError('Connection error during bulk assignment');
            },
            complete: () => {
                $('#bulk-assign-btn').prop('disabled', false);
            }
        });
    }

    // Utility functions
    formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + minutes + 'm';
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    showSuccess(message) {
        this.showToast(message, 'success', 5000);
    }

    showError(message) {
        this.showToast(message, 'danger', 8000);
    }

    showToast(message, type, duration) {
        const toast = $(`<div class="alert alert-${type} alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">` +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            message + '</div>');
        $('body').append(toast);
        setTimeout(() => toast.remove(), duration);
    }
}
