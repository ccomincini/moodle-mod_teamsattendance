<?php
// This file is part of the Teams Meeting Attendance plugin for Moodle - http://moodle.org/
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

namespace mod_teamsattendance\task;

defined('MOODLE_INTERNAL') || die();

class fetch_attendance extends \core\task\scheduled_task {
    public function get_name() {
        return get_string('task_fetch_attendance', 'mod_teamsattendance');
    }

    public function execute() {
        global $DB, $CFG;

        // Include module files
        require_once($CFG->dirroot . '/mod/teamsattendance/lib.php');

        // Log the start of the task
        mtrace("Starting attendance fetch task...");

        // Get all active sessions that need attendance fetching
        $sessions = $DB->get_records('teamsattendance', [
            'status' => 'open' // Only fetch for open registers
        ]);

        // Check if there are any sessions to process
        if (empty($sessions)) {
            mtrace("No active sessions found for attendance fetching.");
            return;
        }

        foreach ($sessions as $session) {
            try {
                // Get course module using direct SQL to avoid deprecation notices
                $cm = $DB->get_record_sql(
                    "SELECT cm.* 
                     FROM {course_modules} cm 
                     WHERE cm.instance = :instance 
                     AND cm.module = (SELECT id FROM {modules} WHERE name = 'teamsattendance')",
                    ['instance' => $session->id]
                );

                if (!$cm) {
                    mtrace("Could not find course module for session {$session->id}");
                    continue;
                }

                // Get context
                $context = \context_module::instance($cm->id);
                if (!$context) {
                    mtrace("Could not find context for session {$session->id}");
                    continue;
                }

                // Check if we have permission to fetch attendance
                if (!has_capability('mod/teamsattendance:manageattendance', $context)) {
                    mtrace("No permission to manage attendance for session {$session->id}");
                    continue;
                }

                // Log attempt
                mtrace("Fetching attendance for session {$session->id}");

                // Call the fetch_attendance function
                $result = teamsattendance_fetch_attendance($cm->id);
                
                if ($result) {
                    mtrace("Successfully fetched attendance for session {$session->id}");
                } else {
                    mtrace("Failed to fetch attendance for session {$session->id}");
                }

            } catch (\Exception $e) {
                // Log error but continue with next session
                mtrace("Error fetching attendance for session {$session->id}: " . $e->getMessage());
            }
        }
    }
} 