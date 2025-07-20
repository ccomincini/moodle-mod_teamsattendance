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
 * Version metadata for the mod_teamsattendance plugin.
 *
 * @package   mod_teamsattendance
 * @copyright 2025, Carlo Comincini <carlo@comincini.it>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025072001; // The current plugin version (Date: YYYYMMDDXX).
$plugin->requires = 2022112800; // Requires this Moodle version.
$plugin->supported = [39, 40];   // Available as of Moodle 3.9.0 or later.
$plugin->component = 'mod_teamsattendance'; // Full name of the plugin (used for diagnostics).
$plugin->maturity = MATURITY_STABLE; // This version's maturity level.
$plugin->release = 'v2.1.4'; // Human-readable version name.

// Register the module's icon
$plugin->icon = 'pix/icon.svg';

$plugin->dependencies = [
	'auth_oidc' => 2024100710, // Requires the OIDC authentication plugin.
	'mod_msteams' => 2022012000
];
