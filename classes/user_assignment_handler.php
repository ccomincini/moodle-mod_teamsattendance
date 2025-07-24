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
 * User assignment handler for Teams attendance
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Handles user assignment operations and preference management
 */
class user_assignment_handler {
    
    /** @var object Course module object */
    private $cm;
    
    /** @var object Teams attendance instance */
    private $teamsattendance;
    
    /** @var object Course object */
    private $course;
    
    /**
     * Constructor
     *
     * @param object $cm Course module
     * @param object $teamsattendance Teams attendance instance
     * @param object $course Course object
     */
    public function __construct($cm, $teamsattendance, $course) {
        $this->cm = $cm;
        $this->teamsattendance = $teamsattendance;
        $this->course = $course;
    }
    
    /**
     * Get available users for assignment
     *
     * @return array Array of available users
     */
    public function get_available_users() {
        global $DB, $CFG;
        
        $context = context_course::instance($this->course->id);
        $enrolled_users = get_enrolled_users($context);
        
        // Get already assigned user IDs for this session
        $assigned_userids = $DB->get_fieldset_select(
            'teamsattendance_data',
            'userid',
            'sessionid = ? AND userid != ?',
            array($this->teamsattendance->id, $CFG->siteguest)
        );
        
        // Filter out already assigned users
        $available_users = array();
        foreach ($enrolled_users as $user) {
            if (!in_array($user->id, $assigned_userids)) {
                $available_users[$user->id] = $user;
            }
        }
        
        return $available_users;
    }
    
    /**
     * Apply bulk suggestions
     *
     * @param array $suggestions Array of record_id => user_id mappings
     * @return array Result with count and details
     */
    public function apply_bulk_suggestions($suggestions) {
        global $DB;
        
        $applied_count = 0;
        $errors = array();
        $applied_details = array();
        
        foreach ($suggestions as $recordid => $suggested_userid) {
            if ($recordid && $suggested_userid) {
                try {
                    $record = $DB->get_record('teamsattendance_data', array('id' => $recordid), '*', MUST_EXIST);
                    
                    // Verify the user is still available
                    $available_users = $this->get_available_users();
                    if (!isset($available_users[$suggested_userid])) {
                        $errors[] = "User ID {$suggested_userid} is no longer available for assignment";
                        continue;
                    }
                    
                    $old_userid = $record->userid;
                    $record->userid = $suggested_userid;
                    $record->manually_assigned = 1;
                    
                    if ($DB->update_record('teamsattendance_data', $record)) {
                        $this->mark_suggestion_as_applied($recordid, $suggested_userid);
                        $applied_count++;
                        
                        $applied_details[] = array(
                            'record_id' => $recordid,
                            'teams_user_id' => $record->teams_user_id,
                            'old_user_id' => $old_userid,
                            'new_user_id' => $suggested_userid,
                            'user' => $available_users[$suggested_userid]
                        );
                    } else {
                        $errors[] = "Failed to update database for record ID {$recordid}";
                    }
                } catch (Exception $e) {
                    $errors[] = "Error processing record ID {$recordid}: " . $e->getMessage();
                }
            }
        }
        
        return array(
            'applied_count' => $applied_count,
            'errors' => $errors,
            'applied_details' => $applied_details
        );
    }
    
    /**
     * Assign single user to record
     *
     * @param int $recordid Record ID
     * @param int $userid User ID
     * @return array Result with success status and details
     */
    public function assign_single_user($recordid, $userid) {
        global $DB;
        
        try {
            $record = $DB->get_record('teamsattendance_data', array('id' => $recordid), '*', MUST_EXIST);
            
            // Verify the user is available
            $available_users = $this->get_available_users();
            if (!isset($available_users[$userid])) {
                return array(
                    'success' => false,
                    'error' => 'User is no longer available for assignment'
                );
            }
            
            $old_userid = $record->userid;
            $record->userid = $userid;
            $record->manually_assigned = 1;
            
            if ($DB->update_record('teamsattendance_data', $record)) {
                $this->mark_suggestion_as_applied($recordid, $userid);
                
                return array(
                    'success' => true,
                    'record_id' => $recordid,
                    'teams_user_id' => $record->teams_user_id,
                    'old_user_id' => $old_userid,
                    'new_user_id' => $userid,
                    'user' => $available_users[$userid]
                );
            } else {
                return array(
                    'success' => false,
                    'error' => 'Failed to update database'
                );
            }
        } catch (Exception $e) {
            return array(
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            );
        }
    }
    
    /**
     * Get unassigned records for this session
     *
     * @return array Array of unassigned records
     */
    public function get_unassigned_records() {
        global $DB, $CFG;
        
        return $DB->get_records_sql("
            SELECT tad.*, u.firstname, u.lastname, u.email
            FROM {teamsattendance_data} tad
            LEFT JOIN {user} u ON u.id = tad.userid
            WHERE tad.sessionid = ? AND tad.userid = ?
            ORDER BY tad.teams_user_id
        ", array($this->teamsattendance->id, $CFG->siteguest));
    }
    
    /**
     * Mark suggestion as applied
     *
     * @param int $record_id Record ID
     * @param int $user_id User ID
     */
    public function mark_suggestion_as_applied($record_id, $user_id) {
        $preference_name = 'teamsattendance_suggestion_applied_' . $record_id;
        set_user_preference($preference_name, $user_id);
    }
    
    /**
     * Check if suggestion was applied for a record
     *
     * @param int $record_id Record ID
     * @return bool True if suggestion was applied
     */
    public function was_suggestion_applied($record_id) {
        $preference_name = 'teamsattendance_suggestion_applied_' . $record_id;
        $applied_user_id = get_user_preferences($preference_name, null);
        
        return !is_null($applied_user_id);
    }
    
    /**
     * Get filtered and sorted users list for dropdown
     *
     * @param array $available_users Available users array
     * @return array Formatted user list for dropdown
     */
    public function get_filtered_users_list($available_users) {
        $userlist = array();
        $sortable_users = array();
        
        // Create array with sortable full names
        foreach ($available_users as $user) {
            $fullname = fullname($user);
            $display_name = $fullname . ' (' . $user->email . ')';
            $sortable_users[] = array(
                'id' => $user->id,
                'fullname' => $fullname,
                'display_name' => $display_name,
                'lastname' => $user->lastname,
                'firstname' => $user->firstname
            );
        }
        
        // Sort by lastname first, then firstname (Italian standard)
        usort($sortable_users, function($a, $b) {
            $lastname_comparison = strcasecmp($a['lastname'], $b['lastname']);
            if ($lastname_comparison === 0) {
                return strcasecmp($a['firstname'], $b['firstname']);
            }
            return $lastname_comparison;
        });
        
        // Build the final array for the dropdown
        foreach ($sortable_users as $user_data) {
            $userlist[$user_data['id']] = $user_data['display_name'];
        }
        
        return $userlist;
    }
    
    /**
     * Get assignment statistics
     *
     * @return array Assignment statistics
     */
    public function get_assignment_statistics() {
        global $DB, $CFG;
        
        $stats = array();
        
        // Total records for this session
        $stats['total_records'] = $DB->count_records('teamsattendance_data', array('sessionid' => $this->teamsattendance->id));
        
        // Unassigned records (guest user)
        $stats['unassigned_records'] = $DB->count_records('teamsattendance_data', array(
            'sessionid' => $this->teamsattendance->id,
            'userid' => $CFG->siteguest
        ));
        
        // Manually assigned records
        $stats['manually_assigned'] = $DB->count_records('teamsattendance_data', array(
            'sessionid' => $this->teamsattendance->id,
            'manually_assigned' => 1
        ));
        
        // Auto-assigned records
        $stats['auto_assigned'] = $DB->count_records_sql("
            SELECT COUNT(*)
            FROM {teamsattendance_data}
            WHERE sessionid = ? AND userid != ? AND (manually_assigned = 0 OR manually_assigned IS NULL)
        ", array($this->teamsattendance->id, $CFG->siteguest));
        
        // Available users for assignment
        $available_users = $this->get_available_users();
        $stats['available_users'] = count($available_users);
        
        // Calculate percentages
        if ($stats['total_records'] > 0) {
            $stats['assignment_rate'] = (($stats['total_records'] - $stats['unassigned_records']) / $stats['total_records']) * 100;
            $stats['manual_assignment_rate'] = ($stats['manually_assigned'] / $stats['total_records']) * 100;
            $stats['auto_assignment_rate'] = ($stats['auto_assigned'] / $stats['total_records']) * 100;
        } else {
            $stats['assignment_rate'] = 0;
            $stats['manual_assignment_rate'] = 0;
            $stats['auto_assignment_rate'] = 0;
        }
        
        return $stats;
    }
}
