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

try {
    require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';
    include_file('core', 'authentification', 'php');

    if (!isConnect('admin')) {
        throw new Exception('401 Unauthorized');
    }
	
	if (init('action') == 'syncconfBoxio') {
		boxio::syncconfBoxio();
		ajax::success();
	}
	
	if (init('action') == 'checkscenario') {
		$sql = " SELECT * FROM boxio_scenarios where id_legrand='" . init('id') ."'";	
		$result['scenario'] =  DB::Prepare($sql, array(), DB::FETCH_TYPE_ALL); 
		//log::add('boxio', 'debug', print_r($result));
		$boxio = boxio::byLogicalId(init('id'), 'boxio');
        if (is_object($boxio)) {
			$result['versioninst'] = $boxio->getConfiguration('version');
            $device_type = explode('::', $boxio->getConfiguration('applyDevice'));
			$deviceref = $device_type[0];
			$path = dirname(__FILE__) . '/../config/devices';
			if (isset($deviceref) && $deviceref != '') {
				$files = ls($path, $deviceref . '.json', false, array('files', 'quiet'));
				if (count($files) == 1) {
					$content = file_get_contents($path . '/' . $files[0]);
					if (is_json($content)) {
						$deviceConfiguration = json_decode($content, true);
						$result['version'] = $deviceConfiguration[$deviceref]['configuration']['version'];
						$result['update'] = $deviceConfiguration[$deviceref]['configuration']['update'];
						//log::add('boxio', 'debug', print_r($result));
					}
				}
			}
		}
		ajax::success($result);
	}

	
	if (init('action') == 'updateMemory') {
        $mem = boxio::byId(init('id'));
        if (!is_object($mem)) {
            throw new Exception(__('Equipement inconnu verifié l\'id', __FILE__));
        }
		boxio::deleteMemory(init('idtrame'));
		//sleep(5);
		boxio::checkMemory(init('idtrame'));
		
        sleep(10);
        ajax::success($return);
    }
	
    throw new Exception('Aucune methode correspondante');
    /*     * *********Catch exeption*************** */
} catch (Exception $e) {
    ajax::error(displayExeption($e), $e->getCode());
}
?>
