<?php
// This file defines the structure of the data to be backed up for the Teams Meeting Attendance plugin.

class backup_teamsattendance_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the structure of the backup data.
     *
     * @return backup_nested_element The root element of the structure.
     */
    protected function define_structure() {
        // Define the root element describing the teamsattendance instance.
        $teamsattendance = new backup_nested_element('teamsattendance', ['id'], [
            'name', 'intro', 'introformat', 'meetingurl', 'organizer_email',
            'expected_duration', 'required_attendance',
            'status', 'timecreated', 'timemodified'
        ]);

        // Define data sources.
        $teamsattendance->set_source_table('teamsattendance', ['id' => backup::VAR_ACTIVITYID]);

        // Define attendance records
        $attendance = new backup_nested_element('attendance', ['id'], [
            'userid', 'attendance_duration', 'actual_attendance', 'completion_met'
        ]);

        // Add attendance records as child elements
        $teamsattendance->add_child($attendance);
        $attendance->set_source_table('teamsattendance', ['sessionid' => backup::VAR_PARENTID]);

        // Return the root element wrapped into the standard activity structure.
        return $this->prepare_activity_structure($teamsattendance);
    }
}
