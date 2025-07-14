<?php
// This file defines the backup task for the Teams Meeting Attendance plugin.

require_once($CFG->dirroot . '/mod/teamsattendance/backup/moodle2/backup_teamsattendance_stepslib.php');

class backup_teamsattendance_activity_task extends backup_activity_task {

    /**
     * Define the settings for the backup task.
     */
    protected function define_my_settings() {
        // No specific settings for this activity.
    }

    /**
     * Define the steps for the backup task.
     */
    protected function define_my_steps() {
        $this->add_step(new backup_teamsattendance_activity_structure_step('teamsattendance_structure', 'teamsattendance.xml'));
    }

    /**
     * Encode content links for transport.
     *
     * @param string $content The content to encode.
     * @return string The encoded content.
     */
    public static function encode_content_links($content) {
        return $content;
    }
}
