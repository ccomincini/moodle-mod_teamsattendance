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
 * Gestione dei record non assegnati di Teams attendance (Versione Modulare Ottimizzata)
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    2.1.0
 */

require_once('../../config.php');
require_once($CFG->dirroot.'/mod/teamsattendance/lib.php');

// Carica le componenti ottimizzate per le performance
require_once($CFG->dirroot . '/mod/teamsattendance/classes/performance_data_handler.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');
require_once($CFG->dirroot . '/mod/teamsattendance/classes/ui_renderer.php');

// Carica le componenti modulari per l'interfaccia
require_once($CFG->dirroot . '/mod/teamsattendance/templates/unassigned_interface.php');

use mod_teamsattendance\performance_data_handler;

// Parametri della richiesta
$id = required_param('id', PARAM_INT); // ID del modulo corso
$page = optional_param('page', 0, PARAM_INT);
$per_page = optional_param('per_page', 20, PARAM_INT);
$filter = optional_param('filter', 'all', PARAM_TEXT);

// Validazione del parametro filtro per sicurezza
$allowed_filters = array('all', 'name_suggestions', 'email_suggestions', 'without_suggestions');
if (!in_array($filter, $allowed_filters)) {
    $filter = 'all';
}

$action = optional_param('action', '', PARAM_TEXT);

// Parametri per le chiamate AJAX
$ajax = optional_param('ajax', 0, PARAM_INT);
$recordid = optional_param('recordid', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

// Inizializza gli oggetti Moodle
$cm = get_coursemodule_from_id('teamsattendance', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
$teamsattendance = $DB->get_record('teamsattendance', array('id' => $cm->instance), '*', MUST_EXIST);

// Controlli di sicurezza
require_login($course, true, $cm);
require_capability('mod/teamsattendance:manageattendance', context_module::instance($cm->id));

// Configurazione della pagina
$PAGE->set_url('/mod/teamsattendance/manage_unassigned.php', array('id' => $cm->id));
$PAGE->set_title(format_string($teamsattendance->name . ' - ' . get_string('manage_unassigned', 'teamsattendance')));
$PAGE->set_heading(format_string($course->fullname));

// Inizializza il gestore delle performance
$performance_handler = new performance_data_handler($cm, $teamsattendance, $course);

// Ottiene le statistiche delle performance
$perf_stats = $performance_handler->get_performance_statistics();

// Imposta la dimensione pagina predefinita
if ($per_page <= 0) {
    $per_page = 20;
}

// SOLUZIONE: Limita rigorosamente agli utenti del corso CORRENTE
$context = context_course::instance($course->id);

// Query per utenti assegnati in questa sessione
$sql_assigned = "SELECT DISTINCT tad.userid
                FROM {teamsattendance_data} tad
                JOIN {user} u ON u.id = tad.userid
                WHERE tad.sessionid = ? 
                AND tad.userid IS NOT NULL 
                AND tad.userid > 0
                AND u.deleted = 0";
$assigned_userids = $DB->get_fieldset_sql($sql_assigned, array($teamsattendance->id));

// Query RISTRETTA: Solo utenti EFFETTIVAMENTE iscritti a QUESTO corso
$sql_enrolled = "SELECT DISTINCT u.id, u.firstname, u.lastname
                FROM {user} u
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = ?
                WHERE u.deleted = 0 
                AND u.suspended = 0
                AND ue.status = 0
                AND e.status = 0
                ORDER BY u.lastname ASC, u.firstname ASC";
$enrolled_users = $DB->get_records_sql($sql_enrolled, array($course->id));

error_log("DEBUG RESTRICTED: Course ID = " . $course->id);
error_log("DEBUG RESTRICTED: Found " . count($assigned_userids) . " assigned users");
error_log("DEBUG RESTRICTED: Found " . count($enrolled_users) . " enrolled users in THIS course");

// Prepara l'elenco degli utenti disponibili (non ancora assegnati)
$available_users = array();
foreach ($enrolled_users as $user) {
    if (!in_array($user->id, $assigned_userids)) {
        $available_users[] = array(
            'id' => $user->id,
            'name' => $user->lastname . ' ' . $user->firstname,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname
        );
    }
}

error_log("DEBUG RESTRICTED: Final available users count = " . count($available_users));

// Ordina gli utenti disponibili per cognome e nome
usort($available_users, function($a, $b) {
    $firstname_cmp = strcasecmp($a['firstname'], $b['firstname']);
    if ($firstname_cmp === 0) {
        return strcasecmp($a['lastname'], $b['lastname']);
    }
    return $firstname_cmp;
});

// Genera le statistiche dei suggerimenti per la visualizzazione
$unassigned_records = $performance_handler->get_all_unassigned_records();
$suggestion_engine = new suggestion_engine($enrolled_users);
$all_suggestions = $suggestion_engine->generate_suggestions($unassigned_records);
$suggestion_stats = $suggestion_engine->get_suggestion_statistics($all_suggestions);

// ========================= GESTORI AJAX =========================

if ($ajax) {
    // Sopprime avvisi/errori PHP per le chiamate AJAX
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Pulisce eventuali buffer di output
    if (ob_get_level()) {
        ob_clean();
    }
    header('Content-Type: application/json');

    try {
        switch ($action) {
            case 'load_page':
                // Ottiene i filtri dalla richiesta
                $filters_json = optional_param('filters', '{}', PARAM_RAW);
                $filters = json_decode($filters_json, true);
                if (!is_array($filters)) {
                    $filters = array();
                }
                
                // Gestisce la dimensione pagina - può essere "all" o numerica
                $per_page_param = optional_param('per_page', 50, PARAM_RAW);
                if ($per_page_param === 'all') {
                    $per_page = 'all';
                } else {
                    $per_page = (int)$per_page_param;
                }
                
                // Applica il filtro lato server
                $paginated_data = $performance_handler->get_unassigned_records_paginated($page, $per_page, $filters);
                
                // Ottiene i suggerimenti per i record della pagina corrente
                $suggestions = $performance_handler->get_suggestions_for_batch($paginated_data['records']);
                
                // Prepara i dati per il frontend
                $response_data = array(
                    'records' => array(),
                    'pagination' => array(
                        'page' => $paginated_data['page'],
                        'per_page' => $paginated_data['per_page'],
                        'total_pages' => $paginated_data['total_pages'],
                        'total_count' => $paginated_data['total_count'],
                        'has_next' => $paginated_data['has_next'],
                        'has_previous' => $paginated_data['has_previous'],
                        'show_all' => $paginated_data['show_all']
                    )
                );
                
                foreach ($paginated_data['records'] as $record) {
                    $suggestion = isset($suggestions[$record->id]) ? $suggestions[$record->id] : null;
                    $suggestion_type = 'none';
                    
                    if ($suggestion) {
                        $suggestion_type = $suggestion['type'];
                    }
                    
                    $record_data = array(
                        'id' => $record->id,
                        'teams_user_id' => $record->teams_user_id,
                        'attendance_duration' => $record->attendance_duration,
                        'has_suggestion' => isset($suggestions[$record->id]),
                        'suggestion' => $suggestion,
                        'suggestion_type' => $suggestion_type
                    );
                    $response_data['records'][] = $record_data;
                }

                echo json_encode(array('success' => true, 'data' => $response_data));
                break;
                
            case 'assign_user':
                if ($recordid && $userid && confirm_sesskey()) {
                    // Usa il gestore di assegnazioni originale per le assegnazioni singole
                    require_once($CFG->dirroot . '/mod/teamsattendance/classes/user_assignment_handler.php');
                    $assignment_handler = new user_assignment_handler($cm, $teamsattendance, $course);
                    $result = $assignment_handler->assign_single_user($recordid, $userid);
                    
                    if ($result['success']) {
                        // Pulisce la cache dopo l'assegnazione
                        $performance_handler->clear_cache();
                        echo json_encode(array('success' => true, 'message' => 'Utente assegnato con successo'));
                    } else {
                        echo json_encode(array('success' => false, 'error' => $result['error']));
                    }
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Parametri non validi'));
                }
                break;
                
            case 'bulk_assign':
                if (confirm_sesskey()) {
                    $assignments = optional_param_array('assignments', array(), PARAM_INT);
                    $result = $performance_handler->apply_bulk_assignments_with_progress($assignments);
                    
                    // Salva le preferenze per le assegnazioni in blocco
                    foreach ($assignments as $recordid => $userid) {
                        set_user_preference('teamsattendance_suggestion_applied_' . $recordid, $userid);
                    }
                    
                    echo json_encode(array(
                        'success' => true,
                        'data' => $result
                    ));
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Sessione non valida'));
                }
                break;
            
            case 'get_available_users':
                // Usa la stessa logica ristretta
                $ajax_sql_assigned = "SELECT DISTINCT tad.userid
                                    FROM {teamsattendance_data} tad
                                    JOIN {user} u ON u.id = tad.userid
                                    WHERE tad.sessionid = ? 
                                    AND tad.userid IS NOT NULL 
                                    AND tad.userid > 0
                                    AND u.deleted = 0";
                $ajax_assigned_userids = $DB->get_fieldset_sql($ajax_sql_assigned, array($teamsattendance->id));
                
                // Query RISTRETTA per utenti iscritti al corso
                $ajax_sql_enrolled = "SELECT DISTINCT u.id, u.firstname, u.lastname
                                    FROM {user} u
                                    JOIN {user_enrolments} ue ON ue.userid = u.id
                                    JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = ?
                                    WHERE u.deleted = 0 
                                    AND u.suspended = 0
                                    AND ue.status = 0
                                    AND e.status = 0
                                    ORDER BY u.lastname ASC, u.firstname ASC";
                $ajax_enrolled_users = $DB->get_records_sql($ajax_sql_enrolled, array($course->id));
                
                error_log("DEBUG AJAX RESTRICTED: Course ID = " . $course->id);
                error_log("DEBUG AJAX RESTRICTED: Found " . count($ajax_assigned_userids) . " assigned users");
                error_log("DEBUG AJAX RESTRICTED: Found " . count($ajax_enrolled_users) . " enrolled users in THIS course");
                
                $ajax_available_users = array();
                foreach ($ajax_enrolled_users as $user) {
                    if (!in_array($user->id, $ajax_assigned_userids)) {
                        $ajax_available_users[] = array(
                            'id' => $user->id,
                            'name' => $user->lastname . ' ' . $user->firstname,
                            'firstname' => $user->firstname,
                            'lastname' => $user->lastname
                        );
                    }
                }
                
                error_log("DEBUG AJAX RESTRICTED: Final available users count = " . count($ajax_available_users));
                
                usort($ajax_available_users, function($a, $b) {
                    $firstname_cmp = strcasecmp($a['firstname'], $b['firstname']);
                    if ($firstname_cmp === 0) {
                        return strcasecmp($a['lastname'], $b['lastname']);
                    }
                    return $firstname_cmp;
                });
                
                echo json_encode(array('success' => true, 'users' => $ajax_available_users));
                break;
            
            case 'get_statistics':
                $unassigned_records = $performance_handler->get_all_unassigned_records();
                $suggestion_engine = new suggestion_engine($enrolled_users);
                $all_suggestions = $suggestion_engine->generate_suggestions($unassigned_records);
                $suggestion_stats = $suggestion_engine->get_suggestion_statistics($all_suggestions);
                
                echo json_encode(array(
                    'success' => true, 
                    'data' => array(
                        'total_unassigned' => count($unassigned_records),
                        'name_suggestions' => $suggestion_stats['name_based'],
                        'email_suggestions' => $suggestion_stats['email_based'],
                        'available_users' => count($available_users)
                    )
                ));
                break;

            case 'get_suggestions':
                $page = optional_param('page', 0, PARAM_INT);
                $paginated_data = $performance_handler->get_unassigned_records_paginated($page, $per_page, $filter);
                $suggestions = $performance_handler->get_suggestions_for_batch($paginated_data['records']);
                echo json_encode(array('success' => true, 'suggestions' => $suggestions));
                break;
            
            case 'retroactive_preferences':
                if (confirm_sesskey() && has_capability('mod/teamsattendance:manageattendance', context_module::instance($cm->id))) {
                    // Ottiene tutti i record assegnati manualmente
                    $manual_records = $DB->get_records('teamsattendance_data', [
                        'sessionid' => $teamsattendance->id,
                        'manually_assigned' => 1
                    ]);
                    
                    $updated_count = 0;
                    
                    // Ottiene gli utenti disponibili per la generazione dei suggerimenti
                    $context = context_course::instance($course->id);
                    $enrolled_users = get_enrolled_users($context, '', 0, 'u.id, u.firstname, u.lastname, u.email');
                    
                    require_once($CFG->dirroot . '/mod/teamsattendance/classes/suggestion_engine.php');
                    $suggestion_engine = new suggestion_engine($enrolled_users);
                    
                    foreach ($manual_records as $record) {
                        // Salta se la preferenza esiste già
                        $preference_name = 'teamsattendance_suggestion_applied_' . $record->id;
                        if (get_user_preference($preference_name)) {
                            continue;
                        }
                        
                        // Genera un suggerimento per questo record specifico
                        $single_record_array = array($record->id => $record);
                        $suggestions = $suggestion_engine->generate_suggestions($single_record_array);
                        
                        // Verifica se l'assegnazione corrente corrisponde al suggerimento
                        if (isset($suggestions[$record->id])) {
                            $suggestion = $suggestions[$record->id];
                            if ($suggestion['user']->id == $record->userid) {
                                // Questa assegnazione corrisponde al suggerimento - probabilmente è stata applicata
                                set_user_preference($preference_name, $record->userid);
                                $updated_count++;
                            }
                        }
                    }
                    
                    echo json_encode(array(
                        'success' => true, 
                        'message' => "Aggiornate $updated_count preferenze retroattive"
                    ));
                } else {
                    echo json_encode(array('success' => false, 'error' => 'Permesso negato'));
                }
                break;

            default:
                echo json_encode(array('success' => false, 'error' => 'Azione sconosciuta'));
        }
    } catch (Exception $e) {
        echo json_encode(array('success' => false, 'error' => $e->getMessage()));
    }
    
    exit;
}

// ========================= OUTPUT DELLA PAGINA =========================

// Carica CSS e JavaScript
$PAGE->requires->css('/mod/teamsattendance/styles/unassigned_manager.css');
$PAGE->requires->jquery();

echo $OUTPUT->header();

echo $OUTPUT->heading(get_string('manage_unassigned', 'teamsattendance'));

// ========================= CARICAMENTO DATI INIZIALI =========================

// Converte il parametro filtro URL nel formato array atteso da get_unassigned_records_paginated
$initial_filter = array();
if ($filter && $filter !== 'all') {
    switch ($filter) {
        case 'name_suggestions':
            $initial_filter['suggestion_type'] = 'name_based';
            break;
        case 'email_suggestions':
            $initial_filter['suggestion_type'] = 'email_based';
            break;
        case 'without_suggestions':
            $initial_filter['suggestion_type'] = 'none';
            break;
    }
}

// Ottiene i dati iniziali per la pagina 0 con il filtro e la dimensione pagina correnti
$initial_page = 0;
$initial_per_page = 50;

// Carica i dati paginati iniziali con il filtro applicato
$initial_data = $performance_handler->get_unassigned_records_paginated($initial_page, $initial_per_page, $initial_filter);

// Ottiene i suggerimenti per i record della pagina iniziale
$initial_suggestions = $performance_handler->get_suggestions_for_batch($initial_data['records']);

// Prepara i dati dei record iniziali per il template
$initial_records = array();
foreach ($initial_data['records'] as $record) {
    $suggestion = isset($initial_suggestions[$record->id]) ? $initial_suggestions[$record->id] : null;
    $suggestion_type = 'none';
    
    if ($suggestion) {
        $suggestion_type = $suggestion['type'];
    }
    
    $record_data = array(
        'id' => $record->id,
        'teams_user_id' => $record->teams_user_id,
        'attendance_duration' => $record->attendance_duration,
        'has_suggestion' => isset($initial_suggestions[$record->id]),
        'suggestion' => $suggestion,
        'suggestion_type' => $suggestion_type
    );
    $initial_records[] = $record_data;
}

// Prepara il contesto del template con i dati iniziali inclusi
$template_context = (object) array(
    'per_page' => $initial_per_page,
    'cm_id' => $cm->id,
    'total_records' => $perf_stats['total_unassigned'],
    'name_suggestions_count' => $suggestion_stats['name_based'],
    'email_suggestions_count' => $suggestion_stats['email_based'],
    'available_users_count' => count($available_users),
    'current_filter' => $filter,
    'initial_data' => array(
        'records' => $initial_records,
        'pagination' => array(
            'page' => $initial_data['page'],
            'per_page' => $initial_data['per_page'],
            'total_pages' => $initial_data['total_pages'],
            'total_count' => $initial_data['total_count'],
            'has_next' => $initial_data['has_next'],
            'has_previous' => $initial_data['has_previous'],
            'show_all' => $initial_data['show_all']
        )
    )
);

// Renderizza l'interfaccia usando il template
echo render_unassigned_interface($template_context);

// Inizializza JavaScript con la configurazione e la dimensione pagina predefinita
$js_config = array(
    'defaultPageSize' => 50,
    'cmId' => $cm->id,
    'sesskey' => sesskey(),
    'available_users' => $available_users, // AGGIUNTA: Passa utenti disponibili al JavaScript
    'strings' => array(
        'teams_user_id' => get_string('teams_user_id', 'teamsattendance'),
        'attendance_duration' => get_string('attendance_duration', 'teamsattendance'),
        'suggested_match' => get_string('suggested_match', 'teamsattendance'),
        'actions' => get_string('actions', 'teamsattendance'),
        'apply_suggestion' => get_string('apply_suggestion', 'teamsattendance'),
        'assign' => get_string('assign', 'teamsattendance'),
        'select_user' => get_string('select_user', 'teamsattendance'),
        'apply_selected' => get_string('apply_selected', 'teamsattendance'),
        'applying' => get_string('applying', 'teamsattendance'),
        'previous' => get_string('previous', 'teamsattendance'),
        'next' => get_string('next', 'teamsattendance'),
        'page' => get_string('page', 'teamsattendance'),
        'of' => get_string('of', 'teamsattendance'),
        'total_records' => get_string('total_records', 'teamsattendance'),
        'no_records_found' => get_string('no_records_found', 'teamsattendance'),
        'no_suggestion' => get_string('no_suggestion', 'teamsattendance')
    )
);

// Carica il JavaScript modulare
$PAGE->requires->js_call_amd('mod_teamsattendance/unassigned_manager', 'init', [$js_config]);

echo $OUTPUT->footer();
