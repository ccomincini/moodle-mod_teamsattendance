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

/**
 * The main teamsattendance configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_teamsattendance
 * @copyright  2025 Invisiblefarm srl
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/moodleform_mod.php');

class mod_teamsattendance_mod_form extends moodleform_mod {
    public function definition() {
        $mform = $this->_form;

        global $DB, $COURSE;

        // General settings.
        $mform->addElement('header', 'general', get_string('general', 'form'));
        $mform->addElement('text', 'name', get_string('activityname', 'mod_teamsattendance'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        // Add standard intro elements.
        $this->standard_intro_elements(get_string('description', 'mod_teamsattendance'));

        // Meeting selection.
        $mform->addElement('header', 'meetingdetails', get_string('meetingdetails', 'mod_teamsattendance'));
        
        // Get Teams meetings from the course
        $meetings = $DB->get_records('msteams', ['course' => $COURSE->id], 'name');
        $meetingoptions = [];
        foreach ($meetings as $meeting) {
            $meetingoptions[$meeting->externalurl] = $meeting->name;
        }
        
        // Add meeting selection dropdown
        $mform->addElement('select', 'meetingurl', get_string('meetingurl', 'mod_teamsattendance'), $meetingoptions);
        $mform->setType('meetingurl', PARAM_URL);
        $mform->addRule('meetingurl', null, 'required');
        $mform->addHelpButton('meetingurl', 'meetingurl', 'mod_teamsattendance');

        // Add organizer email field
        $mform->addElement('text', 'organizer_email', get_string('organizer_email', 'mod_teamsattendance'), ['size' => '64']);
        $mform->setType('organizer_email', PARAM_EMAIL);
        $mform->addRule('organizer_email', null, 'required');
        $mform->addHelpButton('organizer_email', 'organizer_email', 'mod_teamsattendance');

        // Add meeting timeframe fields (required)
        $mform->addElement('date_time_selector', 'start_datetime', get_string('meeting_start_time', 'mod_teamsattendance'));
        $mform->addRule('start_datetime', null, 'required');
        $mform->addHelpButton('start_datetime', 'meeting_start_time', 'mod_teamsattendance');
        
        $mform->addElement('date_time_selector', 'end_datetime', get_string('meeting_end_time', 'mod_teamsattendance'));
        $mform->addRule('end_datetime', null, 'required');
        $mform->addHelpButton('end_datetime', 'meeting_end_time', 'mod_teamsattendance');

        // Completion settings.
        $mform->addElement('header', 'completionsettings', get_string('completionsettings', 'mod_teamsattendance'));

        // Expected duration (automatically calculated, read-only, always in minutes)
        $durationgroup = [];
        $durationgroup[] = $mform->createElement('text', 'expected_duration', '', ['size' => 5, 'readonly' => 'readonly']);
        $durationgroup[] = $mform->createElement('static', 'duration_label', '', get_string('minutes', 'mod_teamsattendance'));
        $mform->addGroup($durationgroup, 'duration_group', get_string('expected_duration', 'mod_teamsattendance'), ' ', false);
        $mform->setType('expected_duration', PARAM_INT);
        $mform->addHelpButton('duration_group', 'expected_duration', 'mod_teamsattendance');
        
        // Add JavaScript to automatically calculate duration
        $mform->addElement('html', '<script type="text/javascript">
            function calculateDuration() {
                var startDate = new Date();
                var endDate = new Date();
                
                // Get start datetime components
                var startYear = document.querySelector("select[name=\'start_datetime[year]\']")?.value;
                var startMonth = document.querySelector("select[name=\'start_datetime[month]\']")?.value;
                var startDay = document.querySelector("select[name=\'start_datetime[day]\']")?.value;
                var startHour = document.querySelector("select[name=\'start_datetime[hour]\']")?.value;
                var startMinute = document.querySelector("select[name=\'start_datetime[minute]\']")?.value;
                
                // Get end datetime components
                var endYear = document.querySelector("select[name=\'end_datetime[year]\']")?.value;
                var endMonth = document.querySelector("select[name=\'end_datetime[month]\']")?.value;
                var endDay = document.querySelector("select[name=\'end_datetime[day]\']")?.value;
                var endHour = document.querySelector("select[name=\'end_datetime[hour]\']")?.value;
                var endMinute = document.querySelector("select[name=\'end_datetime[minute]\']")?.value;
                
                if (startYear && startMonth && startDay && startHour && startMinute &&
                    endYear && endMonth && endDay && endHour && endMinute) {
                    
                    startDate.setFullYear(startYear, startMonth - 1, startDay);
                    startDate.setHours(startHour, startMinute, 0, 0);
                    
                    endDate.setFullYear(endYear, endMonth - 1, endDay);
                    endDate.setHours(endHour, endMinute, 0, 0);
                    
                    if (endDate > startDate) {
                        var diffMs = endDate - startDate;
                        var diffMinutes = Math.round(diffMs / (1000 * 60));
                        
                        var durationField = document.querySelector("input[name=\'expected_duration\']");
                        if (durationField) {
                            durationField.value = diffMinutes;
                        }
                    }
                }
            }
            
            // Add event listeners when DOM is ready
            document.addEventListener("DOMContentLoaded", function() {
                var dateTimeSelectors = [
                    "select[name=\'start_datetime[year]\']",
                    "select[name=\'start_datetime[month]\']", 
                    "select[name=\'start_datetime[day]\']",
                    "select[name=\'start_datetime[hour]\']",
                    "select[name=\'start_datetime[minute]\']",
                    "select[name=\'end_datetime[year]\']",
                    "select[name=\'end_datetime[month]\']",
                    "select[name=\'end_datetime[day]\']", 
                    "select[name=\'end_datetime[hour]\']",
                    "select[name=\'end_datetime[minute]\']"
                ];
                
                dateTimeSelectors.forEach(function(selector) {
                    var element = document.querySelector(selector);
                    if (element) {
                        element.addEventListener("change", calculateDuration);
                    }
                });
                
                // Calculate initial duration if values are already set
                setTimeout(calculateDuration, 100);
            });
        </script>');

        $mform->addElement('text', 'required_attendance', get_string('required_attendance', 'mod_teamsattendance'));
        $mform->setDefault('required_attendance', 75);
        $mform->setType('required_attendance', PARAM_INT);
        $mform->addRule('required_attendance', null, 'required');
        $mform->addHelpButton('required_attendance', 'required_attendance', 'mod_teamsattendance');

	$this->add_completion_rules();

        // Standard course module elements.
        $this->standard_coursemodule_elements();

        // Add action buttons.
        $this->add_action_buttons();

    }

    /**
     * Prepare the form data for display
     */
    
    public function set_data($defaultvalues) {
        // Ensure we're working with an object
        if (is_array($defaultvalues)) {
            $defaultvalues = (object) $defaultvalues;
        }
        
        // Calculate duration from start and end times if available
        if (isset($defaultvalues->start_datetime) && isset($defaultvalues->end_datetime) 
            && $defaultvalues->start_datetime > 0 && $defaultvalues->end_datetime > 0) {
            // Calculate duration from start and end times (always in minutes for display)
            $duration_minutes = ($defaultvalues->end_datetime - $defaultvalues->start_datetime) / 60;
            $defaultvalues->expected_duration = round($duration_minutes);
        } elseif (isset($defaultvalues->expected_duration) && $defaultvalues->expected_duration > 0) {
            // Convert from stored seconds to minutes for display
            $seconds = $defaultvalues->expected_duration;
            $defaultvalues->expected_duration = round($seconds / 60);
        } else {
            // Default value if nothing is set
            $defaultvalues->expected_duration = 60; // 60 minutes default
        }
        
        parent::set_data($defaultvalues);
    }

    /**
     * Validate the form data
     */

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Validate required attendance percentage
        if ($data['required_attendance'] < 0 || $data['required_attendance'] > 100) {
            $errors['required_attendance'] = get_string('required_attendance_error', 'mod_teamsattendance');
        }

        // Validate Teams meeting URL
        if (empty($data['meetingurl'])) {
            $errors['meetingurl'] = get_string('meetingurl_required', 'mod_teamsattendance');
        } else {
            // Check if it's a valid Teams meeting URL
            if (!preg_match('/^https:\/\/teams\.microsoft\.com\/l\/meetup-join\/.*$/', $data['meetingurl'])) {
                $errors['meetingurl'] = get_string('invalid_meetingurl', 'mod_teamsattendance');
            }
        }

        // Validate organizer email
        if (empty($data['organizer_email'])) {
            $errors['organizer_email'] = get_string('organizer_email_required', 'mod_teamsattendance');
        } elseif (!validate_email($data['organizer_email'])) {
            $errors['organizer_email'] = get_string('invalid_email', 'mod_teamsattendance');
        }

        // Validate meeting timeframe (now required)
        if (empty($data['start_datetime'])) {
            $errors['start_datetime'] = get_string('meeting_start_time_required', 'mod_teamsattendance');
        }
        
        if (empty($data['end_datetime'])) {
            $errors['end_datetime'] = get_string('meeting_end_time_required', 'mod_teamsattendance');
        }
        
        if (!empty($data['start_datetime']) && !empty($data['end_datetime'])) {
            if ($data['start_datetime'] >= $data['end_datetime']) {
                $errors['end_datetime'] = get_string('end_time_after_start', 'mod_teamsattendance');
            }
            
            // Automatically calculate duration for validation
            $duration_minutes = ($data['end_datetime'] - $data['start_datetime']) / 60;
            if ($duration_minutes <= 0) {
                $errors['end_datetime'] = get_string('invalid_meeting_duration', 'mod_teamsattendance');
            }
        }

        return $errors;
    }

    public function completion_rule_enabled($data) {
        return !empty($data['completionattendance']);
    }

    public function add_completion_rules() {
        $mform = $this->_form;

        $mform->addElement('checkbox', 'completionattendance', '', get_string('completionattendance', 'mod_teamsattendance'));
        $mform->addHelpButton('completionattendance', 'completionattendance', 'mod_teamsattendance');
        $mform->setDefault('completionattendance', 1);

        return array('completionattendance');
    }

    public function get_completion_rules() {
        return ['completionattendance'];
    }
}
