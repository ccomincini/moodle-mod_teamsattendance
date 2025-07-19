<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Performance-optimized manage unassigned Teams attendance records
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/teamsattendance/lib.php');

// Load performance-optimized components
require_once($CFG->dirroot . '/mod/teamsattendance/classes/performance_data_handler.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/ui_renderer.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/ui_assets_manager.php');

// Get parameters
$id = required_param('id', PARAM_INT); // Course module ID
$page = optional_param('page', 0, PARAM_INT);
$per_page = optional_param('per_page', 0, PARAM_INT);
$filter = optional_param('filter', 'all', PARAM_ALPHA);
$action = optional_param('action', '', PARAM_ALPHA);

// AJAX parameters
$ajax = optional_param('ajax', 0, PARAM_INT);
$recordid = optional_param('recordid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

// Initialize Moodle objects
$cm = get_coursemodule_from_id('teamsattendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamsattendance = $DB->get_record('teamsattendance', array('id' => $cm->instance), '*', MUST_EXIST);

// Security checks
require_login($course, true, $cm);
require_capability('mod/teamsattendance:manageattendance', context_module::instance($cm->id));

// Setup page
$PAGE->set_url('/mod/teamsattendance/manage_unassigned.php', array('id' => $cm->id));
$PAGE->set_title(format_string($teamsattendance->name . ' - ' . get_string('manage_unassigned', 'teamsattendance')));
$PAGE->set_heading(format_string($course->fullname));

// Initialize performance handler
$performance_handler = new performance_data_handler($cm, $teamsattendance, $course);

// Get performance statistics first
$perf_stats = $performance_handler->get_performance_statistics();

// Set optimal page size if not specified
if ($per_page <= 0) {
    $per_page = $perf_stats['recommended_page_size'];
}

// ========================= AJAX HANDLERS =========================

if ($ajax) {
    header('Content-Type: application/json');
    
    try {
        switch ($action) {
            case 'load_page':
                $paginated_data = $performance_handler->get_unassigned_records_paginated($page, $per_page, $filter);
                
                if ($filter === 'with_suggestions' || $filter === 'without_suggestions') {
                    $paginated_data = $performance_handler->filter_records_by_suggestions($paginated_data, $filter);
                }
                
                // Get suggestions for current page
                $suggestions = $performance_handler->get_suggestions_for_batch($paginated_data['records']);
                
                // Prepare data for frontend
                $response_data = array(
                    'records' => array(),
                    'pagination' => array(
                        'page' => $paginated_data['page'],
                        'per_page' => $paginated_data['per_page'],
                        'total_pages' => $paginated_data['total_pages'],
                        'total_count' => $paginated_data['total_count'],
                        'has_next' => $paginated_data['has_next'],
                        'has_previous' => $paginated_data['has_previous']
                    )
                );
                
                foreach ($paginated_data['records'] as $record) {
                    $record_data = array(
                        'id' => $record->id,
                        'teams_user_id' => $record->teams_user_id,
                        'attendance_duration' => $record->attendance_duration,
                        'has_suggestion' => isset($suggestions[$record->id]),
                        'suggestion' => isset($suggestions[$record->id]) ? $suggestions[$record->id] : null
                    );
                    $response_data['records'][] = $record_data;
                }
                
                echo json_encode(array('success' => true, 'data' => $response_data));
                break;
                
            case 'assign_user':
                if ($recordid && $userid && confirm_sesskey()) {
                    // Use original assignment handler for single assignments
                    require_once($CFG->dirroot . '/mod/teamsattendance/classes/user_assignment_handler.php');
                    $assignment_handler = new user_assignment_handler($cm, $teamsattendance, $course);
                    $result = $assignment_handler->assign_single_user($recordid, $userid);
                    
                    if ($result['success']) {
                        // Clear cache after assignment
                        $performance_handler->clear_cache();
                        echo json_encode(array('success' => true, 'message' => 'User assigned successfully'));
                    } else {
                        echo json_encode(array('success' => false, 'error' => $result['error']));
                    }
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Invalid parameters'));
                }
                break;
                
            case 'bulk_assign':
                if (confirm_sesskey()) {
                    $assignments = optional_param_array('assignments', array(), PARAM_INT);
                    $result = $performance_handler->apply_bulk_assignments_with_progress($assignments);
                    
                    echo json_encode(array(
                        'success' => true,
                        'data' => $result
                    ));
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Invalid session'));
                }
                break;
                
            default:
                echo json_encode(array('success' => false, 'error' => 'Unknown action'));
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    
    exit;
}

// ========================= PAGE OUTPUT =========================

echo $OUTPUT->header();

// Show performance warning for large datasets
if ($perf_stats['performance_level'] === 'challenging') {
    echo $OUTPUT->notification(
        get_string('performance_challenging', 'teamsattendance') . ' ' .
        get_string('estimated_time', 'teamsattendance') . ': ' . $perf_stats['estimated_suggestion_time'],
        'warning'
    );
}

echo $OUTPUT->heading(get_string('manage_unassigned', 'teamsattendance'));

// Add custom CSS and performance-optimized JavaScript
echo ui_assets_manager::render_custom_css();
?>

<div class="teamsattendance-performance-container">
    <!-- Performance Dashboard -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title"><?php echo get_string('total_records', 'teamsattendance'); ?></h5>
                    <h2 class="card-text"><?php echo $perf_stats['unassigned_records']; ?></h2>
                    <small><?php echo get_string('performance_level', 'teamsattendance'); ?>: <?php echo $perf_stats['performance_level']; ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-info">
                <div class="card-body">
                    <h5 class="card-title"><?php echo get_string('recommended_page_size', 'teamsattendance'); ?></h5>
                    <h2 class="card-text"><?php echo $perf_stats['recommended_page_size']; ?></h2>
                    <small><?php echo get_string('records_per_page', 'teamsattendance'); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title"><?php echo get_string('available_users', 'teamsattendance'); ?></h5>
                    <h2 class="card-text"><?php echo $perf_stats['available_users_count']; ?></h2>
                    <small><?php echo get_string('for_assignment', 'teamsattendance'); ?></small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-white bg-warning">
                <div class="card-body">
                    <h5 class="card-title"><?php echo get_string('estimated_time', 'teamsattendance'); ?></h5>
                    <h2 class="card-text" style="font-size: 1.2rem;"><?php echo $perf_stats['estimated_suggestion_time']; ?></h2>
                    <small><?php echo get_string('for_suggestions', 'teamsattendance'); ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter and Control Panel -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">
                    <label for="filter-select"><?php echo get_string('filter_by', 'teamsattendance'); ?>:</label>
                    <select id="filter-select" class="form-control">
                        <option value="all"><?php echo get_string('filter_all', 'teamsattendance'); ?></option>
                        <option value="with_suggestions"><?php echo get_string('with_suggestions', 'teamsattendance'); ?></option>
                        <option value="without_suggestions"><?php echo get_string('without_suggestions', 'teamsattendance'); ?></option>
                        <option value="long_duration"><?php echo get_string('filter_long_duration', 'teamsattendance'); ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="page-size-select"><?php echo get_string('records_per_page', 'teamsattendance'); ?>:</label>
                    <select id="page-size-select" class="form-control">
                        <option value="10">10</option>
                        <option value="15" <?php echo $per_page == 15 ? 'selected' : ''; ?>>15</option>
                        <option value="20" <?php echo $per_page == 20 ? 'selected' : ''; ?>>20</option>
                        <option value="25" <?php echo $per_page == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $per_page == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100">100 (<?php echo get_string('advanced_users', 'teamsattendance'); ?>)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label>&nbsp;</label><br>
                    <button id="refresh-btn" class="btn btn-secondary">
                        <i class="fa fa-refresh"></i> <?php echo get_string('refresh', 'teamsattendance'); ?>
                    </button>
                    <button id="bulk-assign-btn" class="btn btn-success" disabled>
                        <i class="fa fa-check-circle"></i> <?php echo get_string('apply_selected', 'teamsattendance'); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Indicator -->
    <div id="loading-indicator" class="text-center mb-4" style="display: none;">
        <div class="spinner-border text-primary" role="status">
            <span class="sr-only"><?php echo get_string('loading', 'teamsattendance'); ?>...</span>
        </div>
        <p class="mt-2"><?php echo get_string('loading', 'teamsattendance'); ?>...</p>
    </div>

    <!-- Progress Bar for Bulk Operations -->
    <div id="progress-container" class="mb-4" style="display: none;">
        <div class="card">
            <div class="card-body">
                <h5><?php echo get_string('bulk_assignment_progress', 'teamsattendance'); ?></h5>
                <div class="progress">
                    <div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                </div>
                <p id="progress-text" class="mt-2">0%</p>
            </div>
        </div>
    </div>

    <!-- Records Table -->
    <div class="card">
        <div class="card-body">
            <div id="records-container">
                <!-- Records will be loaded here via AJAX -->
                <div class="text-center text-muted">
                    <p><?php echo get_string('loading_initial_data', 'teamsattendance'); ?>...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div id="pagination-container" class="mt-4">
        <!-- Pagination will be generated here -->
    </div>

    <!-- Hidden form for bulk operations -->
    <form id="bulk-form" method="post" style="display: none;">
        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
        <input type="hidden" name="action" value="bulk_assign">
        <input type="hidden" name="ajax" value="1">
    </form>
</div>

<script>
// Performance-optimized JavaScript
$(document).ready(function() {
    let currentPage = 0;
    let currentFilter = 'all';
    let currentPageSize = <?php echo $per_page; ?>;
    let selectedRecords = new Set();
    let isLoading = false;

    // Load initial data
    loadPage(0);

    // Event handlers
    $('#filter-select').change(function() {
        currentFilter = $(this).val();
        currentPage = 0;
        selectedRecords.clear();
        updateBulkButton();
        loadPage(0);
    });

    $('#page-size-select').change(function() {
        currentPageSize = parseInt($(this).val());
        currentPage = 0;
        loadPage(0);
    });

    $('#refresh-btn').click(function() {
        loadPage(currentPage, true);
    });

    $('#bulk-assign-btn').click(function() {
        if (selectedRecords.size > 0) {
            performBulkAssignment();
        }
    });

    // Functions
    function loadPage(page, forceRefresh = false) {
        if (isLoading) return;
        
        isLoading = true;
        $('#loading-indicator').show();
        
        const cacheKey = `page_${page}_${currentFilter}_${currentPageSize}`;
        
        if (!forceRefresh && sessionStorage.getItem(cacheKey)) {
            const cachedData = JSON.parse(sessionStorage.getItem(cacheKey));
            renderPage(cachedData);
            isLoading = false;
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
                per_page: currentPageSize,
                filter: currentFilter
            },
            success: function(response) {
                if (response.success) {
                    sessionStorage.setItem(cacheKey, JSON.stringify(response.data));
                    renderPage(response.data);
                } else {
                    showError('Failed to load data: ' + (response.error || 'Unknown error'));
                }
            },
            error: function(xhr, status, error) {
                showError('Connection error: ' + error);
            },
            complete: function() {
                isLoading = false;
                $('#loading-indicator').hide();
            }
        });
    }

    function renderPage(data) {
        currentPage = data.pagination.page;
        
        // Render table
        let html = '<div class="table-responsive">';
        html += '<table class="table table-striped table-hover">';
        html += '<thead class="thead-dark">';
        html += '<tr>';
        html += '<th><input type="checkbox" id="select-all"></th>';
        html += '<th><?php echo get_string('teams_user_id', 'teamsattendance'); ?></th>';
        html += '<th><?php echo get_string('attendance_duration', 'teamsattendance'); ?></th>';
        html += '<th><?php echo get_string('suggested_match', 'teamsattendance'); ?></th>';
        html += '<th><?php echo get_string('actions', 'teamsattendance'); ?></th>';
        html += '</tr>';
        html += '</thead>';
        html += '<tbody>';

        if (data.records.length === 0) {
            html += '<tr><td colspan="5" class="text-center text-muted">';
            html += '<?php echo get_string('no_records_found', 'teamsattendance'); ?>';
            html += '</td></tr>';
        } else {
            data.records.forEach(function(record) {
                html += '<tr data-record-id="' + record.id + '">';
                
                // Checkbox
                html += '<td>';
                if (record.has_suggestion) {
                    html += '<input type="checkbox" class="record-checkbox" value="' + record.id + '">';
                }
                html += '</td>';
                
                // Teams User ID
                html += '<td>' + escapeHtml(record.teams_user_id) + '</td>';
                
                // Duration
                html += '<td>' + formatDuration(record.attendance_duration) + '</td>';
                
                // Suggestion
                html += '<td>';
                if (record.has_suggestion && record.suggestion) {
                    const user = record.suggestion.user;
                    const confidence = record.suggestion.confidence;
                    const type = record.suggestion.type;
                    
                    html += '<span class="badge badge-' + (confidence === 'high' ? 'success' : 'warning') + '">';
                    html += escapeHtml(user.firstname + ' ' + user.lastname);
                    html += '</span>';
                    html += '<br><small class="text-muted">' + type + ' match</small>';
                } else {
                    html += '<span class="text-muted"><?php echo get_string('no_suggestion', 'teamsattendance'); ?></span>';
                }
                html += '</td>';
                
                // Actions
                html += '<td>';
                if (record.has_suggestion) {
                    html += '<button class="btn btn-sm btn-success apply-suggestion-btn" ';
                    html += 'data-record-id="' + record.id + '" ';
                    html += 'data-user-id="' + record.suggestion.user.id + '">';
                    html += '<?php echo get_string('apply_suggestion', 'teamsattendance'); ?>';
                    html += '</button>';
                }
                html += '</td>';
                
                html += '</tr>';
            });
        }

        html += '</tbody></table></div>';
        
        $('#records-container').html(html);
        
        // Render pagination
        renderPagination(data.pagination);
        
        // Update select all checkbox
        $('#select-all').prop('checked', false);
        updateBulkButton();
        
        // Bind events
        bindTableEvents();
    }

    function renderPagination(pagination) {
        let html = '<nav aria-label="Pagination">';
        html += '<ul class="pagination justify-content-center">';
        
        // Previous button
        html += '<li class="page-item ' + (pagination.has_previous ? '' : 'disabled') + '">';
        html += '<a class="page-link" href="#" data-page="' + (pagination.page - 1) + '">';
        html += '<?php echo get_string('previous', 'teamsattendance'); ?></a>';
        html += '</li>';
        
        // Page numbers (simplified for performance)
        const startPage = Math.max(0, pagination.page - 2);
        const endPage = Math.min(pagination.total_pages - 1, pagination.page + 2);
        
        for (let i = startPage; i <= endPage; i++) {
            html += '<li class="page-item ' + (i === pagination.page ? 'active' : '') + '">';
            html += '<a class="page-link" href="#" data-page="' + i + '">' + (i + 1) + '</a>';
            html += '</li>';
        }
        
        // Next button
        html += '<li class="page-item ' + (pagination.has_next ? '' : 'disabled') + '">';
        html += '<a class="page-link" href="#" data-page="' + (pagination.page + 1) + '">';
        html += '<?php echo get_string('next', 'teamsattendance'); ?></a>';
        html += '</li>';
        
        html += '</ul>';
        
        // Page info
        html += '<div class="text-center mt-2">';
        html += '<?php echo get_string('page', 'teamsattendance'); ?> ' + (pagination.page + 1) + ' ';
        html += '<?php echo get_string('of', 'teamsattendance'); ?> ' + pagination.total_pages + ' ';
        html += '(' + pagination.total_count + ' <?php echo get_string('total_records', 'teamsattendance'); ?>)';
        html += '</div>';
        
        html += '</nav>';
        
        $('#pagination-container').html(html);
        
        // Bind pagination events
        $('.page-link').click(function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page >= 0 && page < pagination.total_pages) {
                loadPage(page);
            }
        });
    }

    function bindTableEvents() {
        // Select all checkbox
        $('#select-all').change(function() {
            const isChecked = $(this).prop('checked');
            $('.record-checkbox').prop('checked', isChecked);
            
            if (isChecked) {
                $('.record-checkbox').each(function() {
                    selectedRecords.add(parseInt($(this).val()));
                });
            } else {
                selectedRecords.clear();
            }
            updateBulkButton();
        });
        
        // Individual checkboxes
        $('.record-checkbox').change(function() {
            const recordId = parseInt($(this).val());
            
            if ($(this).prop('checked')) {
                selectedRecords.add(recordId);
            } else {
                selectedRecords.delete(recordId);
            }
            
            updateBulkButton();
            
            // Update select all checkbox
            const totalCheckboxes = $('.record-checkbox').length;
            const checkedCheckboxes = $('.record-checkbox:checked').length;
            $('#select-all').prop('checked', totalCheckboxes === checkedCheckboxes);
        });
        
        // Apply suggestion buttons
        $('.apply-suggestion-btn').click(function() {
            const recordId = $(this).data('record-id');
            const userId = $(this).data('user-id');
            applySingleSuggestion(recordId, userId, $(this));
        });
    }

    function updateBulkButton() {
        const count = selectedRecords.size;
        $('#bulk-assign-btn').prop('disabled', count === 0);
        $('#bulk-assign-btn').text('<?php echo get_string('apply_selected', 'teamsattendance'); ?> (' + count + ')');
    }

    function applySingleSuggestion(recordId, userId, button) {
        button.prop('disabled', true).text('<?php echo get_string('applying', 'teamsattendance'); ?>...');
        
        $.ajax({
            url: window.location.href,
            method: 'POST',
            data: {
                ajax: 1,
                action: 'assign_user',
                recordid: recordId,
                userid: userId,
                sesskey: '<?php echo sesskey(); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Remove row from table
                    $('tr[data-record-id="' + recordId + '"]').fadeOut();
                    selectedRecords.delete(recordId);
                    updateBulkButton();
                    
                    // Clear cache
                    sessionStorage.clear();
                    
                    showSuccess(response.message);
                } else {
                    showError(response.error);
                    button.prop('disabled', false).text('<?php echo get_string('apply_suggestion', 'teamsattendance'); ?>');
                }
            },
            error: function() {
                showError('Connection error');
                button.prop('disabled', false).text('<?php echo get_string('apply_suggestion', 'teamsattendance'); ?>');
            }
        });
    }

    function performBulkAssignment() {
        if (selectedRecords.size === 0) return;
        
        $('#progress-container').show();
        $('#bulk-assign-btn').prop('disabled', true);
        
        const assignments = {};
        selectedRecords.forEach(recordId => {
            // Get the suggested user ID for this record
            const row = $('tr[data-record-id="' + recordId + '"]');
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
                sesskey: '<?php echo sesskey(); ?>'
            },
            success: function(response) {
                if (response.success) {
                    const result = response.data;
                    
                    // Update progress
                    const successRate = (result.successful / result.total) * 100;
                    $('#progress-bar').css('width', '100%').addClass('bg-success');
                    $('#progress-text').text(`Complete: ${result.successful}/${result.total} successful`);
                    
                    // Clear selections and cache
                    selectedRecords.clear();
                    sessionStorage.clear();
                    
                    // Reload current page
                    setTimeout(() => {
                        $('#progress-container').hide();
                        loadPage(currentPage, true);
                    }, 2000);
                    
                    showSuccess(`Bulk assignment completed: ${result.successful} successful, ${result.failed} failed`);
                } else {
                    showError(response.error);
                }
            },
            error: function() {
                showError('Connection error during bulk assignment');
            },
            complete: function() {
                $('#bulk-assign-btn').prop('disabled', false);
            }
        });
    }

    // Utility functions
    function formatDuration(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        return hours + 'h ' + minutes + 'm';
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function showSuccess(message) {
        // Simple toast notification
        const toast = $('<div class="alert alert-success alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">' +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            message + '</div>');
        $('body').append(toast);
        setTimeout(() => toast.remove(), 5000);
    }

    function showError(message) {
        const toast = $('<div class="alert alert-danger alert-dismissible fade show position-fixed" style="top: 20px; right: 20px; z-index: 9999;">' +
            '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
            message + '</div>');
        $('body').append(toast);
        setTimeout(() => toast.remove(), 8000);
    }
});
</script>

<?php
echo $OUTPUT->footer();
?>
