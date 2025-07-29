<?php
/**
 * Template for unassigned records management interface
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Render the unassigned records management interface
 * @param object $context Template context with performance stats and configuration
 * @return string HTML output
 */
function render_unassigned_interface($context) {
    $output = '';
    
    // Add custom CSS for suggestion type backgrounds
    $output .= '<style>
    .suggestion-name-row {
        background-color: #e3f2fd !important; /* Light blue */
    }
    .suggestion-email-row {
        background-color: #f3e5f5 !important; /* Light purple */
    }
    .suggestion-none-row {
        background-color: #ffffff !important; /* White */
    }
    .counter-card-name {
        background-color: #e3f2fd;
        border: 1px solid #bbdefb;
    }
    .counter-card-email {
        background-color: #f3e5f5;
        border: 1px solid #e1bee7;
    }
    .counter-card-none {
        background-color: #ffffff;
        border: 1px solid #e0e0e0;
    }
    .counter-card {
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        margin-bottom: 10px;
    }
    .counter-number {
        font-weight: bold;
        font-size: 24px;
        display: block;
        margin-bottom: 5px;
    }
    .counter-label {
        font-size: 14px;
        color: #666;
    }
    .action-column {
        display: flex;
        flex-direction: column;
        gap: 5px;
        align-items: stretch;
    }
    .manual-select-container {
        display: flex;
        gap: 5px;
        align-items: center;
    }
    .manual-user-select {
        flex: 1;
        min-width: 120px;
    }
    .assign-btn {
        white-space: nowrap;
    }
    </style>';
    
    $output .= '<div class="teamsattendance-performance-container">';
    
    // Filter and Control Panel
    $output .= '<div class="card mb-4">';
    $output .= '<div class="card-body">';
    $output .= '<div class="row">';
    
    // Filter Select with current filter selected
    $current_filter = isset($context->current_filter) ? $context->current_filter : 'all';
    $output .= '<div class="col-md-3">';
    $output .= '<label for="filter-select">' . get_string('filter_by', 'teamsattendance') . ':</label>';
    $output .= '<select id="filter-select" class="form-control">';
    $output .= '<option value="all"' . ($current_filter === 'all' ? ' selected' : '') . '>' . get_string('filter_all', 'teamsattendance') . '</option>';
    $output .= '<option value="name_suggestions"' . ($current_filter === 'name_suggestions' ? ' selected' : '') . '>' . get_string('filter_name_suggestions', 'teamsattendance') . '</option>';
    $output .= '<option value="email_suggestions"' . ($current_filter === 'email_suggestions' ? ' selected' : '') . '>' . get_string('filter_email_suggestions', 'teamsattendance') . '</option>';
    $output .= '<option value="without_suggestions"' . ($current_filter === 'without_suggestions' ? ' selected' : '') . '>' . get_string('without_suggestions', 'teamsattendance') . '</option>';
    $output .= '</select>';
    $output .= '</div>';

    // Page Size Select with new options and default 50 (FIXED)
    $output .= '<div class="col-md-3">';
    $output .= '<label for="page-size-select">' . get_string('records_per_page', 'teamsattendance') . ':</label>';
    $output .= '<select id="page-size-select" class="form-control">';
    $output .= '<option value="20">20</option>';
    $output .= '<option value="50" selected>50</option>'; // CHANGED: selected su 50 invece di 20
    $output .= '<option value="100">100</option>';
    $output .= '<option value="all">' . get_string('all_records', 'teamsattendance') . '</option>';
    $output .= '</select>';
    $output .= '</div>';
    
    // Action Buttons (solo bulk assign)
    $output .= '<div class="col-md-4">';
    $output .= '<label>&nbsp;</label><br>';
    $output .= '<button id="bulk-assign-btn" class="btn btn-success" disabled>';
    $output .= '<i class="fa fa-check-circle"></i> ' . get_string('apply_selected', 'teamsattendance');
    $output .= '</button>';
    $output .= '</div>';
    
    $output .= '</div></div></div>'; // End row, card-body, card
    
    // Card colorate per i contatori
    $output .= '<div class="row mb-4">';
    
    // Card suggerimenti dal nome (azzurro)
    $output .= '<div class="col-md-4">';
    $output .= '<div class="counter-card counter-card-name">';
    $output .= '<span class="counter-number" id="name-suggestions-count" style="color: #1976d2;">' . $context->name_suggestions_count . '</span>';
    $output .= '<div class="counter-label">Suggerimenti desunti dal nome</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    // Card suggerimenti dall'email (viola)
    $output .= '<div class="col-md-4">';
    $output .= '<div class="counter-card counter-card-email">';
    $output .= '<span class="counter-number" id="email-suggestions-count" style="color: #7b1fa2;">' . $context->email_suggestions_count . '</span>';
    $output .= '<div class="counter-label">Suggerimenti desunti dall\'email</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    // Card senza suggerimenti (senza sfondo)
    $output .= '<div class="col-md-4">';
    $output .= '<div class="counter-card counter-card-none">';
    $output .= '<span class="counter-number" id="no-suggestions-count" style="color: #424242;">' . ($context->total_records - $context->name_suggestions_count - $context->email_suggestions_count) . '</span>';
    $output .= '<div class="counter-label">Record non associati senza suggerimenti</div>';
    $output .= '</div>';
    $output .= '</div>';
    
    $output .= '</div>'; // End row
    
    // Loading Indicator
    $output .= '<div id="loading-indicator" class="text-center mb-4" style="display: none;">';
    $output .= '<div class="spinner-border text-primary" role="status">';
    $output .= '<span class="sr-only">' . get_string('loading', 'teamsattendance') . '...</span>';
    $output .= '</div>';
    $output .= '<p class="mt-2">' . get_string('loading', 'teamsattendance') . '...</p>';
    $output .= '</div>';
    
    // Progress Bar for Bulk Operations
    $output .= '<div id="progress-container" class="mb-4" style="display: none;">';
    $output .= '<div class="card">';
    $output .= '<div class="card-body">';
    $output .= '<h5>' . get_string('bulk_assignment_progress', 'teamsattendance') . '</h5>';
    $output .= '<div class="progress">';
    $output .= '<div id="progress-bar" class="progress-bar" role="progressbar" style="width: 0%"></div>';
    $output .= '</div>';
    $output .= '<p id="progress-text" class="mt-2">0%</p>';
    $output .= '</div></div></div>';
    
    // Records Table Container with initial data support
    $output .= '<div class="card">';
    $output .= '<div class="card-body">';
    $output .= '<div id="records-container">';

    // Check if initial data is provided
    if (isset($context->initial_data) && !empty($context->initial_data['records'])) {
        // Render initial data instead of loading message
        $output .= render_initial_records_table($context->initial_data);
    } else {
        // Fallback to loading message
        $output .= '<div class="text-center text-muted">';
        $output .= '<p>' . get_string('loading_initial_data', 'teamsattendance') . '...</p>';
        $output .= '</div>';
    }

    $output .= '</div>';
    $output .= '</div></div>';
    
    // Pagination Container
    $output .= '<div id="pagination-container" class="mt-4"></div>';
    
    // Hidden form for bulk operations
    $output .= '<form id="bulk-form" method="post" style="display: none;">';
    $output .= '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
    $output .= '<input type="hidden" name="action" value="bulk_assign">';
    $output .= '<input type="hidden" name="ajax" value="1">';
    $output .= '</form>';
    
    $output .= '</div>'; // End teamsattendance-performance-container
    
    return $output;
}

/**
 * Render initial records table with data
 * @param array $initial_data Initial records and pagination data
 * @return string HTML table
 */
function render_initial_records_table($initial_data) {
    $output = '';
    
    if (empty($initial_data['records'])) {
        return '<div class="text-center text-muted"><p>' . get_string('no_records_found', 'teamsattendance') . '</p></div>';
    }
    
    $output .= '<div class="table-responsive">';
    $output .= '<table class="table table-striped">';
    $output .= '<thead>';
    $output .= '<tr>';
    $output .= '<th><input type="checkbox" id="select-all" class="select-all-checkbox"></th>';
    $output .= '<th>' . get_string('teams_user_id', 'teamsattendance') . '</th>';
    $output .= '<th>' . get_string('attendance_duration', 'teamsattendance') . '</th>';
    $output .= '<th>' . get_string('suggested_match', 'teamsattendance') . '</th>';
    $output .= '<th>' . get_string('actions', 'teamsattendance') . '</th>';
    $output .= '</tr>';
    $output .= '</thead>';
    $output .= '<tbody>';
    
    foreach ($initial_data['records'] as $record) {
        $row_class = 'suggestion-' . $record['suggestion_type'] . '-row';
        $output .= '<tr class="' . $row_class . '" data-record-id="' . $record['id'] . '">';
        
        // Checkbox
        $checkbox_disabled = $record['has_suggestion'] ? '' : 'disabled';
        $checkbox_checked = $record['has_suggestion'] ? 'checked' : '';
        $output .= '<td><input type="checkbox" class="record-checkbox" data-record-id="' . $record['id'] . '" ' . $checkbox_disabled . ' ' . $checkbox_checked . '></td>';
        
        // Teams User ID
        $output .= '<td>' . htmlspecialchars($record['teams_user_id']) . '</td>';
        
        // Duration
        $output .= '<td>' . $record['attendance_duration'] . '</td>';
        
        // Suggestion
        $output .= '<td>';
        if ($record['has_suggestion']) {
            $suggestion = $record['suggestion'];
            $type_label = ($suggestion['type'] === 'name_based') ? 'nome' : 'email';
            $output .= '<span class="badge badge-info">' . htmlspecialchars($suggestion['user']->firstname . ' ' . $suggestion['user']->lastname) . '</span>';
            $output .= '<br><small>Suggerito da ' . $type_label . '</small>';
        } else {
            $output .= '<span class="text-muted">' . get_string('no_suggestion', 'teamsattendance') . '</span>';
        }
        $output .= '</td>';
        
        // Actions - ALWAYS show both suggestion button AND manual select
        $output .= '<td>';
        $output .= '<div class="action-column">';
        
        if ($record['has_suggestion']) {
            // Show suggestion button
            $output .= '<button class="btn btn-sm btn-success apply-suggestion-btn" data-record-id="' . $record['id'] . '" data-user-id="' . $record['suggestion']['user']->id . '">';
            $output .= get_string('apply_suggestion', 'teamsattendance');
            $output .= '</button>';
        }
        
        // ALWAYS show manual select (both with and without suggestions)
        $output .= '<div class="manual-select-container">';
        $output .= '<select class="form-control form-control-sm manual-user-select" data-record-id="' . $record['id'] . '">';
        $output .= '<option value="">' . get_string('select_user', 'teamsattendance') . '</option>';
        $output .= '<!-- Users will be loaded by JavaScript -->';
        $output .= '</select>';
        $output .= '<button class="btn btn-sm btn-primary assign-btn" data-record-id="' . $record['id'] . '" disabled>';
        $output .= get_string('assign', 'teamsattendance');
        $output .= '</button>';
        $output .= '</div>';
        
        $output .= '</div>';
        $output .= '</td>';
        
        $output .= '</tr>';
    }
    
    $output .= '</tbody>';
    $output .= '</table>';
    $output .= '</div>';
    
    return $output;
}
