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
 * Italian strings for teamsattendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin information
$string['pluginname'] = 'Presenze riunioni Teams';
$string['pluginadministration'] = 'Amministrazione presenze riunioni Teams';
$string['modulename'] = 'Presenze riunioni Teams';
$string['modulenameplural'] = 'Presenze riunioni Teams';

// Settings strings
$string['settingsheader'] = 'Impostazioni presenze Teams';
$string['settingsheader_desc'] = 'Configura le impostazioni API Microsoft Teams per il tracciamento delle presenze';
$string['tenantid'] = 'ID tenant';
$string['tenantid_desc'] = 'ID tenant Microsoft Azure per autenticazione API';
$string['apiendpoint'] = 'Endpoint API';
$string['apiendpoint_desc'] = 'URL endpoint API Microsoft Graph';
$string['apiversion'] = 'Versione API';
$string['apiversion_desc'] = 'Versione API Microsoft Graph da utilizzare';

// Basic strings
$string['description'] = 'Descrizione';
$string['activityname'] = 'Nome attività';
$string['meetingdetails'] = 'Dettagli riunione';
$string['completionsettings'] = 'Impostazioni completamento';
$string['minutes'] = 'minuti';

// Meeting configuration
$string['meetingurl'] = 'URL riunione Teams';
$string['meetingurl_help'] = 'Seleziona la riunione Teams per cui tracciare le presenze.';
$string['organizer_email'] = 'Email organizzatore riunione';
$string['organizer_email_help'] = 'Indirizzo email della persona che ha organizzato la riunione Teams. Necessario per recuperare i report delle presenze.';
$string['meeting_start_time'] = 'Ora inizio riunione';
$string['meeting_start_time_help'] = 'L\'ora di inizio della sessione di riunione per filtrare i report delle presenze.';
$string['meeting_end_time'] = 'Ora fine riunione';
$string['meeting_end_time_help'] = 'L\'ora di fine della sessione di riunione per filtrare i report delle presenze.';
$string['expected_duration'] = 'Durata prevista';
$string['expected_duration_help'] = 'La durata prevista della riunione in minuti. Viene calcolata automaticamente dagli orari di inizio e fine.';
$string['required_attendance'] = 'Presenza richiesta (%)';
$string['required_attendance_help'] = 'La percentuale minima di presenza richiesta per il completamento. Gli studenti devono partecipare almeno per questa percentuale della durata prevista della riunione.';

// Completion
$string['completionattendance'] = 'Lo studente deve soddisfare il requisito di presenza';
$string['completionattendance_help'] = 'Se abilitato, gli studenti devono raggiungere la percentuale minima di presenza per completare questa attività.';
$string['completionattendance_desc'] = 'Lo studente deve raggiungere la percentuale di presenza richiesta';

// View page
$string['attendance_register'] = 'Registro presenze';
$string['close_register'] = 'Chiudi registro';
$string['reopen_register'] = 'Riapri registro';
$string['fetch_attendance'] = 'Recupera dati presenze';
$string['fetch_warning'] = 'Questo recupererà gli ultimi dati di presenza da Microsoft Teams. Il processo potrebbe richiedere alcuni momenti.';
$string['last_fetch_time'] = 'Ultimo aggiornamento: {$a}';
$string['exporttocsv'] = 'Esporta in CSV';
$string['exporttoxlsx'] = 'Esporta in Excel';

// Table headers
$string['cognome'] = 'Cognome';
$string['nome'] = 'Nome';
$string['idnumber'] = 'Codice identificativo';
$string['role'] = 'Ruolo';
$string['tempo_totale'] = 'Tempo totale';
$string['attendance_percentage'] = 'Presenza %';
$string['soglia_raggiunta'] = 'Soglia raggiunta';
$string['assignment_type'] = 'Tipo assegnazione';
$string['teams_user'] = 'Utente Teams';
$string['teams_user_id'] = 'ID utente Teams';
$string['attendance_duration'] = 'Durata presenza';
$string['suggested_match'] = 'Corrispondenza suggerita';
$string['assign_user'] = 'Assegna utente';
$string['actions'] = 'Azioni';

// Assignment types
$string['manual'] = 'Manuale';
$string['automatic'] = 'Automatico';
$string['manually_assigned_tooltip'] = 'Questo utente è stato assegnato manualmente da un amministratore';
$string['automatically_assigned_tooltip'] = 'Questo utente è stato associato automaticamente basandosi sull\'indirizzo email';

// Unassigned management
$string['unassigned_records'] = 'Record non assegnati';
$string['manage_unassigned'] = 'Gestisci record non assegnati';
$string['manage_manual_assignments'] = 'Gestisci assegnazioni manuali';
$string['no_unassigned'] = 'Tutti i record di presenza sono stati assegnati agli utenti.';
$string['unassigned_users_alert'] = 'Ci sono {$a} record di presenza non assegnati che necessitano revisione manuale.';

// Performance strings - CORRECTED
$string['total_records'] = 'Record totali';
$string['performance_level'] = 'Livello performance';
$string['recommended_page_size'] = 'Dimensione pagina consigliata';
$string['available_users'] = 'Utenti disponibili';
$string['for_assignment'] = 'per assegnazione';
$string['estimated_time'] = 'Tempo stimato';
$string['for_suggestions'] = 'per suggerimenti';
$string['filter_by'] = 'Filtra per';
$string['filter_all'] = 'Tutti i record';
$string['all_records'] = 'Tutti i record';
$string['filter_name_suggestions'] = 'Suggerimenti desunti dal nome';
$string['filter_email_suggestions'] = 'Suggerimenti desunti dall\'indirizzo email';
$string['with_suggestions'] = 'Con suggerimenti';
$string['without_suggestions'] = 'Senza suggerimenti';
$string['filter_long_duration'] = 'Sessioni lunga durata';
$string['records_per_page'] = 'Record per pagina';
$string['advanced_users'] = 'Solo utenti avanzati';
$string['refresh'] = 'Aggiorna';
$string['apply_selected'] = 'Applica selezionati';
$string['bulk_assignment_progress'] = 'Progresso assegnazione massa';
$string['loading_initial_data'] = 'Caricamento dati iniziali';
$string['loading'] = 'Caricamento';
$string['applying'] = 'Applicazione';
$string['page'] = 'Pagina';
$string['of'] = 'di';
$string['previous'] = 'Precedente';
$string['next'] = 'Successivo';
$string['no_records_found'] = 'Nessun record trovato';

// Performance levels
$string['performance_excellent'] = 'Performance eccellente prevista';
$string['performance_good'] = 'Buona performance prevista';
$string['performance_moderate'] = 'Performance moderata - considera l\'uso di filtri';
$string['performance_challenging'] = 'Dataset grande - usa paginazione e filtri per migliori performance';

// Suggestions system - CORRECTED
$string['suggestions_found'] = '{$a} suggerimenti automatici di corrispondenza trovati basati sui nomi';
$string['suggestions_summary'] = 'Trovati {$a->total} suggerimenti totali: {$a->name_matches} basati su somiglianza dei nomi, {$a->email_matches} basati su pattern email';
$string['name_match_suggestion'] = 'Corrispondenza suggerita per omonimia';
$string['email_match_suggestion'] = 'Corrispondenza suggerita dedotta da indirizzo email';
$string['no_suggestion'] = 'Nessun suggerimento automatico';
$string['apply_suggestion'] = 'Applica questo suggerimento';
$string['apply_selected_suggestions'] = 'Applica suggerimenti selezionati';
$string['bulk_assignments_applied'] = '{$a} assegnazioni sono state applicate con successo.';
$string['no_assignments_applied'] = 'Nessuna assegnazione è stata applicata.';

// Color legend - CORRECTED
$string['color_legend'] = 'Legenda colori';
$string['name_based_matches'] = 'Suggerimenti desunti dal nome';
$string['email_based_matches'] = 'Suggerimenti desunti dall\'indirizzo email';
$string['suggested_matches'] = 'Corrispondenze suggerite';
$string['no_matches'] = 'Nessuna corrispondenza automatica';
$string['name_suggestions_count'] = 'Suggerimenti desunti dal nome';
$string['email_suggestions_count'] = 'Suggerimenti desunti dall\'indirizzo email';

// User assignment
$string['select_user'] = 'Seleziona utente...';
$string['assign'] = 'Assegna';
$string['user_assigned'] = 'L\'utente è stato assegnato con successo.';
$string['user_assignment_failed'] = 'Fallimento nell\'assegnazione dell\'utente. Riprova.';

// JavaScript messages
$string['select_user_first'] = 'Per favore seleziona prima un utente.';
$string['confirm_assignment'] = 'Sei sicuro di voler assegnare questo record a {user}?';
$string['select_suggestions_first'] = 'Per favore seleziona almeno un suggerimento da applicare.';
$string['confirm_bulk_assignment'] = 'Sei sicuro di voler applicare {count} suggerimenti selezionati?';

// Error messages
$string['meetingurl_required'] = 'L\'URL della riunione Teams è richiesto.';
$string['invalid_meetingurl'] = 'Per favore inserisci un URL valido per la riunione Teams.';
$string['organizer_email_required'] = 'L\'email dell\'organizzatore della riunione è richiesta.';
$string['invalid_email'] = 'Per favore inserisci un indirizzo email valido.';
$string['meeting_start_time_required'] = 'L\'ora di inizio della riunione è richiesta.';
$string['meeting_end_time_required'] = 'L\'ora di fine della riunione è richiesta.';
$string['end_time_after_start'] = 'L\'ora di fine deve essere successiva all\'ora di inizio.';
$string['invalid_meeting_duration'] = 'Durata della riunione non valida.';
$string['required_attendance_error'] = 'La presenza richiesta deve essere tra 0 e 100 percento.';

// Help strings
$string['required_attendance_help'] = 'Inserisci la percentuale minima di presenza richiesta agli studenti per completare questa attività. Il valore deve essere tra 0 e 100.';
$string['expected_duration_help'] = 'Questo campo mostra la durata prevista della riunione in minuti, calcolata automaticamente dagli orari di inizio e fine impostati sopra.';
$string['meetingurl_help'] = 'Seleziona la riunione Teams dalle riunioni disponibili in questo corso. Se non sono disponibili riunioni, devi prima creare un\'attività riunione Teams.';
$string['organizer_email_help'] = 'Inserisci l\'indirizzo email della persona che ha organizzato la riunione Teams. Questa email viene utilizzata per autenticarsi con l\'API Microsoft Teams e recuperare i report delle presenze.';
$string['meeting_start_time_help'] = 'Imposta l\'ora di inizio per questa sessione di riunione. Verrà utilizzata per filtrare i report delle presenze per includere solo i partecipanti in questo intervallo di tempo.';
$string['meeting_end_time_help'] = 'Imposta l\'ora di fine per questa sessione di riunione. Verrà utilizzata per filtrare i report delle presenze per includere solo i partecipanti in questo intervallo di tempo.';
$string['completionattendance_help'] = 'Se abilitato, gli studenti dovranno raggiungere la percentuale minima di presenza specificata sopra per contrassegnare questa attività come completata.';

// API and system messages
$string['missingapicredentials'] = 'Le credenziali API Microsoft Graph sono mancanti. Per favore configura il plugin auth_oidc.';
$string['missingtenantid'] = 'L\'ID tenant è mancante. Per favore configuralo nelle impostazioni del plugin.';
$string['invalidaccesstoken'] = 'Fallimento nell\'ottenere un token di accesso valido dall\'API Microsoft Graph.';
$string['sessionnotfound'] = 'Sessione di presenza Teams non trovata.';
$string['invalidattendanceformat'] = 'Formato dati di presenza non valido ricevuto dall\'API Microsoft Teams.';
$string['attendancefetchfailed'] = 'Fallimento nel recuperare i dati di presenza da Microsoft Teams.';
$string['fetch_attendance_success'] = 'I dati di presenza sono stati recuperati con successo da Microsoft Teams.';

// Completion descriptions
$string['completionattendance_desc'] = 'Lo studente deve raggiungere la percentuale di presenza richiesta';

// Capabilities
$string['teamsattendance:view'] = 'Visualizza report presenze Teams';
$string['teamsattendance:manageattendance'] = 'Gestisci dati presenze Teams';
$string['teamsattendance:addinstance'] = 'Aggiungi attività presenze Teams';

// Reset automatic assignments
$string['automatic_assignments_info'] = '{$a} record associati sulla base di suggerimenti.';
$string['reset_automatic_assignments'] = 'Resetta tutte le assegnazioni effettuate sulla base di suggerimenti';
$string['confirm_reset_automatic'] = 'Sei sicuro di voler resettare tutte le associazioni basate su suggerimenti? Tutte le associazioni resettate dovranno essere nuovamente effettuate manualmente.';
$string['automatic_assignments_reset'] = '{$a} assegnazioni automatiche resettate.';

$string['manual_assignments_info'] = '{$a} assegnazioni manuali trovate.';
$string['reset_manual_assignments'] = 'Reimposta assegnazioni manuali';
$string['confirm_reset_manual_assignments'] = 'Sei sicuro di voler reimpostare tutte le assegnazioni manuali?';

$string['potential_suggestions_info'] = 'Ci sono {$a} associazioni manuali che corrispondono ai suggerimenti automatici attuali';
$string['reset_suggestion_assignments'] = 'Reimposta associazioni da suggerimenti';
$string['confirm_reset_suggestions'] = 'Reimpostare le associazioni che corrispondono ai suggerimenti automatici?';
$string['suggestion_assignments_reset'] = 'Reimpostati {$a} associazioni da suggerimenti';

//Privacy
$string['privacy:metadata'] = 'Il plugin presenze riunioni Teams memorizza dati di presenza recuperati da Microsoft Teams.';
$string['privacy:metadata:teamsattendance_data'] = 'Record di presenza per riunioni Teams';
$string['privacy:metadata:teamsattendance_data:userid'] = 'L\'ID dell\'utente';
$string['privacy:metadata:teamsattendance_data:attendance_duration'] = 'Durata della presenza nella riunione';
$string['privacy:metadata:teamsattendance_data:actual_attendance'] = 'Percentuale di presenza effettiva';
$string['privacy:metadata:teamsattendance_data:completion_met'] = 'Se i criteri di completamento sono stati soddisfatti';