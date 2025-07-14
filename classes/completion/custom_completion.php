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

namespace mod_teamsattendance\completion;

defined('MOODLE_INTERNAL') || die();

/**
 * Custom completion rules implementation for the Teams Attendance module.
 *
 * @package   mod_teamsattendance
 * @copyright 2025, Invisiblefarm
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class custom_completion extends \core_completion\activity_custom_completion {

    /**
     * Fetches the completion state for a given completion rule.
     * This is where the core logic for checking your custom rule goes.
     *
     * @param string $rule The completion rule name (e.g., 'completionattendance').
     * @return int The completion state (COMPLETION_COMPLETE, COMPLETION_INCOMPLETE).
     */
    public function get_state(string $rule): int {
        global $DB;

        debugging("TEAMS_CLASS_DEBUG: mod_teamsattendance\\completion\\custom_completion->get_state CHIAMATA per regola: '{$rule}', cmid: {$this->cm->id}, utente: {$this->userid}", DEBUG_DEVELOPER);
        error_log("PHP_CLASS_LOG: mod_teamsattendance\\completion\\custom_completion->get_state CHIAMATA per regola: '{$rule}', cmid: {$this->cm->id}, utente: {$this->userid}");

        if ($rule !== 'completionattendance') {
            debugging("TEAMS_CLASS_DEBUG: Regola '{$rule}' non gestita. Restituisco INCOMPLETE come fallback.", DEBUG_DEVELOPER);
            return COMPLETION_INCOMPLETE; // O un altro stato appropriato se la regola non è riconosciuta
        }

        // Recupera l'istanza specifica di teamsattendance
        // $this->cm è un oggetto cm_info, $this->cm->instance è l'ID dell'istanza del modulo (teamsattendance.id)
        $teamsattendance_instance = $DB->get_record('teamsattendance', ['id' => $this->cm->instance]);

        if (!$teamsattendance_instance) {
            debugging("TEAMS_CLASS_DEBUG: Istanza teamsattendance non trovata (id: {$this->cm->instance}). Restituisco INCOMPLETE.", DEBUG_DEVELOPER);
            return COMPLETION_INCOMPLETE;
        }

        // Controlla se la regola "Richiedi presenza" è effettivamente abilitata
        // nelle impostazioni di QUESTA specifica istanza dell'attività.
        // ($teamsattendance_instance->completionattendance dovrebbe essere 1 se abilitata, 0 altrimenti)
        if (empty($teamsattendance_instance->completionattendance)) {
            debugging("TEAMS_CLASS_DEBUG: Regola 'completionattendance' NON abilitata per istanza {$this->cm->instance}. Restituisco INCOMPLETE (o NOT_VISIBLE se la regola non è proprio usata).", DEBUG_DEVELOPER);
            // Se la regola non è spuntata nelle impostazioni, non si applica.
            // Potresti voler restituire un valore che indica che la regola non è attiva,
            // ma per il calcolo complessivo, se una regola obbligatoria non è attiva, l'attività non può essere completata da essa.
            // Tuttavia, activity_custom_completion::get_overall_completion_state cicla solo su get_available_custom_rules().
            // Quindi, se questa regola non è in available_custom_rules (perché completionattendance è 0), questo get_state non dovrebbe essere chiamato per essa.
            // Per sicurezza, restituiamo INCOMPLETE.
            return COMPLETION_INCOMPLETE;
        }

        // Recupera i dati di partecipazione dell'utente
        $attendance_data = $DB->get_record('teamsattendance_data', [
            'sessionid' => $this->cm->instance,
            'userid'    => $this->userid
        ]);

        if (!$attendance_data) {
            debugging("TEAMS_CLASS_DEBUG: Nessun dato 'teamsattendance_data' trovato per utente {$this->userid} in sessione {$this->cm->instance}. Restituisco INCOMPLETE.", DEBUG_DEVELOPER);
            return COMPLETION_INCOMPLETE;
        }

        // $attendance_data->completion_met è 1 se completato, 0 altrimenti (impostato dal tuo cron).
        if (!empty($attendance_data->completion_met) && $attendance_data->completion_met == 1) {
            debugging("TEAMS_CLASS_DEBUG: Dati di partecipazione indicano completion_met = 1. Restituisco COMPLETE.", DEBUG_DEVELOPER);
            return COMPLETION_COMPLETE;
        } else {
            debugging("TEAMS_CLASS_DEBUG: Dati di partecipazione indicano completion_met = 0 o vuoto. Restituisco INCOMPLETE.", DEBUG_DEVELOPER);
            return COMPLETION_INCOMPLETE;
        }
    }

    /**
     * Fetch the list of custom completion rules that this module defines.
     *
     * @return array Array of rule names.
     */
    public static function get_defined_custom_rules(): array {
        // Questo metodo è statico.
        // Non usare $this qui.
        // Non è necessario loggare qui dato che viene chiamato internamente e frequentemente.
        return ['completionattendance'];
    }

    /**
     * Returns an associative array of the descriptions of custom completion rules.
     *
     * @return array Associative array where keys are rule names and values are descriptions.
     */
    public function get_custom_rule_descriptions(): array {
        // Questo metodo NON è statico.
        debugging("TEAMS_CLASS_DEBUG: mod_teamsattendance\\completion\\custom_completion->get_custom_rule_descriptions CHIAMATA", DEBUG_DEVELOPER);
        return [
            'completionattendance' => get_string('completionattendance_desc', 'mod_teamsattendance')
        ];
    }

    /**
     * Returns an array of all completion rules, in the order they should be displayed to users.
     *
     * @return array Array of rule names.
     */
    public function get_sort_order(): array {
        // Questo metodo NON è statico.
        return ['completionattendance'];
    }
}
