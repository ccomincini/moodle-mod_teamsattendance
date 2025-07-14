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

defined('MOODLE_INTERNAL') || die();

function xmldb_teamsattendance_upgrade($oldversion) {
    global $CFG, $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2025050700) {
        // Rename tables if they exist
        $table = new xmldb_table('teamsattendance_sessions');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'teamsattendance');
        }

        $table = new xmldb_table('teamsattendance_attendance');
        if ($dbman->table_exists($table)) {
            $dbman->rename_table($table, 'teamsattendance_data');
        }

        // Remove redundant fields
        $table = new xmldb_table('teamsattendance');
        $field = new xmldb_field('meetingid');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        $field = new xmldb_field('completion_threshold');
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Update foreign key references
        $table = new xmldb_table('teamsattendance_data');
        if ($dbman->table_exists($table)) {
            // Drop old foreign key
            $key = new xmldb_key('sessionid_fk', XMLDB_KEY_FOREIGN, array('sessionid'), 'teamsattendance_sessions', array('id'));
            if ($dbman->key_exists($table, $key)) {
                $dbman->drop_key($table, $key);
            }

            // Add new foreign key
            $key = new xmldb_key('sessionid_fk', XMLDB_KEY_FOREIGN, array('sessionid'), 'teamsattendance', array('id'));
            $dbman->add_key($table, $key);
        }

        upgrade_mod_savepoint(true, 2025050700, 'teamsattendance');
    }

    if ($oldversion < 2025050800) {
        // Add new fields to teamsattendance_data table
        $table = new xmldb_table('teamsattendance_data');
        
        // Add join_time field
        $field = new xmldb_field('join_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'completion_met');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add leave_time field
        $field = new xmldb_field('leave_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'join_time');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add role field
        $field = new xmldb_field('role', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, 'Attendee', 'leave_time');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2025050800, 'teamsattendance');
    }

    if ($oldversion < 2025050900) {
        // Add completionattendance field to teamsattendance table
        $table = new xmldb_table('teamsattendance');
        $field = new xmldb_field('completionattendance', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'required_attendance');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teamsattendance savepoint reached
        upgrade_mod_savepoint(true, 2025050900, 'teamsattendance');
    }

    if ($oldversion < 2025050901) {
        // Add teams_user_id field to teamsattendance_data table
        $table = new xmldb_table('teamsattendance_data');
        $field = new xmldb_field('teams_user_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, 'userid');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teamsattendance savepoint reached
        upgrade_mod_savepoint(true, 2025050901, 'teamsattendance');
    }

    if ($oldversion < 2025051302) {
        // Add table teamsattendance_reports
        $table = new xmldb_table('teamsattendance_reports');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('data_id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('report_id', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
        $table->add_field('join_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('leave_time', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('attendance_duration', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table teamsattendance_reports
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('dataid_fk', XMLDB_KEY_FOREIGN, array('data_id'), 'teamsattendance_data', array('id'));
        $table->add_key('data_id_report_id', XMLDB_KEY_UNIQUE, array('data_id', 'report_id'));

        // Conditionally launch create table for teamsattendance_reports
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }


        // Teamsattendance savepoint reached
        upgrade_mod_savepoint(true, 2025051302, 'teamsattendance');
    }

    if ($oldversion < 2025051303) {
        // Add manually_assigned field to teamsattendance_data table
        $table = new xmldb_table('teamsattendance_data');
        $field = new xmldb_field('manually_assigned', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'role');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teamsattendance savepoint reached
        upgrade_mod_savepoint(true, 2025051303, 'teamsattendance');
    }

    if ($oldversion < 2025051304) {
        // Fix unique constraint bug: change from sessionid,userid to sessionid,teams_user_id
        $table = new xmldb_table('teamsattendance_data');
        
        // Drop the old unique key (use try-catch in case it doesn't exist)
        $old_key = new xmldb_key('sessionid_userid', XMLDB_KEY_UNIQUE, array('sessionid', 'userid'));
        try {
            if ($dbman->table_exists($table)) {
                $dbman->drop_key($table, $old_key);
            }
        } catch (Exception $e) {
            // Key might not exist, continue with upgrade
            debugging('Could not drop old key sessionid_userid: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        
        // Add the new unique key (use try-catch in case it already exists)
        $new_key = new xmldb_key('sessionid_teams_user_id', XMLDB_KEY_UNIQUE, array('sessionid', 'teams_user_id'));
        try {
            if ($dbman->table_exists($table)) {
                $dbman->add_key($table, $new_key);
            }
        } catch (Exception $e) {
            // Key might already exist, continue with upgrade
            debugging('Could not add new key sessionid_teams_user_id: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        // Teamsattendance savepoint reached
        upgrade_mod_savepoint(true, 2025051304, 'teamsattendance');
    }

    if ($oldversion < 2025051305) {
        // Add start_datetime and end_datetime fields to teamsattendance table
        $table = new xmldb_table('teamsattendance');
        
        // Add start_datetime field
        $field = new xmldb_field('start_datetime', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'status');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Add end_datetime field
        $field = new xmldb_field('end_datetime', XMLDB_TYPE_INTEGER, '10', null, null, null, '0', 'start_datetime');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Teamsattendance savepoint reached
        upgrade_mod_savepoint(true, 2025051305, 'teamsattendance');
    }

    return true;
} 