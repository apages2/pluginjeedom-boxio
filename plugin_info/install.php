<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

require_once dirname(__FILE__) . '/../../../core/php/core.inc.php';

function boxio_install() {
	log::add('boxio', 'debug', 'Installation du Plugin Boxio');
	exec('sudo chmod 777 '.dirname(__FILE__) . '/install.sql');
	$sql = file_get_contents(dirname(__FILE__) . '/install.sql');
    DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
}

function boxio_update() {
	log::add('boxio', 'debug', 'Update du Plugin Boxio');
	try {
		exec('sudo chmod 777 '.dirname(__FILE__) . '/install.sql');
		$sql = file_get_contents(dirname(__FILE__) . '/install.sql');
		DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
	} catch (Exception $e) {

	}
}

function boxio_remove() {
	log::add('boxio', 'debug', 'Suppression du Plugin Boxio');
	$sql = "DROP TABLE IF EXISTS boxio_scenarios;";
    DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
}
?>
