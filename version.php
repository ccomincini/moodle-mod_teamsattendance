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
 * Metadati di versione per il plugin mod_teamsattendance.
 *
 * @package   mod_teamsattendance
 * @copyright 2025, Carlo Comincini <carlo@comincini.it>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version = 2025072402; // Versione corrente del plugin (Data: YYYYMMDDXX).
$plugin->requires = 2022112800; // Richiede questa versione di Moodle.
$plugin->supported = [39, 40];   // Disponibile da Moodle 3.9.0 o successivo.
$plugin->component = 'mod_teamsattendance'; // Nome completo del plugin (usato per diagnostica).
$plugin->maturity = MATURITY_STABLE; // Livello di maturità di questa versione.
$plugin->release = 'v3.0.0'; // Nome versione leggibile.

// Registra l'icona del modulo
$plugin->icon = 'pix/icon.svg';

$plugin->dependencies = [
	'auth_oidc' => 2024100710, // Richiede il plugin di autenticazione OIDC.
	'mod_msteams' => 2022012000
];
