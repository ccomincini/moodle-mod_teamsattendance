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
    </style>';
    
    $output .= '<div class="teamsattendance-performance-container">';
    
    // Filter and Control Panel
    $output .= '<div class="card mb-4">';
    $output .= '<div class="card-body">';
    $output .= '<div class="row">';
    
    // Filter Select with default "all"
    $output .= '<div class="col-md-4">';
    $output .= '<label for="filter-select">' . get_string('filter_by', 'teamsattendance') . ':</label>';
    $output .= '<select id="filter-select" class="form-control">';
    $output .= '<option value="all" selected>' . get_string('filter_all', 'teamsattendance') . '</option>';
    $output .= '<option value="name_suggestions">' . get_string('filter_name_suggestions', 'teamsattendance') . '</option>';
    $output .= '<option value="email_suggestions">' . get_string('filter_email_suggestions', 'teamsattendance') . '</option>';
    $output .= '<option value="without_suggestions">' . get_string('without_suggestions', 'teamsattendance') . '</option>';
    $output .= '</select>';
    $output .= '</div>';
    
    // Page Size Select with new options and default 20
    $output .= '<div class="col-md-4">';
    $output .= '<label for="page-size-select">' . get_string('records_per_page', 'teamsattendance') . ':</label>';
    $output .= '<select id="page-size-select" class="form-control">';
    $output .= '<option value="20" selected>20</option>';
    $output .= '<option value="50">50</option>';
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
    
    // Records Table Container
    $output .= '<div class="card">';
    $output .= '<div class="card-body">';
    $output .= '<div id="records-container">';
    $output .= '<div class="text-center text-muted">';
    $output .= '<p>' . get_string('loading_initial_data', 'teamsattendance') . '...</p>';
    $output .= '</div>';
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
