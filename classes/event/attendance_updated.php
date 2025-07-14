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

namespace mod_teamsattendance\event;

defined('MOODLE_INTERNAL') || die();

class attendance_updated extends \core\event\base {
    protected function init() {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'teamsattendance_data';
        $this->data['objectid'] = 0;
        $this->data['context'] = \context_module::instance(0);
    }

    public static function get_name() {
        return get_string('event_attendance_updated', 'mod_teamsattendance');
    }

    public function get_description() {
        return "The user with id '$this->userid' updated attendance for the user with id '$this->relateduserid' " .
            "in the Teams Meeting Attendance activity with course module id '$this->contextinstanceid'.";
    }

    public function get_url() {
        return new \moodle_url('/mod/teamsattendance/view.php', array('id' => $this->contextinstanceid));
    }

    public function get_legacy_logdata() {
        return array($this->courseid, 'teamsattendance', 'update attendance',
            $this->get_url(), $this->objectid, $this->contextinstanceid);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     * @return void
     */
    protected function validate_data() {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }

        if (!isset($this->other['meetingurl'])) {
            throw new \coding_exception('The \'meetingurl\' value must be set in other.');
        }

        if (!isset($this->other['attendance_duration'])) {
            throw new \coding_exception('The \'attendance_duration\' value must be set in other.');
        }

        if (!isset($this->other['actual_attendance'])) {
            throw new \coding_exception('The \'actual_attendance\' value must be set in other.');
        }
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_legacy_eventname() {
        return 'teamsattendance_attendance_updated';
    }
} 