<?php
namespace mod_teamsattendance\task;

/**
 * Scheduled task to verify completion for Teams Attendance.
 *
 * @package    mod_teamsattendance
 */
class verify_completion extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('verify_completion_task', 'mod_teamsattendance');
    }

    public function execute() {
        global $DB;

        $sessions = $DB->get_records('teamsattendance_sessions', ['status' => 'open']);
        foreach ($sessions as $session) {
            $records = $DB->get_records('teamsattendance', ['sessionid' => $session->id]);
            foreach ($records as $record) {
                if (empty($record->userid)) {
                    continue;
                }
                $completion_met = teamsattendance_check_completion((object)['instance' => $session->id], $record->userid);
                if ($completion_met) {
                    $cm = get_coursemodule_from_instance('teamsattendance', $session->id);
                    if (!$cm) { continue; }
                    $course = $DB->get_record('course', ['id' => $session->course]);
                    if (!$course) { continue; }
                    $completion = new \completion_info($course);
                    $completion->update_state($cm, COMPLETION_COMPLETE, $record->userid);
                }
            }
        }
    }
}