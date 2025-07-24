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

// Only add settings if we're in the admin tree and this is a full tree build
if ($ADMIN->fulltree) {
    // Create a unique settings page name that won't conflict
    $settingspage = new admin_settingpage(
        'mod_teamsattendance_settings',  // Unique page name
        get_string('pluginname', 'mod_teamsattendance'),
        'moodle/site:config'
    );

    // Add the settings page to the module settings section
    if ($settingspage) {
        $ADMIN->add('modsettings', $settingspage);

        // Add header for Microsoft Teams API settings
        $settingspage->add(new admin_setting_heading(
            'mod_teamsattendance/settingsheader',
            get_string('settingsheader', 'mod_teamsattendance'),
            get_string('settingsheader_desc', 'mod_teamsattendance')
        ));

        // Tenant ID setting
        $settingspage->add(new admin_setting_configtext(
            'mod_teamsattendance/tenantid',
            get_string('tenantid', 'mod_teamsattendance'),
            get_string('tenantid_desc', 'mod_teamsattendance'),
            '',
            PARAM_TEXT,
            50
        ));

        // API Endpoint setting
        $settingspage->add(new admin_setting_configtext(
            'mod_teamsattendance/apiendpoint',
            get_string('apiendpoint', 'mod_teamsattendance'),
            get_string('apiendpoint_desc', 'mod_teamsattendance'),
            'https://graph.microsoft.com/v1.0',
            PARAM_URL,
            50
        ));

        // API Version setting
        $settingspage->add(new admin_setting_configtext(
            'mod_teamsattendance/apiversion',
            get_string('apiversion', 'mod_teamsattendance'),
            get_string('apiversion_desc', 'mod_teamsattendance'),
            'v1.0',
            PARAM_TEXT,
            20
        ));
    }
}
