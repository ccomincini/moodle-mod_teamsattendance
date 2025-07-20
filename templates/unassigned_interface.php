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
    .card-name-suggestions {
        background: linear-gradient(135deg, #2196f3, #64b5f6);
    }
    .card-email-suggestions {
        background: linear-gradient(135deg, #9c27b0, #ba68c8);
    }
    </style>';
    
    // Performance Dashboard with Suggestion Counters
    $output .= '<div class="teamsattendance-performance-container">';
    $output .= '<div class="row mb-4">';
    
    // Total Records Card
    $output .= '<div class="col-md-3">';
    $output .= '<div class="card text-white bg-primary">';
    $output .= '<div class="card-body">';
    $output .= '<h5 class="card-title">' . get_string('total_records', 'teamsattendance') . '</h5>';
    $output .= '<h2 class="card-text">' . $context->total_records . '</h2>';
    $output .= '<small>Non assegnati</small>';
    $output .= '</div></div></div>';
    
    // Name-based Suggestions Card (Light Blue)
    $output .= '<div class="col-md-3">';
    $output .= '<div class="card text-white card-name-suggestions">';
    $output .= '<div class="card-body">';
    $output .= '<h5 class="card-title">' . get_string('name_suggestions_count', 'teamsattendance') . '</h5>';
    $output .= '<h2 class="card-text">' . $context->name_suggestions_count . '</h2>';
    $output .= '<small>Corrispondenze per nome</small>';
    $output .= '</div></div></div>';
    
    // Email-based Suggestions Card (Light Purple)
    $output .= '<div class="col-md-3">';
    $output .= '<div class="card text-white card-email-suggestions">';
    $output .= '<div class="card-body">';
    $output .= '<h5 class="card-title">' . get_string('email_suggestions_count', 'teamsattendance') . '</h5>';
    $output .= '<h2 class="card-text">' . $context->email_suggestions_count . '</h2>';
    $output .= '<small>Desunte da email</small>';
    $output .= '</div></div></div>';
    
    // Available Users Card
    $output .= '<div class="col-md-3">';
    $output .= '<div class="card text-white bg-success">';
    $output .= '<div class="card-body">';
    $output .= '<h5 class="card-title">' . get_string('available_users', 'teamsattendance') . '</h5>';
    $output .= '<h2 class="card-text">' . $context->available_users_count . '</h2>';
    $output .= '<small>' . get_string('for_assignment', 'teamsattendance') . '</small>';
    $output .= '</div></div></div>';
    
    $output .= '</div>'; // End row
    
    // Filter and Control Panel
    $output .= '<div class="card mb-4">';
    $output .= '<div class="card-body">';
    $output .= '<div class="row">';
    
    // Filter Select with new options
    $output .= '<div class="col-md-4">';
    $output .= '<label for="filter-select">' . get_string('filter_by', 'teamsattendance') . ':</label>';
    $output .= '<select id="filter-select" class="form-control">';
    $output .= '<option value="all">' . get_string('filter_all', 'teamsattendance') . '</option>';
    $output .= '<option value="name_suggestions">' . get_string('filter_name_suggestions', 'teamsattendance') . '</option>';
    $output .= '<option value="email_suggestions">' . get_string('filter_email_suggestions', 'teamsattendance') . '</option>';
    $output .= '<option value="without_suggestions">' . get_string('without_suggestions', 'teamsattendance') . '</option>';
    $output .= '</select>';
    $output .= '</div>';
    
    // Page Size Select
    $output .= '<div class="col-md-4">';
    $output .= '<label for="page-size-select">' . get_string('records_per_page', 'teamsattendance') . ':</label>';
    $output .= '<select id="page-size-select" class="form-control">';
    $output .= '<option value="10">10</option>';
    $output .= '<option value="15"' . ($context->per_page == 15 ? ' selected' : '') . '>15</option>';
    $output .= '<option value="20"' . ($context->per_page == 20 ? ' selected' : '') . '>20</option>';
    $output .= '<option value="25"' . ($context->per_page == 25 ? ' selected' : '') . '>25</option>';
    $output .= '<option value="50"' . ($context->per_page == 50 ? ' selected' : '') . '>50</option>';
    $output .= '<option value="100">100 (' . get_string('advanced_users', 'teamsattendance') . ')</option>';
    $output .= '</select>';
    $output .= '</div>';
    
    // Action Buttons
    $output .= '<div class="col-md-4">';
    $output .= '<label>&nbsp;</label><br>';
    $output .= '<button id="refresh-btn" class="btn btn-secondary">';
    $output .= '<i class="fa fa-refresh"></i> ' . get_string('refresh', 'teamsattendance');
    $output .= '</button> ';
    $output .= '<button id="bulk-assign-btn" class="btn btn-success" disabled>';
    $output .= '<i class="fa fa-check-circle"></i> ' . get_string('apply_selected', 'teamsattendance');
    $output .= '</button>';
    $output .= '</div>';
    
    $output .= '</div></div></div>'; // End row, card-body, card
    
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
