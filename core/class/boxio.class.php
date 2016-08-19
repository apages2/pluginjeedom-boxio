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

/* * ***************************Includes********************************* */
require_once dirname(__FILE__) . '/../../../../core/php/core.inc.php';

class boxio extends eqLogic {
    /*     * *************************Attributs****************************** */
	
	
    /*     * ***********************Methode static*************************** */


	public static function cronDaily() {
		if (config::byKey('jeeNetwork::mode') == 'master') {
			if (config::byKey('auto_updateConf', 'boxio') == 1) {
				try {
					boxio::syncconfBoxio();
				} catch (Exception $e) {
				}
			}
		}
	}

	public static function dependancy_info() {
		$return = array();
		$return['log'] = 'boxio.update';
		$return['progress_file'] = '/tmp/dependancy_boxio_in_progress';
		if (exec('sudo dpkg --get-selections | grep python-serial | grep install | wc -l') != 0) {
			$return['state'] = 'ok';
		} else {
			$return['state'] = 'nok';
		}
		return $return;
	}

	public static function dependancy_install() {
		log::remove('boxio.update');
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../ressources/install.sh';
		$cmd .= ' >> ' . log::getPathToLog('boxio.update') . ' 2>&1 &';
		exec($cmd);
	}

	public static function deamon_info() {
		$return = array();
		$return['log'] = 'boxiocmd';
		$return['state'] = 'nok';
		$pid_file = '/tmp/boxio.pid';
		if (file_exists($pid_file)) {
			if (posix_getsid(trim(file_get_contents($pid_file)))) {
				$return['state'] = 'ok';
			} else {
				shell_exec('sudo rm -rf ' . $pid_file . ' 2>&1 > /dev/null;rm -rf ' . $pid_file . ' 2>&1 > /dev/null;');
			}
		}
		$return['launchable'] = 'ok';
		$port = config::byKey('port', 'boxio');
		if ($port != 'auto') {
			$port = jeedom::getUsbMapping($port);
			if (@!file_exists($port)) {
				$return['launchable'] = 'nok';
				$return['launchable_message'] = __('Le port n\'est pas configuré', __FILE__);
			}
			exec('sudo chmod 777 ' . $port . ' > /dev/null 2>&1');
		}
		return $return;
	}

	public static function deamon_start($_debug = false) {
		self::deamon_stop();
		$deamon_info = self::deamon_info();
		if ($deamon_info['launchable'] != 'ok') {
			throw new Exception(__('Veuillez vérifier la configuration', __FILE__));
		}
		log::remove('boxiocmd');
		$port = config::byKey('port', 'boxio');
		if ($port != 'auto') {
			$port = jeedom::getUsbMapping($port);
		}

		$boxio_path = realpath(dirname(__FILE__) . '/../../ressources/boxiocmd');

		if (file_exists('/tmp/config_boxio.xml')) {
			unlink('/tmp/config_boxio.xml');
		}
		$enable_logging = (config::byKey('enableLogging', 'boxio', 0) == 1) ? 'yes' : 'no';
		if (file_exists(log::getPathToLog('boxiocmd') . '.message')) {
			unlink(log::getPathToLog('boxiocmd') . '.message');
		}
		if (!file_exists(log::getPathToLog('boxiocmd') . '.message')) {
			touch(log::getPathToLog('boxiocmd') . '.message');
		}
		$replace_config = array(
            '#device#' => $port,
            '#serial_rate#' => config::byKey('serial_rate', 'boxio', 115200),
            '#socketport#' => config::byKey('socketport', 'boxio', 55002),
            '#log_path#' => log::getPathToLog('boxiocmd'),
            '#enable_log#' => $enable_logging,
            '#pid_path#' => '/tmp/boxio.pid',
        );
        if (config::byKey('jeeNetwork::mode') == 'slave') {
            $replace_config['#sockethost#'] = network::getNetworkAccess('internal', 'ip', '127.0.0.1');
			$replace_config['#trigger_url#'] = config::byKey('jeeNetwork::master::ip') . '/plugins/boxio/core/php/jeeboxio.php';
			$replace_config['#apikey#'] = config::byKey('jeeNetwork::master::apikey');
        } 
		else {
            $replace_config['#sockethost#'] = '127.0.0.1';
			$replace_config['#trigger_url#'] = network::getNetworkAccess('internal', 'proto:127.0.0.1:port:comp') . '/plugins/boxio/core/php/jeeboxio.php';
			$replace_config['#apikey#'] = config::byKey('api');
        }

		$config = template_replace($replace_config, file_get_contents($boxio_path . '/config_tmpl.xml'));
		file_put_contents('/tmp/config_boxio.xml', $config);
		chmod('/tmp/config_boxio.xml', 0777);
		if (!file_exists('/tmp/config_boxio.xml')) {
			throw new Exception(__('Impossible de créer : ', __FILE__) . '/tmp/config_boxio.xml');
		}
		$cmd = '/usr/bin/python ' . $boxio_path . '/boxiocmd.py -l -o /tmp/config_boxio.xml';
		if ($_debug) {
			$cmd .= ' -D';
		}
		log::add('boxiocmd', 'info', 'Lancement démon boxiocmd : ' . $cmd);
		$result = exec($cmd . ' >> ' . log::getPathToLog('boxiocmd') . ' 2>&1 &');
		if (strpos(strtolower($result), 'error') !== false || strpos(strtolower($result), 'traceback') !== false) {
			log::add('boxiocmd', 'error', $result);
			return false;
		}

		$i = 0;
		while ($i < 30) {
			$deamon_info = self::deamon_info();
			if ($deamon_info['state'] == 'ok') {
				break;
			}
			sleep(1);
			$i++;
		}
		if ($i >= 30) {
			log::add('boxiocmd', 'error', 'Impossible de lancer le démon boxio, vérifiez le log boxiocmd', 'unableStartDeamon');
			return false;
		}
		message::removeAll('boxio', 'unableStartDeamon');
		log::add('boxiocmd', 'info', 'Démon boxio lancé');
		return true;
	}

	public static function deamon_stop() {
		$pid_file = '/tmp/boxio.pid';
		if (file_exists($pid_file)) {
			$pid = intval(trim(file_get_contents($pid_file)));
			system::kill($pid);
		}
		system::fuserk(config::byKey('socketport', 'boxio', 55002));
	}
	
	public static function syncconfBoxio($_background = true) {
		if (config::byKey('jeeNetwork::mode') == 'master') {
			foreach (jeeNetwork::byPlugin('boxio') as $jeeNetwork) {
				try {
					$jeeNetwork->sendRawRequest('syncconfBoxio', array('plugin' => 'boxio'));
				} catch (Exception $e) {

				}
			}
		}
		log::remove('boxio.syncconf');
		$cmd = 'sudo /bin/bash ' . dirname(__FILE__) . '/../../ressources/syncconf.sh';
		if ($_background) {
			$cmd .= ' >> ' . log::getPathToLog('boxio.syncconf') . ' 2>&1 &';
		}
		log::add('boxio.syncconf', 'info', $cmd);
		shell_exec($cmd);
		/*foreach (self::byType('boxio') as $eqLogic) {
			$eqLogic->loadCmdFromConf(true);
		}*/
	}

	public static function updateDBScenario($data) {
		$sql = "INSERT INTO boxio_scenarios (id_legrand, unit, id_legrand_listen, unit_listen, value_listen, media_listen, frame_number) VALUES ('".$data["id"]."','".$data["unit"]."','".$data["id_listen"]."','".$data["unit_listen"]."','".$data["value"]."','".$data["media"]."','".$data["frame_number"]."')";
		DB::Prepare($sql, array(), DB::FETCH_TYPE_ROW);
	}

	public static function updateMemoryDepth($tramedecrypt) {
		$boxio = boxio::byLogicalId($tramedecrypt["id"], 'boxio');
		$val=boxioCmd::get_params($tramedecrypt['dimension'], $tramedecrypt['param'], 'array');
		log::add('boxio', 'debug', "depth :" . $val['depth'] . "\n");	
		$boxio->setConfiguration('memorydepth',$val['depth']);
		$boxio->save();
	}
		
	public static function updateRequestStatus($decrypted_trame) {
		
		$def = new boxio_def();
		$boxio = boxio::byLogicalId($decrypted_trame["id"], 'boxio');
		$id = $decrypted_trame['id'];
		$unit = $decrypted_trame['unit'];
		$device_type = explode('::', $boxio->getConfiguration('device'));
		$ref_id_legrand = $device_type[0].$unit;
		$config = $boxio->getConfiguration($ref_id_legrand);
		$unit_status = $config["unit_status"];
		$statusid = "status".$unit_status;
		
		$params = preg_split('/[\*|#]/', $decrypted_trame['param']);
		$unit_code = $params[0];
		//ON NE CONNAIT PAS CE STATUS
		if (!isset($def->OWN_STATUS_DEFINITION[$unit_code])) {
			return;
		}
		
		//MISE A JOUR DES LIGHTS
		foreach ($def->OWN_STATUS_DEFINITION[$unit_code]['TYPE'] as $type) {
			$definition = $def->OWN_STATUS_DEFINITION[$unit_code]['DEFINITION'];
			$value = $def->OWN_STATUS_DEFINITION[$unit_code]['VALUE'];
			//GESTION DES LIGHT
			if ($type == 'light') {
				//Valeur par defaut
				$level = $params[array_search("level", $definition)];
				//On recherche la valeur
				foreach ($value['level'] as $command => $reg) {
					if (preg_match($reg, $level)) {
						$level = $command;
						break;
					}
				}
				//On recherche si l'action est en cours d'execution sur un variateur
				$in_progress = false;
				if (isset($value['action_for_time'])) {
					$action_in_progress = $params[array_search("action_for_time", $definition)];
					//On recherche la valeur
					foreach ($value['action_for_time'] as $command => $reg) {
						if (preg_match($reg, $action_in_progress)) {
							$action_in_progress = $command;
							break;
						}
					}
					if ($action_in_progress == 'ACTION_IN_PROGRESS') {
						$in_progress = true;
					}
				}
				if ($level == 'ON_DETECTION') {
					$in_progress = true;
				}
				//On ne met pas à jour si c'est une commande en cours
				if ($in_progress == false) {
					$boxiocmd = $boxio->getCmd('info', 'level'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($level);
						$boxiocmd->save();
					}
					log::add('boxio','debug',"mise a jour du status : ".$level."\n");
				}
			//GESTION DES CONFORT
			} 
			else if ($type == 'heating') {
				//Valeur par defaut
				$status = false;
				if ($definition[0] == 'inter_confort') {
					//Valeur par defaut
					$status_confort = $params[array_search("status_confort", $definition)];
					//On recherche la valeur
					foreach ($value['status_confort'] as $command => $reg) {
						if (preg_match($reg, $status_confort)) {
							$status_confort = $command;
							break;
						}
					}
					$status = $status_confort;
					$boxiocmd = $boxio->getCmd('info', 'status_confort'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($status_confort);
						$boxiocmd->save();
					}
					log::add('boxio','debug',"mise a jour du status : status_confort : ".$status_confort."\n");
				} 
				else if ($definition[0] == 'consigne_confort') {
					$mode = $params[array_search("mode", $definition)];
					
					foreach ($value['mode'] as $command => $reg) {
						if (preg_match($reg, $mode)) {
							$mode = $command;
							break;
						}
					}
					$modechauffe = explode('/', $command);
					$boxiocmd = $boxio->getCmd('info', 'mode'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($modechauffe[0]);
						$boxiocmd->save();
					}
					$boxiocmd = $boxio->getCmd('info', 'chauffe'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($modechauffe[1]);
						$boxiocmd->save();
					}
					log::add('boxio','debug',"mise a jour du status : mode : ".$modechauffe[0]." et chauffe : ". $modechauffe[1] ."\n");
				}
				else if ($definition[0] == 'confort') {
					//Valeur par defaut
					$mode = $params[array_search("mode", $definition)];
					//On recherche la valeur
					foreach ($value['mode'] as $command => $reg) {
						if (preg_match($reg, $mode)) {
							$mode = $command;
							break;
						}
					}
					$internal_temp = boxioCmd::calc_iobl_to_temp($params[array_search("internal_temp_multiplicator", $definition)], $params[array_search("internal_temp", $definition)]);
					$wanted_temp = boxioCmd::calc_iobl_to_temp($params[array_search("wanted_temp_multiplicator", $definition)], $params[array_search("wanted_temp", $definition)]);
					$boxiocmd = $boxio->getCmd('info', 'internal_temp'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($internal_temp/10);
						$boxiocmd->save();
					}
					$boxiocmd = $boxio->getCmd('info', 'wanted_temp'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($wanted_temp/10);
						$boxiocmd->save();
					}
					$boxiocmd = $boxio->getCmd('info', 'mode'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($mode);
						$boxiocmd->save();
					}
					log::add('boxio','debug',"mise a jour du status : internal_temp : ".$internal_temp." et wanted_temp : ". $wanted_temp ."\n");
				} 
				else if ($definition[0] == 'fan') {
					//Valeur par defaut
					$status = $params[array_search("fan_speed", $definition)];
					$boxiocmd = $boxio->getCmd('info', 'fan_speed'.$unit);
					if (isset($boxiocmd)){
						$boxiocmd->event($status);
						$boxiocmd->save();
					}
					log::add('boxio','debug',"mise a jour du status : fan_speed : ".$fan_speed."\n");
				}
				else if ($decrypted_trame['dimension'] == 'QUEL_INDEX') {
					log::add('boxio','debug',"mise a jour du status : Quel_index \n");
					//Valeur par defaut
					//$status = $params[array_search("fan_speed", $definition)];
					//$boxiocmd = $boxio->getCmd('info', 'fan_speed'.$unit);
					//if (isset($boxiocmd)){
						//$boxiocmd->event($status);
						//$boxiocmd->save();
					//}
					//log::add('boxio','debug',"mise a jour du status : fan_speed : ".$fan_speed."\n");
				}
			} 
			
			else if ($type == 'automatism') {
				//TODO: GESTION DES SHUTTER
			}
		}
		return;
	}

	public static function dimensionRequestStatus($decrypted_trame) {
		
		$def = new boxio_def();
		$boxio = boxio::byLogicalId($decrypted_trame["id"], 'boxio');
		$id = $decrypted_trame['id'];
		$unit = $decrypted_trame['unit'];
		$device_type = explode('::', $boxio->getConfiguration('device'));
		$ref_id_legrand = $device_type[0].$unit;
		$config = $boxio->getConfiguration($ref_id_legrand);
		$unit_status = $config["unit_status"];
		$statusid = "status".$unit_status;
		$type=$decrypted_trame["type"];
		
		$params = preg_split('/[\*|#]/', $decrypted_trame['param']);
		
		//GESTION DES LIGHT
		if ($type == 'light') {
		//GESTION DES CONFORT
		} 
		else if ($type == 'heating') {
			if ($decrypted_trame['dimension'] == 'QUEL_INDEX') {
				$IndexHP=$params[1];
				$IndexHC=$params[2];
				$boxiocmd = $boxio->getCmd('info', 'indexhp'.$unit);
				if (isset($boxiocmd)){
					$boxiocmd->event($IndexHP);
					$boxiocmd->save();
				}     
				$boxiocmd = $boxio->getCmd('info', 'indexhc'.$unit);
				if (isset($boxiocmd)){
					$boxiocmd->event($IndexHC);
					$boxiocmd->save();
				}
				log::add('boxio','debug',"mise a jour du status : index_HC : ".$IndexHC." index_HP : ".$IndexHP." \n");
			}
			if ($decrypted_trame['dimension'] == 'INFORMATION_TARIF') {
				if ($params[0] == 2) {
					$status="Heures Creuses";
				} elseif ($params[0] == 3) {
					$status="Heures Pleines";
				} 
				//Valeur par defaut
				//$status = $params[array_search("fan_speed", $definition)];
				$boxiocmd = $boxio->getCmd('info', 'tarif'.$unit);
				if (isset($boxiocmd)){
					$boxiocmd->event($status);
					$boxiocmd->save();
				}
				log::add('boxio','debug',"mise a jour du status : tarif : ".$status." \n");
			}
		} 
		
		else if ($type == 'automatism') {
			//TODO: GESTION DES SHUTTER
		}
		return;
	}

	public static function updateMemory($tramedecrypt) {
		//$boxio = boxio::byLogicalId($tramedecrypt["id"], 'boxio');
		$val=boxioCmd::get_params($tramedecrypt['dimension'], $tramedecrypt['param'], 'array');
		log::add('boxio', 'debug', "Family_Type : " . $val['family_type'] . "\n Address : " . $val['address'] . "\n Preset value : " . $val['preset_value'] . "\n Frame Number : " . $val['frame_number'] . "\n");
		$address = explode('/', $val['address']);
		
		$data["id"]=$tramedecrypt["id"];
		$data["unit"]=$tramedecrypt["unit"];
		$data["id_listen"]=$address[0];
		$data["unit_listen"]=$address[1];
		$data["value"]=$val['preset_value'];
		
		if ($val['family_type'] == 'CPL') { $data["media"]='0';}
		else if ($val['family_type'] == 'RF') {$data["media"]='1';}
		else if ($val['family_type'] == 'IR') {$data["media"]='2';}
							
		$data["frame_number"]=$val['frame_number'];
		boxio::updateDBScenario($data);
	
/*	
		for ($i = 0; $i < $boxio->getConfiguration('memorydepth'); $i++) {
			$fnbr='mem_fnbr'.$i;			
			$fnbr_base=$boxio->getConfiguration($fnbr);
			$ftyp='mem_ftyp'.$i;
			$ftyp_base=$boxio->getConfiguration($ftyp);
			$id='mem_id'.$i;
			$id_base=$boxio->getConfiguration($id);
			$prev='mem_prev'.$i;
			$prev_base=$boxio->getConfiguration($prev);
			$unit='mem_unit'.$i;
			$unit_base=$boxio->getConfiguration($unit);
			$address = explode('/', $val['address']);
			$id_listen = $address[0];
			$unit_listen = $address[1];					
			if ($fnbr_base=='') {
				log::add('boxio', 'debug', "Frame Number ".$val['frame_number']. " inconnu");
				$boxio->setConfiguration($fnbr,$val['frame_number']);
				$boxio->setConfiguration($ftyp,$val['family_type']);
				$boxio->setConfiguration($id,$id_listen);
				$boxio->setConfiguration($unit,$unit_listen);
				$boxio->setConfiguration($prev,$val['preset_value']);
				$boxio->save();
				break;
			} 
			elseif ($fnbr_base!='' && $val['frame_number']==$fnbr_base){
				log::add('boxio', 'debug', "getunit".$id_listen." getid". $unit_listen);
				if ($id_listen!=$id_base) {
					log::add('boxio', 'debug', "update ID");
					$boxio->setConfiguration($fnbr,$val['frame_number']);
					$boxio->setConfiguration($ftyp,$val['family_type']);
					$boxio->setConfiguration($id,$id_listen);
					$boxio->setConfiguration($unit,$unit_listen);
					$boxio->setConfiguration($prev,$val['preset_value']);
					$boxio->save();
					break;
				} 
				elseif ($id_listen==$id_base && ($unit_listen!=$unit_base || $prev_base!=$val['preset_value'] || $ftyp_base!=$val['family_type'])){
					log::add('boxio', 'debug', "update getunit".$id_listen." getid". $unit_listen);
					$boxio->setConfiguration($fnbr,$val['frame_number']);
					$boxio->setConfiguration($ftyp,$val['family_type']);
					$boxio->setConfiguration($id,$id_listen);
					$boxio->setConfiguration($unit,$unit_listen);
					$boxio->setConfiguration($prev,$val['preset_value']);
					$boxio->save();
					break;
				}
			break;
			}
		}*/
	}
		
	public static function send_trame($trame) {
		log::add('boxio','debug',"Send trame");
		if (config::byKey('jeeNetwork::mode') == 'master') {
			foreach (jeeNetwork::byPlugin('boxio') as $jeeNetwork) {
				$socket = socket_create(AF_INET, SOCK_STREAM, 0);
				socket_connect($socket, $jeeNetwork->getRealIp(), config::byKey('socketport', 'boxio', 55002));
				socket_write($socket, trim($trame), strlen(trim($trame)));
				socket_close($socket);
			}
		}
		
		if (config::byKey('port', 'boxio', 'none') != 'none') {
			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
			socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'boxio', 55002));
			socket_write($socket, trim($trame), strlen(trim($trame)));
			socket_close($socket);
		}
		
	}
	
	public static function checkDevice($dev) {
	
		$check="*#1000*".($dev*16)."*51##";
		$boxio = boxio::byLogicalId($dev, 'boxio');
	
		boxio::send_trame($check);
		
	}

	public static function deleteMemory($dev) {
	
		$boxio = boxio::byLogicalId($dev, 'boxio');		
		
		/*for ($i = 0; $i < $boxio->getConfiguration('memorydepth'); $i++) {
			$fnbr='mem_fnbr'.$i;			
			$ftyp='mem_ftyp'.$i;
			$id='mem_id'.$i;
			$unit='mem_unit'.$i;
			$prev='mem_prev'.$i;
			$boxio->setConfiguration($fnbr,"");
			$boxio->setConfiguration($ftyp,"");
			$boxio->setConfiguration($id,"");
			$boxio->setConfiguration($unit,"");
			$boxio->setConfiguration($prev,"");
			$boxio->save();
		}*/
		$sqld = "DELETE FROM boxio_scenarios WHERE id_legrand='".$dev."'";
		log::add('boxio', 'debug', $sqld);
		DB::Prepare($sqld, array(), DB::FETCH_TYPE_ROW);
		log::add('boxio', 'debug', "Suppression des paramètres d'appairage dans la base de donnée avant reconstruction. Suppression de " . $boxio->getConfiguration('memorydepth') . " Equipements\n");
		//$boxio->setConfiguration('memorydepth',"");
		//$boxio->save();
	}
	
	public static function checkMemory($dev,$unit) {
	
		$check="*1000*66*" . (($dev*16)+$unit) . "##";
	
		if (config::byKey('jeeNetwork::mode') == 'master') {
			foreach (jeeNetwork::byPlugin('boxio') as $jeeNetwork) {
				$socket = socket_create(AF_INET, SOCK_STREAM, 0);
				socket_connect($socket, $jeeNetwork->getRealIp(), config::byKey('socketport', 'boxio', 55002));
				socket_write($socket, trim($check), strlen(trim($check)));
				socket_close($socket);
			}
		}
		if (config::byKey('port', 'boxio', 'none') != 'none') {
			$socket = socket_create(AF_INET, SOCK_STREAM, 0);
			socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'boxio', 55002));
			socket_write($socket, trim($check), strlen(trim($check)));
			socket_close($socket);
		}
	}

	public static function createFromDef($_def) { 
        if (config::byKey('autoDiscoverEqLogic', 'boxio') == 0) {
            return false;
        }
        $banId = explode(' ', config::byKey('banboxioId', 'boxio'));
        if (in_array($_def['id'], $banId)) {
            return false;
        }
		
		//faire requete pour connaitre le type
		
        if (!isset($_def['id']) || !isset($_def['type']) || !isset($_def['media']) || $_def['id']=="NULL" 
				|| $_def['type']=="NULL" || $_def['media']=="NULL") {
            log::add('boxio', 'error', 'Information manquante pour ajouter l\'équipement : ' . print_r($_def, true));
            return false;
        }
		
        $boxio = boxio::byLogicalId($_def['id'], 'boxio');
        if (!is_object($boxio)) {
            $eqLogic = new boxio();
            $eqLogic->setName($_def['id']);
        }
        $eqLogic->setLogicalId($_def['id']);
        $eqLogic->setEqType_name('boxio');
        $eqLogic->setIsEnable(1);
        $eqLogic->setIsVisible(1);
		$eqLogic->setConfiguration('media', $_def['media']);
		
      
		if ($_def['type'] == "light" || $_def['type'] == "automatism" || $_def['type'] == "security" || $_def['type'] == "heating" ) {
			$eqLogic->setCategory($_def['type'], 1);
		} 
		else {
			$eqLogic->setCategory('default', 1);
		}
        $eqLogic->save();
		$ref=boxio::checkDevice($_def['id']);
        return $eqLogic;
    }

    public static function devicesParameters($_device = '') {
        $path = dirname(__FILE__) . '/../config/devices';
        if (isset($_device) && $_device != '') {
            $files = ls($path, $_device . '.json', false, array('files', 'quiet'));
            if (count($files) == 1) {
                try {
                    $content = file_get_contents($path . '/' . $files[0]);
                    if (is_json($content)) {
                        $deviceConfiguration = json_decode($content, true);
                        return $deviceConfiguration[$_device];
                    }
                    return array();
                } catch (Exception $e) {
                    return array();
                }
            }
        }
        $files = ls($path, '*.json', false, array('files', 'quiet'));
        $return = array();
        foreach ($files as $file) {
            try {
                $content = file_get_contents($path . '/' . $file);
                if (is_json($content)) {
                    $return += json_decode($content, true);
                }
            } catch (Exception $e) {

            }
        }

        if (isset($_device) && $_device != '') {
            if (isset($return[$_device])) {
                return $return[$_device];
            }
            return array();
        }
        return $return;
    }

	public static function decrypt_trame($trame) {
	
		/*
		 // FONCTION : DECRYPTAGE D'UNE TRAME AU FORMAT LISIBLE
		// PARAMS : $trame=string
		// RETURN : array(
				"trame" => string,
				"mode" => string,
				"media" => 'string',
				"format" => 'string',
				"type" => 'string',
				"value" => string,
				"dimension" => string,
				"param" => string,
				"id" => string,
				"unit" => string,
				"date" => timestamp
		//Exemple
				array(
				"trame" => *2*2*#653565653##,
				"media" => CPL,
				"mode" => multicast,
				"format" => BUS_COMMAND,
				"type" => 2, (automation) Who
				"value" => 2 , (Move Down) What
				"dimension" => string,
				"param" => string,
				"id" => string,
				"unit" => string,				
				"date" => timestamp
				*/
				
		$def = new boxio_def();
		
		$ret_trame = array(
					"trame" => $trame,
					"format" => 'UNKNOWN',
					"mode" => 'UNKNOWN',
					"media" => 'UNKNOWN',
					"type" => 'UNKNOWN',
					"value" => NULL,
					"dimension" => NULL,
					"param" => NULL,
					"id" => NULL,
					"unit" => NULL,
					"date" => date("Y-m-d H:i:s", time())
		);
		
		$find_trame = false;
		//on teste le format de la trame
		foreach ($def->OWN_TRAME as $command => $command_reg) {
			//si on trouve un format valide de trame
			if (preg_match($command_reg, $ret_trame['trame'], $decode_trame)) {
				//on teste le type de la trame
				if ($command == 'BUS_COMMAND' && $decode_trame[1] != '1000') {
					$who = $decode_trame[1];
					$what = $decode_trame[2];
					$where = $decode_trame[3];
					$dimension = NULL;
					$val = NULL;
					$find_trame = true;
				} 
				elseif ($command == 'STATUS_REQUEST') {
					$who = $decode_trame[1];
					$what = NULL;
					$where = $decode_trame[2];
					$dimension = NULL;
					$val = NULL;
					$find_trame = true;
				} 
				elseif ($command == 'DIMENSION_REQUEST') {
					$who = $decode_trame[1];
					$what = $decode_trame[2];
					$where = $decode_trame[2];
					$dimension = $decode_trame[3];
					$val = $decode_trame[4];
					$find_trame = true;
				} 
				elseif ($command == 'DIMENSION_SET') {
					$who = $decode_trame[1];
					$what = $decode_trame[2];
					$where = $decode_trame[4];
					$dimension = $decode_trame[2];
					$val = $decode_trame[3];
					$find_trame = true;
				} 
				elseif ($command == 'ACK' || $command == 'NACK') {
					$who = NULL;
					$what = NULL;
					$where = NULL;
					$dimension = NULL;
					$val = NULL;
					$find_trame = true;
				}
				//Impossible de trouver la command dans ce format de la trame
				if ($find_trame == false) {
					continue;
				}
				//On sauvegarde le format
				$ret_trame["format"] = $command;
				//On test le type de la trame
				foreach ($def->OWN_TRAME_DEFINITION as $key => $value) {
					if ($key == $who) {
						$ret_trame["type"] = $def->OWN_TRAME_DEFINITION[$key]['TYPE'];
						//On recherche s'il existe les value/dimension/param dans la trame
						if (!is_null($what)
								&& (isset($def->OWN_TRAME_DEFINITION[$key][$what]) || isset($def->OWN_TRAME_DEFINITION[$key][$what.'_']))) {
							// on a un parametre on favorise avec le param
							if ($val && isset($def->OWN_TRAME_DEFINITION[$key][$what.'_'])) {
								$ret_trame["value"] = $def->OWN_TRAME_DEFINITION[$key][$what.'_'];
								// on test sans param
							} 
							elseif (isset($def->OWN_TRAME_DEFINITION[$key][$what])) {
								$ret_trame["value"] = $def->OWN_TRAME_DEFINITION[$key][$what];
								// on test avec param en dernier recours
							} 
							elseif (isset($def->OWN_TRAME_DEFINITION[$key][$what.'_'])) {
								$ret_trame["value"] = $def->OWN_TRAME_DEFINITION[$key][$what.'_'];
							}
						}
						if (!is_null($dimension)
								&& (isset($def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension]) || isset($def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension.'_']))) {
							// on a un parametre on favorise avec le param
							if ($val && isset($def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension.'_'])) {
								$ret_trame["dimension"] = $def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension.'_'];
								// on test sans param
							} 
							elseif (isset($def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension])) {
								$ret_trame["dimension"] = $def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension];
								// on test avec param en dernier recours
							} 
							elseif (isset($def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension.'_'])) {
								$ret_trame["dimension"] = $def->OWN_TRAME_DEFINITION[$key]['DIMENSION'][$dimension.'_'];
							}
						}
						if ($val) {
							$ret_trame["param"] = $val;
						}
					}
				}
				//ON RECUPE L'ID
				preg_match($def->OWN_WHERE_DEFINITION, $where, $matches_where);
				if (strlen($matches_where[1]) > 1) {
					$ret_trame["mode"] = $def->OWN_COMMUNICATION_DEFINITION[""];
					$matches_id = $matches_where[1];
					$ret_trame["media"] = $def->OWN_MEDIA_DEFINITION[$matches_where[2]];
				} 
				elseif (strlen($matches_where[2]) > 1) {
					$ret_trame["mode"] = $def->OWN_COMMUNICATION_DEFINITION[$matches_where[1]];
					$matches_id = $matches_where[2];
					$ret_trame["media"] = $def->OWN_MEDIA_DEFINITION[$matches_where[3]];
				}
				if (isset($matches_id)) {
					$ret_trame["id"] = boxioCmd::getIdBoxio($matches_id);
					$ret_trame["unit"] = boxioCmd::getUnitBoxio($matches_id);
				} 
				else {
					$ret_trame["id"] = NULL;
					$ret_trame["unit"] = NULL;
				}
				break;
			}
		}
		return $ret_trame;
	}

	public static function updateStatus($decrypted_trame) {
		//CAS DES VOLETS
		if ($decrypted_trame['type'] == 'automatism') {
			log::add('boxio','debug',"Update Status Automatism");
			boxio::updateStatusShutter($decrypted_trame, true);
		//CAS DES LUMIERES
		} 
		elseif ($decrypted_trame['type'] == 'light') {
			log::add('boxio','debug',"Update Status Light");
			boxio::updateStatusLight($decrypted_trame, true);
		//CAS DES SCENARIOS
		} 
		elseif ($decrypted_trame['type'] == 'scene') {
			log::add('boxio','debug',"Update Status Scene");
			boxio::updateStatusScenario($decrypted_trame);
		//CAS DE LA THERMOREGULATION
		}
		elseif ($decrypted_trame['type'] == 'heating') {
			log::add('boxio','debug',"Update Status Chauffage");
			boxio::updateStatusConfort($decrypted_trame, true);
		//ON NE S'EST PAS DE QUOI IL S'AGIT, ON QUITTE
		}
		else {
			return;
		}
	}
	
	public static function updateStatusShutter($decrypted_trame, $scenarios=false) {
		/*
		// FONCTION : MISE A JOUR DU STATUS DES VOLETS
		// PARAMS : $decrypted_trame = array(
				"trame" => string,
				"format" => 'string',
				"type" => 'string',
				"value" => string,
				"dimension" => string,
				"param" => string,
				"id" => string,
				"unit" => string,)
		$scenarios => boolean (true si l'on doit recherche des scenarios associés)
		*/
		
		$def = new boxio_def();
		
		//Creation des variables utiles
		$boxio = boxio::byLogicalId($decrypted_trame["id"], 'boxio');
		$id = $decrypted_trame["id"];
		$unit = $decrypted_trame["unit"];
		$device_type = explode('::', $boxio->getConfiguration('device'));
		$ref_id_legrand = $device_type[0].$unit;
		$sousdevice = $device_type[1];
		//On recupere la date de l'action et on ajoute le temps du relais interne
		$date = strtotime($decrypted_trame["date"]) + $def->SHUTTER_RELAY_TIME;
		//recuperation du derniere etat connu ET des possibilites
		$config = $boxio->getConfiguration($ref_id_legrand);
		$unit_status = $config["unit_status"];
		$possibility = $config["possibility"];
		$statusid = 'status'.$unit_status;
		$boxiocmd = $boxio->getCmd('info', $statusid);
		$duree_cmd	= $boxiocmd->getConfiguration('DureeCmd');
		$last_status = $boxiocmd->execCmd(null,2);
		log::add('boxio','debug','unit_status : '.$unit_status.' poss : '.$possibility.' Sous_device : '.$sousdevice.' last : '.$last_status.' duréecmd : '.$duree_cmd.' id cmd : '.$boxiocmd->getId() . " date : ".$date);
		//on test s'il faut faire un update des statuts
		if ($decrypted_trame["value"] == 'MOVE_UP'
				|| $decrypted_trame["value"] == 'MOVE_DOWN'
				|| $decrypted_trame["value"] == 'MOVE_STOP') {
			$value = $decrypted_trame["value"];
			//Il ne s'agit pas d'une mise à jour
		} 
		else {
			return;
		}
		//Recuperation des options
		 if ($config["server_opt"] != "NULL") {
			preg_match_all('/(?P<param>.+?)=(?P<value>[^;]+);//', $config["server_opt"], $server_opt);
			$params = array();
			foreach ($server_opt['param'] as $opt => $opt_value) {
				$params[$opt_value] = $server_opt['value'][$opt];
			}
		}
		//gestion des temps ouverture/fermeture en fonction de la date
		//if (strpos($possibility, 'MEMORY') !== FALSE) { //En gros si MEMORY existe dans les possibility (par extrapolation de si STATUS ou SERVER_STATUS existe)
			if (is_numeric($duree_cmd)) {
				$move_time = $duree_cmd;
			} 
			else {
				$move_time = $def->DEFAULT_SHUTTER_MOVE_TIME;
			}
			//mise a jour en fonction du mouvement demande
			if ($sousdevice =='00') {
			//Si il s'agit d'un bouton normal
				log::add('boxio', 'debug', " Bouton normal \n");
				if ($value == 'MOVE_UP') {
					log::add('boxio', 'debug', "Action MOVE_UP");
					//Si le volet est en train de monter
					if ($last_status == 'UP') {
						$status = 'UP';
					//Si le volet est deja en haut
					} 
					elseif ($last_status == '100' || $last_status == 'OPEN') {
						$status = 'OPEN';
					//Si le volet change de sens
					} 
					elseif ($last_status == 'DOWN') {
						$status = 'UP';
						$sec=date("s");
						$updatedate=$boxiocmd->getConfiguration('updatedate');
						if ($updatedate<$date)
						{
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->save();
							$updatedate=0;
						}
						$new_pos = ($move_time - ($updatedate - $date))/$move_time*100;
						$move_time = round($new_pos/100*$move_time);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','OPEN');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
						//Si le volet est en position intermediaire ou completement ferme
					} 
					elseif (is_numeric($last_status) || $last_status == 'CLOSED') {
						if ($last_status == 'CLOSED') {
							$last_status = 0;
						}
						$status = 'UP';
						$sec=date("s");
						log::add('boxio', 'debug', "Point ".$status);
						$move_time = $move_time - ($last_status/100*$move_time);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','OPEN');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
					} 
					else {
						$status = 'OPEN';
					}
				} 
				elseif ($value == 'MOVE_DOWN') {
					log::add('boxio', 'debug', "Action move_DOWN");
					//Si le volet est en train de descendre
					if ($last_status == 'DOWN') {
						$status = 'DOWN';
						//Si le volet est deja en bas
					} 
					elseif ($last_status == '0' || $last_status == 'CLOSED') {
						$status = 'CLOSED';
						//Si le volet change de sens
					} 
					elseif ($last_status == 'UP') {
						$status = 'DOWN';
						$sec=date("s");
						$updatedate=$boxiocmd->getConfiguration('updatedate');
						if ($updatedate<$date)
						{
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->save();
							$updatedate=0;
						}
						$new_pos = ($move_time - ($updatedate - $date))/$move_time*100;
						$move_time = round($new_pos/100*$move_time);
						log::add('boxio', 'debug', " Move time : ".$move_time." New_pos : ".$new_pos);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);						
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','CLOSED');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
						log::add('boxio', 'debug', " Move time : ".$move_time);
						//Si le volet est arrete en position intermediaire ou completement ouvert
					} 
					elseif (is_numeric($last_status) || $last_status == 'OPEN') {
						if ($last_status == 'OPEN') {
							$last_status = 100;
						}
						$status = 'DOWN';
						$sec=date("s");
						log::add('boxio', 'debug', "Point ".$status."sec : ".$sec."movetime : ".$move_time);
						$move_time = ($last_status/100*$move_time);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','CLOSED');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
						log::add('boxio', 'debug', " Move time : ".$move_time);
					} 
					else {
						$status = 'CLOSED';
					}
				} 
				elseif ($value == 'MOVE_STOP') {
					log::add('boxio', 'debug', "Action move_STOP");
					//Par defaut on dit que le volet est arrete et donc à son ancienne position
					$status = $last_status;
					$updatedate=$boxiocmd->getConfiguration('updatedate');
					//Si le volet est deja en mouvement
					if (!is_numeric($last_status) && isset($updatedate)) {
						$new_pos = ($move_time - ($updatedate - $date))/$move_time*100;
						log::add('boxio', 'debug', " updatedate : ".$updatedate." Newpos : ".$new_pos);
						$boxiocmd->setConfiguration('updatedate',NULL);
						if ($last_status == 'UP') {
							$status = round($new_pos);
							log::add('boxio', 'debug', "last_status : Up, status : ".$status);
							$boxiocmd->setConfiguration('returnStateValue',$status);
							$boxiocmd->setConfiguration('returnStateTime',1);
						} 
						elseif ($last_status == 'DOWN') {
							$status = round(100 - $new_pos);
							$boxiocmd->setConfiguration('returnStateValue',$status);
							$boxiocmd->setConfiguration('returnStateTime',1);
							log::add('boxio', 'debug', "last_status : Down, status : ".$status);
						}
						if ($status <= 0) {
							$status = 'CLOSED';
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->setConfiguration('returnStateValue',NULL);
							$boxiocmd->setConfiguration('returnStateTime',NULL);
						} 
						elseif ($status >= 100) {
							$status = 'OPEN';
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->setConfiguration('returnStateValue',NULL);
							$boxiocmd->setConfiguration('returnStateTime',NULL);
						}
					} 
					else {
						$status = 'OPEN';
					}
				}
				$boxiocmd->save();
			}	 
			elseif ($sousdevice == '01') {

				//Si il s'agit d'un bouton inversé
				log::add('boxio', 'debug', "Bouton Inversé ...\n");
				if ($value == 'MOVE_UP') {
					log::add('boxio', 'debug', "Action move_UP");
					//Si le volet est en train de descendre
					if ($last_status == 'DOWN') {
						$status = 'DOWN';
					//Si le volet est deja en bas
					} 
					elseif ($last_status == '0' || $last_status == 'CLOSED') {
						$status = 'CLOSED';
					//Si le volet change de sens
					} 
					elseif ($last_status == 'UP') {
						$status = 'DOWN';
						$sec=date("s");
						$updatedate=$boxiocmd->getConfiguration('updatedate');
						if ($updatedate<$date)
						{
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->save();
							$updatedate=0;
						}
						$new_pos = ($move_time - ($updatedate - $date))/$move_time*100;
						$move_time = round($new_pos/100*$move_time);
						log::add('boxio', 'debug', " Move time : ".$move_time." New_pos : ".$new_pos);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);						
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','CLOSED');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
						log::add('boxio', 'debug', " Move time : ".$move_time);
					//Si le volet est en position intermediaire ou completement ouvert
					} 
					elseif (is_numeric($last_status) || $last_status == 'OPEN') {
						if ($last_status == 'OPEN') {
							$last_status = 100;
						}
						$status = 'DOWN';
						$sec=date("s");
						log::add('boxio', 'debug', "Point ".$status."sec : ".$sec."movetime : ".$move_time);
						$move_time = ($last_status/100*$move_time);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','CLOSED');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
						log::add('boxio', 'debug', " Move time : ".$move_time);
					} 
					else {
						$status = 'OPEN';
					}
				} 
				elseif ($value == 'MOVE_DOWN') {
					log::add('boxio', 'debug', "Action move_DOWN");
					//Si le volet est en train de monter
					if ($last_status == 'UP') {
						$status = 'UP';
						//Si le volet est deja en haut
					} 
					elseif ($last_status == '100' || $last_status == 'OPEN') {
						$status = 'OPEN';
						//Si le volet change de sens
					} 
					elseif ($last_status == 'DOWN') {
						$status = 'UP';
						$sec=date("s");
						$updatedate=$boxiocmd->getConfiguration('updatedate');
						if ($updatedate<$date)
						{
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->save();
							$updatedate=0;
						}
						$new_pos = ($move_time - ($updatedate - $date))/$move_time*100;
						$move_time = round($new_pos/100*$move_time);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','OPEN');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
						//Si le volet est arrete en position intermediaire ou completement fermé
					} 
					elseif (is_numeric($last_status) || $last_status == 'CLOSED') {
						if ($last_status == 'CLOSED') {
							$last_status = 0;
						}
						$status = 'UP';
						$sec=date("s");
						log::add('boxio', 'debug', "Point ".$status);
						$move_time = $move_time - ($last_status/100*$move_time);
						$move_time_quotient = floor($move_time/60);
						log::add('boxio', 'debug', " Move time : ".$move_time);
						$boxiocmd->setConfiguration('updatedate',$date+$move_time);
						$boxiocmd->setConfiguration('returnStateValue','OPEN');
						$nextupdate= 1+$move_time_quotient;
						$boxiocmd->setConfiguration('returnStateTime',$nextupdate);
					} 
					else {
						$status = 'CLOSED';
					}
				} 
				elseif ($value == 'MOVE_STOP') {
					log::add('boxio', 'debug', "Action move_STOP");
					//Par defaut on dit que le volet est arrete et donc à son ancienne position
					$status = $last_status;
					$updatedate=$boxiocmd->getConfiguration('updatedate');
					//Si le volet est deja en mouvement
					if (!is_numeric($last_status) && isset($updatedate)) {
						$new_pos = ($move_time - ($updatedate - $date))/$move_time*100;
						log::add('boxio', 'debug', " updatedate : ".$updatedate." Newpos : ".$new_pos);
						$boxiocmd->setConfiguration('updatedate',NULL);
						if ($last_status == 'UP') {
							$status = round($new_pos);
							log::add('boxio', 'debug', "last_status : Up, status : ".$status);
							$boxiocmd->setConfiguration('returnStateValue',$status);
							$boxiocmd->setConfiguration('returnStateTime',1);
						} 
						elseif ($last_status == 'DOWN') {
							$status = round(100 - $new_pos);
							$boxiocmd->setConfiguration('returnStateValue',$status);
							$boxiocmd->setConfiguration('returnStateTime',1);
							log::add('boxio', 'debug', "last_status : Down, status : ".$status);
						}
						if ($status <= 0) {
							$status = 'CLOSED';
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->setConfiguration('returnStateValue',NULL);
							$boxiocmd->setConfiguration('returnStateTime',NULL);
						} 
						elseif ($status >= 100) {
							$status = 'OPEN';
							$boxiocmd->setConfiguration('updatedate',NULL);
							$boxiocmd->setConfiguration('returnStateValue',NULL);
							$boxiocmd->setConfiguration('returnStateTime',NULL);
						}
					}
				} 
				else {
					$status = 'OPEN';
				} 
				$boxiocmd->save();
			}
		//mise a jour simple du bouton
		/*} 
		else {
			$status = $value;
		}*/
		//Mise à jour de la touche de l'equipement (s'il ne s'agit pas du unit_status OU de la memoire cas particulier du SOMFY RF)
		/*if ($unit != $unit_status || strpos($possibility, 'MEMORY') === FALSE) {
			log::add('boxio','debug',"mise a jour du value : ".$value."\n");
			$boxiocmd->event($value);		
		}*/
		//Mise à jour interne du status de l'equipement
		//if (strpos($possibility, 'MEMORY') !== FALSE) {
			log::add('boxio','debug',"mise a jour du status : ".$status."\n");
			$boxiocmd->event($status);
		//}
		//Mise à jour des groupe de volet en parametre (INTERFACE SOMFY)
		if (isset($params['grp_opt'])) {
			$grp_shutter = explode(',', $params['grp_opt']);
			$grp_decrypted_trame = $decrypted_trame;
			foreach ($grp_shutter as $grp => $new_unit) {
				$grp_decrypted_trame['unit'] = $new_unit;
				boxio::updateStatusShutter($grp_decrypted_trame, false);
			}
		}
		//Mise à jour des scenarios si necessaire
		if ($scenarios === true) {
			$scenarios_decrypted_trame = $decrypted_trame;
			
			$query = "SELECT id_legrand,unit FROM boxio_scenarios WHERE `id_legrand_listen`=$id AND `unit_listen`=$unit;";
			$result =  DB::Prepare($query, array(), DB::FETCH_TYPE_ALL);
			$row=sizeof($result);
			for ($i=0; $i<$row; $i++){
				$scenarios_decrypted_trame['id'] = $result[$i]['id_legrand'];
				$scenarios_decrypted_trame['unit'] = $result[$i]['unit'];
				log::add('boxio','debug',"update_scenario_id : ".$result[$i]['id_legrand']." update_scenario_unit : ".$result[$i]['unit']);
				boxio::updateStatusShutter($scenarios_decrypted_trame, false);
			}
			
			/*
			$memorydepth=$boxio->getConfiguration('memorydepth');
			log::add('boxio','debug',"mem_depth : ".$memorydepth);
			for ($i = 0; $i < $memorydepth; $i++)
			{
				$mem_id="mem_id".$i;
				$mem_unit="mem_unit".$i;
				log::add('boxio','debug',"mem_id : ".$mem_id." mem_unit : ".$mem_unit);
				$scenarios_decrypted_trame['id']=$boxio->getConfiguration($mem_id);
				$scenarios_decrypted_trame['unit']=$boxio->getConfiguration($mem_unit);
				log::add('boxio','debug',"id_scenario : ". $scenarios_decrypted_trame['id'] ." unit_scenario : ".$scenarios_decrypted_trame['unit']);
				$i++;
				boxio::updateStatusShutter($scenarios_decrypted_trame, false);
			}*/
		}
	}
	
	public static function updateStatusSecurity($decrypted_trame, $scenarios=false) {
		/*
		// FONCTION : MISE A JOUR DU STATUS DES SECURITY
		// PARAMS : $decrypted_trame = array(
						"trame" => string,
						"format" => 'string',
						"type" => 'string',
						"value" => string,
						"dimension" => string,
						"param" => string,
						"id" => string,
						"unit" => string,)
				$scenarios => boolean (true si l'on doit recherche des scenarios associés)
		*/
		
		$def = new boxio_def();
		//Creation des variables utiles
		$boxio = boxio::byLogicalId($decrypted_trame["id"], 'boxio');
		$id = $decrypted_trame["id"];
		$unit = $decrypted_trame["unit"];
		$device_type = explode('::', $boxio->getConfiguration('device'));
		$ref_id_legrand = $device_type[0].$unit;
		$sousdevice = $device_type[1];
		//recuperation du unit principale de sauvegarde des status
		$config = $boxio->getConfiguration($ref_id_legrand);
		$unit_status = $config["unit_status"];
		$statusid = "status".$unit_status;
		$boxiocmd = $boxio->getCmd('info', $statusid);
		$status = NULL;
		log::add('boxio','debug',"statusid : ".$statusid." ref legrand/sous device: ".$ref_id_legrand."/".$sousdevice." unit status : ".$unit_status);
			
		//Appui Touche 1
		if ($decrypted_trame["unit"] == '2') {
				$status = 'TOUCHE1';
		}
		//Appui Touche 2
		else if ($decrypted_trame["unit"] == '3') {
				$status = 'TOUCHE2';
		}
		//Mise en Service Totale
		else if ($decrypted_trame["unit"] == '4') {
				$status = 'Mise en Service Totale';
		}
		//Arret
		else if ($decrypted_trame["unit"] == '5') {
				$status = 'Arret';
		}
		//Mise en Service Partielle 
		else if ($decrypted_trame["unit"] == '6') {
				$status = 'Mise en Service Partielle';
		}
		//Départ En Alarme
		else if ($decrypted_trame["unit"] == '7') {
				$status = 'Départ en Alarme';
		}
		//Vérrouillage Alarme Après Tempo de Sortie
		else if ($decrypted_trame["unit"] == '8') {
				$status = 'Verrouillage Alarme après Tempo de Sortie';
		}
		//Défaut Détécté (par exemple l'alarme s'est déclenchée)
		else if ($decrypted_trame["unit"] == '9') {
				$status = 'Défaut Détécté';
		}
		else {
			return;
		}
		
		//On annule les éventuels action en cours
		
		
		$boxiocmd->setConfiguration('updatedate',NULL);
		$boxiocmd->setConfiguration('returnStateValue',NULL);
		$boxiocmd->setConfiguration('returnStateTime',NULL);
		
		//Mise à jour des scenarios si necessaire
		if ($scenarios === true) {
			$scenarios_decrypted_trame = $decrypted_trame;
			
			$query = "SELECT id_legrand,unit FROM boxio_scenarios WHERE id_legrand_listen='$id' AND unit_listen='$unit' AND id_legrand<>'$id';";
			$result =  DB::Prepare($query, array(), DB::FETCH_TYPE_ALL); 
			$row=sizeof($result);
			for ($i=0; $i<$row; $i++){
				$scenarios_decrypted_trame['id'] = $result[$i]['id_legrand'];
				$scenarios_decrypted_trame['unit'] = $result[$i]['unit'];
				log::add('boxio','debug',"update_scenario_id : ".$result[$i]['id_legrand']." update_scenario_unit : ".$result[$i]['unit']);
				boxio::updateStatusSecurity($scenarios_decrypted_trame, false);
			}
		}
		log::add('boxio','debug',"mise a jour du status : ".$status."\n");
		$boxiocmd->event($status);
		$boxiocmd->save();
	}
	
	public static function updateStatusLight($decrypted_trame, $scenarios=false) {
		/*
		// FONCTION : MISE A JOUR DU STATUS DES LIGHTS
		// PARAMS : $decrypted_trame = array(
						"trame" => string,
						"format" => 'string',
						"type" => 'string',
						"value" => string,
						"dimension" => string,
						"param" => string,
						"id" => string,
						"unit" => string,)
				$scenarios => boolean (true si l'on doit recherche des scenarios associés)
		*/
		
		$def = new boxio_def();
		//Creation des variables utiles
		$boxio = boxio::byLogicalId($decrypted_trame["id"], 'boxio');
		$id = $decrypted_trame["id"];
		$unit = $decrypted_trame["unit"];
		$device_type = explode('::', $boxio->getConfiguration('device'));
		$ref_id_legrand = $device_type[0].$unit;
		$sousdevice = $device_type[1];
		//On recupere la date de l'action
		$date = strtotime($decrypted_trame["date"]);
		//recuperation du unit principale de sauvegarde des status
		$config = $boxio->getConfiguration($ref_id_legrand);
		$unit_status = $config["unit_status"];
		$unit_code = $config["unit_code"];
		$statusid = "status".$unit_status;
		$boxiocmd = $boxio->getCmd('info', $statusid);
		if (!isset($def->OWN_STATUS_DEFINITION[$unit_code]['DEFINITION'][0])) {
			$type = 'other';
		} else {
			$type = $def->OWN_STATUS_DEFINITION[$unit_code]['DEFINITION'][0];
		}
		$timer = 0;//L'action est par défaut immédiate
		$status = NULL;
		log::add('boxio','debug',"statusid : ".$statusid." ref legrand/sous device: ".$ref_id_legrand."/".$sousdevice." date : ".$date." unit status : ".$unit_status." unit_code : ".$unit_code." type : ".$type);
		
		//On recupere les server_opt
		$server_opt = $config["server_opt"];
		//s'il y a un timer dans les server_opt on l'inclus tout de suite
		if (preg_match('/timer=(?P<seconds>\d+)/',$server_opt,$res_timer)) {
			$timer = $res_timer['seconds'];
		}
		//TODO: mettre à jour les modes des inters
				
		//Si un variateur essai de mettre à jour un inter on en tient pas compte
		if ($type != 'variator' && (
			$decrypted_trame["value"] == 'DIM_STOP'
			|| $decrypted_trame["dimension"] == 'DIM_STEP'
			|| $decrypted_trame["dimension"] == 'GO_TO_LEVEL_TIME')) {
			return;
		}
		//Gestion des ACTION
		//TODO: gérer les ACTION_IN_TIME
		if ($decrypted_trame["value"] == 'ACTION_FOR_TIME') {
			$value = 'ACTION_FOR_TIME';
			log::add('boxio','debug',"ACTION_FOR_TIME");
			preg_match('/(?P<time>\d+)/', $decrypted_trame["param"], $param);
			//on test si le status est trouve
			if (isset($param['time'])) {
				$timer = boxioCmd::calc_iobl_to_time($param['time']);
				//on a envoyé en interne le status sinon on prend par defaut ON
				if (isset($decrypted_trame['internal_status'])) {
					$status = $decrypted_trame['internal_status'];
				} else {
					$status = 'ON';
				}
				$next_status = 'OFF';
			} else {
				$status = NULL;
			}
		}
		//Interruption des actions en cours on fait une demande de status
		else if ($decrypted_trame["value"] == 'DIM_STOP') {
			$value = 'DIM_STOP';
			log::add('boxio','debug',"DIM_STOP");
			//on supprimme les actions en cours
			$ownid = boxioCmd::ioblId_to_ownId($id, $unit);
			$trame="#1000*".$ownid."*55##";
			$boxiocmd->setConfiguration('updatedate',NULL);
			$boxiocmd->setConfiguration('returnStateValue',NULL);
			$boxiocmd->setConfiguration('returnStateTime',NULL);
			$res = boxio::send_trame($trame);
		}
		//Allumage
		else if ($decrypted_trame["value"] == 'ON') {
			$value = 'ON';
			$status = 'ON';
			//S'il y a un timer dans le server_opt
			if ($timer != 0) {
				$next_status = 'OFF';
			} else {
				$next_status = 'ON';
			}
		}
		//Extinction 
		else if ($decrypted_trame["value"] == 'OFF') {
			$value = 'OFF';
			$status = 'OFF';
			//S'il y a un timer dans le server_opt
			if ($timer != 0) {
				$next_status = 'ON';
			} else {
				$next_status = 'OFF';
			}
		}
		//Variation par etape 
		else if ($decrypted_trame["dimension"] == 'DIM_STEP') {
			$value = 'DIM_STEP';
			log::add('boxio','debug',"DIM_STEP");
			//Recuperation du derniere etat connu(last_status de l'unitstatus)
			$last_status = $boxiocmd->execCmd(null,2);
			if (!is_numeric($last_status)) {
				if ($last_status == 'OFF') {
					$last_status = 0;
				} else if ($last_status == 'ON') {
					$last_status = 100;
				} else {
					$last_status = 0;
				}
			}
			preg_match('/(?P<step>\d+?)\*(?P<time>\d+)/', $decrypted_trame["param"], $param);
			//on test si le status est trouve
			if (isset($param['step']) && isset($param['time'])) {
				$timer = boxioCmd::calc_iobl_to_time($param['time']);
				$change_status = boxioCmd::calc_iobl_to_light($param['step']);
				$next_status = $last_status + $change_status;
				if ($next_status > 100) {
					$next_status = 100;
				} else if ($next_status < 0) {
					$next_status = 0;
				}
				if ($last_status < $next_status) {
					$status = 'DIM_UP_'.$last_status.'_TO_'.$next_status.'_IN_'.$timer.'S';
				} else {
					$status = 'DIM_DOWN_'.$last_status.'_TO_'.$next_status.'_IN_'.$timer.'S';
				}
				if ($next_status == 0) {
					$next_status = 'OFF';
				} else if ($next_status == 100) {
					$next_status = 'ON';
				}
			} else {
				$next_status = NULL;
			}
		}
		//Variation directe 
		else if ($decrypted_trame["dimension"] == 'GO_TO_LEVEL_TIME') {
			$value = 'GO_TO_LEVEL_TIME';
			log::add('boxio','debug',"GO_TO_LEVEL_TIME");
			//Recuperation du derniere etat connu
			$last_status = $boxiocmd->execCmd(null,2);
			if (!is_numeric($last_status)) {
				if ($last_status == 'OFF') {
					$last_status = 0;
				} else if ($last_status == 'ON') {
					$last_status = 100;
				} else {
					$last_status = 0;
				}
			}
			preg_match('/(?P<level>\d+?)\*(?P<time>\d+)/', $decrypted_trame["param"], $param);
			//on test si le status est trouve
			if (isset($param['level']) && isset($param['time'])) {
				$timer = boxioCmd::calc_iobl_to_time($param['time']);
				$next_status = $param['level'];
				if ($next_status > 100) {
					$next_status = 100;
				} else if ($next_status < 0) {
					$next_status = 0;
				}
				if ($last_status < $next_status) {
					$status = 'DIM_UP_'.$last_status.'_TO_'.$next_status.'_IN_'.$timer.'S';
				} else {
					$status = 'DIM_DOWN_'.$last_status.'_TO_'.$next_status.'_IN_'.$timer.'S';
				}
				if ($next_status == 0) {
					$next_status = 'OFF';
				} else if ($next_status == 100) {
					$next_status = 'ON';
				}
			} else {
				$status = NULL;
			}
			//Il ne s'agit pas d'une mise à jour
		} else {
			return;
		}
		
		//on n'a pas trouve le nouveau status, erreur dans la trame ?
		if ($status == NULL) {
			return;
		}
		//Mise à jour de la touche de l'equipement (s'il ne s'agit pas du unit_status)
		/*if ($unit != $unit_status) {
			$query = "UPDATE `equipements_status` SET status='$value' WHERE id_legrand='$id' AND unit='$unit'";
			$this->mysqli->query($query);
		}*/
		//On annule les éventuels action en cours
		
		
		$boxiocmd->setConfiguration('updatedate',NULL);
		$boxiocmd->setConfiguration('returnStateValue',NULL);
		$boxiocmd->setConfiguration('returnStateTime',NULL);
		
		//Dans le cas d'une commande temporelle on met le status en attente de mise a jour sauf si la commande est inférieur à 1s
		if ($timer>1) {
			$boxiocmd->setConfiguration('returnStateTime',$date+$timer);
			$boxiocmd->setConfiguration('returnStateValue',$next_status);
		}
		//La commande n'est pas temporisé on indique la bonne valeur (au cas ou cela na pas ete fait)
		else {
			$status = $next_status;
		}
		
		//Mise à jour des scenarios si necessaire
		if ($scenarios === true && $decrypted_trame["dimension"] != 'GO_TO_LEVEL_TIME') {
			$scenarios_decrypted_trame = $decrypted_trame;
			
			$query = "SELECT id_legrand,unit FROM boxio_scenarios WHERE id_legrand_listen='$id' AND unit_listen='$unit' AND id_legrand<>'$id';";
			$result =  DB::Prepare($query, array(), DB::FETCH_TYPE_ALL); 
			$row=sizeof($result);
			for ($i=0; $i<$row; $i++){
				$scenarios_decrypted_trame['id'] = $result[$i]['id_legrand'];
				$scenarios_decrypted_trame['unit'] = $result[$i]['unit'];
				log::add('boxio','debug',"update_scenario_id : ".$result[$i]['id_legrand']." update_scenario_unit : ".$result[$i]['unit']);
				boxio::updateStatusLight($scenarios_decrypted_trame, false);
			}
			
			
			/*$memorydepth=$boxio->getConfiguration('memorydepth');
			for($i = 0; $i < $memorydepth; $i++)
			{
				$mem_id="mem_id".$i;
				$mem_unit="mem_unit".$i;
				log::add('boxio','debug',"mem_id : ".$mem_id." mem_unit : ".$mem_unit);
				$scenarios_decrypted_trame['id_legrand']=$boxio->getConfiguration($mem_id);
				$scenarios_decrypted_trame['unit']=$boxio->getConfiguration($mem_unit);
				boxio::updateStatusLight($scenarios_decrypted_trame, false);
			}*/
		}
		log::add('boxio','debug',"mise a jour du status : ".$status."\n");
		$boxiocmd->event($status);
		$boxiocmd->save();
	}

	public static function updateStatusConfort($decrypted_trame, $scenarios=false) {
		/*
		// FONCTION : MISE A JOUR DU STATUS DES THERMOREGULATION
		// PARAMS : $decrypted_trame = array(
						"trame" => string,
						"format" => 'string',
						"type" => 'string',
						"value" => string,
						"dimension" => string,
						"param" => string,
						"id" => string,
						"unit" => string,)
				$scenarios => boolean (true si l'on doit recherche des scenarios associés)
		*/
		$def = new boxio_def();
		//Creation des variables utiles
		$boxio = boxio::byLogicalId($decrypted_trame["id"], 'boxio');
		$id = $decrypted_trame["id"];
		$unit = $decrypted_trame["unit"];
		//On recupere la date de l'action
		$date = strtotime($decrypted_trame["date"]);
		$device_type = explode('::', $boxio->getConfiguration('device'));
		$ref_id_legrand = $device_type[0].$unit;
		$sousdevice = $device_type[1];
		//recuperation du unit principale de sauvegarde des status
		$config = $boxio->getConfiguration($ref_id_legrand);
		$unit_status = $config["unit_status"];
		$statusid = "status".$unit_status;
		$boxiocmd = $boxio->getCmd('info', $statusid);
		//On recupere les server_opt
		$server_opt = $config["server_opt"];
		$status = NULL;
		log::add('boxio','debug',"ID : ".$id." UNIT : ".$unit." Ref Legrand+unit : ".$ref_id_legrand." Unit_Status : ".$unit_status." Statusid : ".$statusid. " Commande : ".$decrypted_trame["value"]);
		//LOW FAN SPEED
		if ($decrypted_trame["value"] == 'LOW_FAN_SPEED') {
			$value = 'LOW_FAN_SPEED';
			$status = 'LOW_FAN_SPEED';
		}
		//HIGH FAN SPEED
		else if ($decrypted_trame["value"] == 'HIGH_FAN_SPEED') {
			$value = 'HIGH_FAN_SPEED';
			$status = 'HIGH_FAN_SPEED';
			if (preg_match('/timer=(?P<seconds>\d+)/',$server_opt,$timer)) {
				$boxiocmd->setConfiguration('returnStateTime',$date+$timer['seconds']);
				$boxiocmd->setConfiguration('returnStateValue','LOW_FAN_SPEED');
			}
		//ACTION INCONNU
		}
		else if ($decrypted_trame["value"] == 'DEROGATION_CONSIGNE') {
			
			if ($decrypted_trame["param"] == 4) {
				$value = 'Hors-Gel';
				$status = 'Hors-Gel';
			}
			elseif ($decrypted_trame["param"] == 3) {
				$value = 'Eco';
				$status = 'Eco';
			}
			elseif ($decrypted_trame["param"] == 2) {
				$value = 'Confort-2';
				$status = 'Confort-2';
			}
			elseif ($decrypted_trame["param"] == 1) {
				$value = 'Confort-1';
				$status = 'Confort-1';
			}
			elseif ($decrypted_trame["param"] == 0) {
				$value = 'Confort';
				$status = 'Confort';
			}
			log::add('boxio','debug',"Status : ".$status);
			if (preg_match('/timer=(?P<seconds>\d+)/',$server_opt,$timer)) {
				$boxiocmd->setConfiguration('returnStateTime',$date+$timer['seconds']);
				$boxiocmd->setConfiguration('returnStateValue',$status);
			}
		//ACTION INCONNU
		}
		else if ($decrypted_trame["value"] == 'CONSIGNE') {
			if ($decrypted_trame["param"] == 4) {
				$value = 'Hors-Gel';
				$status = 'Hors-Gel';
			}
			elseif ($decrypted_trame["param"] == 3) {
				$value = 'Eco';
				$status = 'Eco';
			}
			elseif ($decrypted_trame["param"] == 2) {
				$value = 'Confort-2';
				$status = 'Confort-2';
			}
			elseif ($decrypted_trame["param"] == 1) {
				$value = 'Confort-1';
				$status = 'Confort-1';
			}
			elseif ($decrypted_trame["param"] == 0) {
				$value = 'Confort';
				$status = 'Confort';
			}
			elseif ($decrypted_trame["param"] == '') {
				$value = 'Confort';
				$status = 'Confort';
			}
			log::add('boxio','debug',"Status : ".$status);
			if (preg_match('/timer=(?P<seconds>\d+)/',$server_opt,$timer)) {
				$boxiocmd->setConfiguration('returnStateTime',$date+$timer['seconds']);
				$boxiocmd->setConfiguration('returnStateValue',$status);
			}
		//ACTION INCONNU
		}
		else {
			return;
		}
		
		//on n'a pas trouve le nouveau status
		if ($status == NULL) {
			return;
		}
		//Mise à jour de la touche de l'equipement (s'il ne s'agit pas du unit_status)
		/*if ($unit != $unit_status) {
			$query = "UPDATE `equipements_status` SET status='$value' WHERE id_legrand='$id' AND unit='$unit'";
			$this->mysqli->query($query);
		}*/
		//Mise à jour interne du status de l'equipement
		//$query = "UPDATE `equipements_status` SET status='$status' WHERE id_legrand='$id' AND unit='$unit_status'";
		//$this->mysqli->query($query);
		
		//Mise à jour des scenarios si necessaire
		if ($scenarios === true) {
			$scenarios_decrypted_trame = $decrypted_trame;
			
			$query = "SELECT id_legrand,unit FROM boxio_scenarios WHERE id_legrand_listen='$id' AND unit_listen='$unit' AND id_legrand<>'$id';";
			$result =  DB::Prepare($query, array(), DB::FETCH_TYPE_ALL); 
			$row=sizeof($result);
			for ($i=0; $i<$row; $i++){
				$scenarios_decrypted_trame['id'] = $result[$i]['id_legrand'];
				$scenarios_decrypted_trame['unit'] = $result[$i]['unit'];
				log::add('boxio','debug',"update_scenario_id : ".$result[$i]['id_legrand']." update_scenario_unit : ".$result[$i]['unit']);
				boxio::updateStatusConfort($scenarios_decrypted_trame, false);
			}
			
			
			/*$memorydepth=$boxio->getConfiguration('memorydepth');
			for($i = 0; $i < $memorydepth; $i++)
			{
				$mem_id="mem_id".$i;
				$mem_unit="mem_unit".$i;
				log::add('boxio','debug',"mem_id : ".$mem_id." mem_unit : ".$mem_unit);
				$scenarios_decrypted_trame['id_legrand']=$boxio->getConfiguration($mem_id);
				$scenarios_decrypted_trame['unit']=$boxio->getConfiguration($mem_unit);
				boxio::updateStatusConfort($scenarios_decrypted_trame, false);
			}*/
		}
		//Mise à jour du status
		log::add('boxio','debug',"mise a jour du status : ".$status."\n");
		$boxiocmd->event($status);
		$boxiocmd->save();
	}
	
	public static function updateStatusScenario($decrypted_trame) {
		/*
		// FONCTION : MISE A JOUR DU STATUS DES SCENARIO
		// PARAMS : $decrypted_trame = array(
				"trame" => string,
				"format" => 'string',
				"type" => 'string',
				"value" => string,
				"dimension" => string,
				"param" => string,
				"id" => string,
				"unit" => string,
				"date" => date)
		*/
		//Creation des variables utiles
		$def = new boxio_def();
		
		$boxio = boxio::byLogicalId($decrypted_trame["id"], 'boxio');
		$id = $decrypted_trame["id"];
		$unit = $decrypted_trame["unit"];
		$value = $decrypted_trame["value"];
		$date = $decrypted_trame["date"];
		$param = $decrypted_trame["param"];
		$device_type = explode('::', $boxio->getConfiguration('device'));
		$ref_id_legrand = $device_type[0].$unit;
		$sousdevice = $device_type[1];
		$config = $boxio->getConfiguration($ref_id_legrand);
		$family = $config["family"];
		log::add('boxio','debug',"type Scenario : update de l'ID : ".$id." UNIT : ".$unit. " Commande : ".$decrypted_trame["value"]." type :".$family);
		
		//On arrete l'action si celle ci est de type specifique
		if ($decrypted_trame['value'] == 'STOP_ACTION') {
			return;
		log::add('boxio','debug',"action de type :".$decrypted_trame['value']);
		}
		
		//On recherche si le scenario n'est pas de type SECURITY, si oui on le met à jour en fonction des units
		if ($family == 'SECURITY') {
			log::add('boxio','debug',"Scenario de type Security");
			boxio::updateStatusSecurity($decrypted_trame, false);
		}
		
		//Mise a jour de  l'equipement scenario
		if ($family != 'SECURITY') {
			log::add('boxio','debug',"Statusid : ".$statusid);
			$statusid = "status".$unit;
			$boxiocmd = $boxio->getCmd('info', $statusid);
			$boxiocmd->event($value);
			$boxiocmd->save();
		}
						
		//On recherche si le scenario n'est pas de type LIGHTING, si oui on le met à jour tout de suite
		
		if ($family == 'LIGHTING') {
			boxio::updateStatusLight($decrypted_trame, false);
			log::add('boxio','debug',"Scenario de type light");
		}
		
		
		//On recherche les elements affectés et comment il réagisse
		$query = "SELECT DISTINCT id_legrand,unit,value_listen,id_legrand_listen,unit_listen FROM boxio_scenarios WHERE id_legrand_listen='$id' AND unit_listen='$unit' ;";
		$result =  DB::Prepare($query, array(), DB::FETCH_TYPE_ALL); 
		$row=sizeof($result);

		$scenarios_decrypted_trame = array(
				'format' => 'DIMENSION_REQUEST',
				'value' => '',
				'dimension' => '',
				'param' => '',
				'date' => $date
		);
		for ($i=0; $i<$row; $i++){
			log::add('boxio','debug',"id associé: ".$result[$i]['id_legrand']." unit associé: ".$result[$i]['unit']." id_listen associé: ".$result[$i]['id_legrand_listen']." unit_listen associé: ".$result[$i]['unit_listen']." fonction associé: ".$result[$i]['value_listen']);
			
			$scenarios_decrypted_trame['id'] = $result[$i]['id_legrand'];
			$scenarios_decrypted_trame['unit'] = $result[$i]['unit'];
			$boxioscenario = boxio::byLogicalId($scenarios_decrypted_trame["id"], 'boxio');
			$device_typescenario = explode('::', $boxioscenario->getConfiguration('device'));
			$ref_id_legrandscenario = $device_typescenario[0].$scenarios_decrypted_trame['unit'];
			$configscenario = $boxioscenario->getConfiguration($ref_id_legrandscenario);
			$familyscenario = $configscenario["family"];
			log::add('boxio','debug',"id associé: ".$scenarios_decrypted_trame['id']." type associé: ".$familyscenario);
		
			
			//CAS DES LUMIERES
			if ($familyscenario == 'LIGHTING') {
				$scenarios_decrypted_trame['type'] = 'LIGHTING';
				//on teste le format de la trame
				foreach ($def->OWN_SCENARIO_DEFINITION['light'] as $command => $command_reg) {
					$scenarios_decrypted_trame['dimension'] = '';
					$scenarios_decrypted_trame['value'] = '';
					$scenarios_decrypted_trame['param'] = '';
					$scenarios_decrypted_trame['internal_status'] = '';
					
					//si on trouve un format valide de trame
					if (preg_match($command_reg, $result[$i]['value_listen'])) {
						if ($command == 'LEVEL') {
							$status = $result[$i]['value_listen'];
							$scenarios_decrypted_trame['dimension'] = 'GO_TO_LEVEL_TIME';
							$scenarios_decrypted_trame['param'] = $result[$i]['value_listen'].'*0';
						} else if ($command == 'ON' || $command == 'ON_FORCED' || $command == 'AUTO_ON') {
							$status = 'ON';
							$scenarios_decrypted_trame['value'] = 'ON';
						} else if ($command == 'OFF' || $command == 'OFF_FORCED' || $command == 'AUTO_OFF') {
							$status = 'OFF';
							$scenarios_decrypted_trame['value'] = 'OFF';
						} else {
							continue;
						}
						//ON SIMULE EN INTERNE UNE TRAME DE MISE A JOUR
						//SI C'EST UN TIMER ON ANTICIPE LE FUTUR OFF
						if ($decrypted_trame['value'] == 'ACTION_FOR_TIME') {
							$scenarios_decrypted_trame['dimension'] = '';
							$scenarios_decrypted_trame['value'] = 'ACTION_FOR_TIME';
							$scenarios_decrypted_trame['param'] = $param;
							$scenarios_decrypted_trame['internal_status'] = $status;
							boxio::updateStatusLight($scenarios_decrypted_trame, false);
							log::add('boxio','debug',"Equipement Scenario, Update Light");
						} else {
							boxio::updateStatusLight($scenarios_decrypted_trame, false);
							log::add('boxio','debug',"Equipement Scenario, Update Light");
						}
						//On arrete de boucle ce n'est plus necessaire
						break;
					}
				}
			}
			//CAS DES SHUTTER
			else if ($familyscenario == 'SHUTTER') {
				$scenarios_decrypted_trame['type'] = 'SHUTTER';
				//on teste le format de la trame
				foreach ($def->OWN_SCENARIO_DEFINITION['automatism'] as $command => $command_reg) {
					//si on trouve un format valide de trame
					if (preg_match($command_reg, $result[$i]['value_listen'])) {
						$scenarios_decrypted_trame['value'] = $command;
						$scenarios_decrypted_trame['dimension'] = '';
						$scenarios_decrypted_trame['param'] = '';
						//ON SIMULE EN INTERNE UNE TRAME DE MISE A JOUR
						boxio::updateStatusShutter($scenarios_decrypted_trame, false);
						log::add('boxio','debug',"Equipement Scenario, Update Volet");
					}
				}
			}
			//CAS DES THERMOREGULATION
			else if ($familyscenario == 'THERMOREGULATION') {
				$scenarios_decrypted_trame['type'] = 'THERMOREGULATION';
				//on teste le format de la trame
				foreach ($def->OWN_SCENARIO_DEFINITION['heating'] as $command => $command_reg) {
					//si on trouve un format valide de trame
					//TODO:Mettre a jour les scenarios
					log::add('boxio','debug',"Equipement Scenario, Update Confort");
				}
			}
		}
	}

/*     * *************************MARKET**************************************** */

	public static function shareOnMarket(&$market) {
		$moduleFile = dirname(__FILE__) . '/../config/devices/' . $market->getLogicalId() . '.json';
		if (!file_exists($moduleFile)) {
			throw new Exception('Impossible de trouver le fichier de conf ' . $moduleFile);
		}
		$tmp = dirname(__FILE__) . '/../../../../tmp/' . $market->getLogicalId() . '.zip';
		if (file_exists($tmp)) {
			if (!unlink($tmp)) {
				throw new Exception(__('Impossible de supprimer : ', __FILE__) . $tmp . __('. Vérifiez les droits', __FILE__));
			}
		}
		if (!create_zip($moduleFile, $tmp)) {
			throw new Exception(__('Echec de création du zip. Répertoire source : ', __FILE__) . $moduleFile . __(' / Répertoire cible : ', __FILE__) . $tmp);
		}
		return $tmp;
	}

	public static function getFromMarket(&$market, $_path) {
		$cibDir = dirname(__FILE__) . '/../config/devices/';
		if (!file_exists($cibDir)) {
			throw new Exception(__('Impossible d\'installer la configuration du module le repertoire n\éxiste pas : ', __FILE__) . $cibDir);
		}
		$zip = new ZipArchive;
		if ($zip->open($_path) === TRUE) {
			$zip->extractTo($cibDir . '/');
			$zip->close();
		} 
		else {
			throw new Exception('Impossible de décompresser le zip : ' . $_path);
		}
		$moduleFile = dirname(__FILE__) . '/../config/devices/' . $market->getLogicalId() . '.json';
		if (!file_exists($moduleFile)) {
			throw new Exception(__('Echec de l\'installation. Impossible de trouver le module ', __FILE__) . $moduleFile);
		}

		foreach (eqLogic::byTypeAndSearhConfiguration('boxio', $market->getLogicalId()) as $eqLogic) {
			$eqLogic->applyModuleConfiguration();
		}
	}

	public static function removeFromMarket(&$market) {
		$moduleFile = dirname(__FILE__) . '/../config/devices/' . $market->getLogicalId() . '.json';
		if (!file_exists($moduleFile)) {
			throw new Exception(__('Echec lors de la suppression. Impossible de trouver le module ', __FILE__) . $moduleFile);
		}
		if (!unlink($moduleFile)) {
			throw new Exception(__('Impossible de supprimer le fichier :  ', __FILE__) . $moduleFile . '. Veuillez vérifier les droits');
		}
	}

	public static function listMarketObject() {
		$return = array();
		foreach (boxio::devicesParameters() as $logical_id => $name) {
			$return[] = $logical_id;
		}
		return $return;
	}

/*     * *********************Methode d'instance************************* */

	public function preInsert() {
		if ($this->getLogicalId() == '') {
			for ($i = 0; $i < 20; $i++) {
				$logicalId = strtoupper(str_pad(dechex(mt_rand()), 8, '0', STR_PAD_LEFT));
				$result = eqLogic::byLogicalId($logicalId, 'boxio');
				if (!is_object($result)) {
					$this->setLogicalId($logicalId);
					break;
				}
			}
		}
	}

	public function postSave() {
		if ($this->getConfiguration('applyDevice') != $this->getConfiguration('device')) {
			$this->applyModuleConfiguration();
		}
	}

	public function applyModuleConfiguration() {
		$this->setConfiguration('applyDevice', $this->getConfiguration('device'));
		$this->save();
		if ($this->getConfiguration('device') == '') {
			return true;
		}
		$device_type = explode('::', $this->getConfiguration('device'));
		$deviceref = $device_type[0];
		$subtype = $device_type[1];
		$device = self::devicesParameters($deviceref);
		if (!is_array($device)) {
			return true;
		}
		if (isset($device['configuration'])) {
			foreach ($device['configuration'] as $key => $value) {
				$this->setConfiguration($key, $value);
				$this->save();
			}
		}
		if (!isset($device['subtype'][$subtype])) {
			if (count($device['subtype']) != 1) {
				return true;
			}
			$device = reset($device['subtype']);
		} 
		else {
			$device = $device['subtype'][$subtype];
		}
		if (isset($device['category'])) {
			foreach ($device['category'] as $key => $value) {
				$this->setCategory($key, $value);
			}
		}
		$cmd_order = 0;
		$link_cmds = array();
		$link_actions = array();
		foreach ($device['commands'] as $command) {
			$cmd = null;
			foreach ($this->getCmd() as $liste_cmd) {
				if ($liste_cmd->getLogicalId() == $command['logicalId']) {
					if ($liste_cmd->getConfiguration('unit') == $command['configuration']['unit']) {
						$cmd = $liste_cmd;
						break;
					}
				}
			}
			try {
				if ($cmd == null || !is_object($cmd)) {
					$cmd = new boxioCmd();
					$cmd->setOrder($cmd_order);
					$cmd->setEqLogic_id($this->getId());
				} 
				else {
					$command['name'] = $cmd->getName();
				}
				utils::a2o($cmd, $command);
				$cmd->save();
				if (isset($command['value'])) {
					$link_cmds[$cmd->getId()] = $command['value'];
				}
				if (isset($command['configuration']) && isset($command['configuration']['updateCmdId'])) {
					$link_actions[$cmd->getId()] = $command['configuration']['updateCmdId'];
				}
				$cmd_order++;
			} catch (Exception $exc) {

			}
		}

		if (count($link_cmds) > 0) {
			foreach ($this->getCmd() as $eqLogic_cmd) {
				foreach ($link_cmds as $cmd_id => $link_cmd) {
					if ($link_cmd == $eqLogic_cmd->getName()) {
						$cmd = cmd::byId($cmd_id);
						if (is_object($cmd)) {
							$cmd->setValue($eqLogic_cmd->getId());
							$cmd->save();
						}
					}
				}
			}
		}
		if (count($link_actions) > 0) {
			foreach ($this->getCmd() as $eqLogic_cmd) {
				foreach ($link_actions as $cmd_id => $link_action) {
					if ($link_action == $eqLogic_cmd->getName()) {
						$cmd = cmd::byId($cmd_id);
						if (is_object($cmd)) {
							$cmd->setConfiguration('updateCmdId', $eqLogic_cmd->getId());
							$cmd->save();
						}
					}
				}
			}
		}


		$this->save();
	}

/*     * **********************Getteur Setteur*************************** */
} 	

class boxioCmd extends cmd {
    /*     * *************************Attributs****************************** */


    /*     * ***********************Methode static*************************** */
	
	public static function calc_iobl_to_light($iobl_value) {
		/*
		// FONCTION : CALCUL UNE VALEUR IOBL DE LUMIERE EN POURCENTAGE
		// PARAMS : $iobl_value => string
		// RETOURNE : LA VALEUR EN POURCENTAGE
		*/
		
		// Augmentation
		if ($iobl_value < 128) {
			$percent = $iobl_value;
			// Diminution
		} 
		else {
			$percent = $iobl_value - 256;
		}
		return $percent;
	}

	public static function calc_iobl_to_time($iobl_value) {
		/*
		// FONCTION : CALCUL UNE VALEUR IOBL D'UNE TEMPORISATION
		// PARAMS : $iobl_value => string
		// RETOURNE : LA VALEUR EN SECONDES
		*/
		$time = $iobl_value / 5;
		//On arrondi à la seconde supérieure
		$time = round($time, 0, PHP_ROUND_HALF_UP);
		return $time;
	}

	public static function calc_iobl_to_temp($iobl_value1, $iobl_value2) {
		/*
		// FONCTION : CALCUL UNE VALEUR IOBL DECOMPOSE DE TEMPERATTURE EN UNE VALEUR ENTIERE
		// PARAMS : $iobl_value1 => string, $iobl_value2 => string
		// RETOURNE : LA VALEUR EN POURCENTAGE
		*/
	
		//TODO : Corriger pour les valeur negative 
		$value = ($iobl_value1*256)+$iobl_value2;
		return $value;
	}
	
	public static function get_params($function, $param, $ret_type='string') {
		/*
		// FONCTION : DECRYPTAGE DES PARAMETRES
		// PARAMS : $function=string => fonction a analyser
		$param=string => liste params separes par des * ou #
		$ret_type=string => type du format retourne 'string' ou 'array'
		// RETURN : $decrypted_array=array|$decrypted_string=string
		*/
		$def = new boxio_def();
		
		$params = preg_split('/[\*|#]/', $param);
		$coma = '';
		$decrypted_string = '';
		$decrypted_array = array();
		//Le parametre est inconnu
		if (!isset($def->OWN_PARAM_DEFINITION[$function])) {
			$decrypted_string = 'unknown='.$param;
			$decrypted_array['unknown'] = $param;
		//Le parametre est de type UNIT_DESCRIPTION_STATUS on decrypt de quoi il s'agit
		} 
		elseif ($function == "UNIT_DESCRIPTION_STATUS" && isset($def->OWN_STATUS_DEFINITION[$params[0]])) {
			for ($i = 0; $i < count($def->OWN_STATUS_DEFINITION[$params[0]]['DEFINITION']); $i++) {
				if (isset($params[$i])) {
					$decrypted_array[$def->OWN_STATUS_DEFINITION[$params[0]]['DEFINITION'][$i]] = $params[$i];
					$decrypted_string .= $coma.$def->OWN_STATUS_DEFINITION[$params[0]]['DEFINITION'][$i].'='.$params[$i];
					$coma = ';';
				}
			}
		//Le parametre est decrypte normalement
		} 
		else {
			for ($i = 0; $i < count($def->OWN_PARAM_DEFINITION[$function]); $i++) {
				if (isset($params[$i])) {
					
					if ($def->OWN_PARAM_DEFINITION[$function][$i] == "function_or_reference") {
						$params[$i] = intval($params[$i])/16;
					} 
					elseif ($def->OWN_PARAM_DEFINITION[$function][$i] == "reference"
							|| $def->OWN_PARAM_DEFINITION[$function][$i] == "version") {
						$params[$i] = dechex(intval($params[$i]));
					}
					elseif ($def->OWN_PARAM_DEFINITION[$function][$i] == "address") {
						$params[$i] = boxioCmd::getIdBoxio($params[$i]).'/'.boxioCmd::getUnitBoxio($params[$i]);
					}
					elseif ($def->OWN_PARAM_DEFINITION[$function][$i] == "family_type") {
						if (isset($def->OWN_FAMILY_DEFINITION[$params[$i]])) {
							$params[$i] = $def->OWN_FAMILY_DEFINITION[$params[$i]];
						}
					}
					$decrypted_array[$def->OWN_PARAM_DEFINITION[$function][$i]] = $params[$i];
					$decrypted_string .= $coma.$def->OWN_PARAM_DEFINITION[$function][$i].'='.$params[$i];
					$coma = ';';
				}
			}
			if ($i < count($params)) {
				$decrypted_array['other_params'] = '';
				$decrypted_string .= ';other=';
				$coma = '';
				while ($i < count($params)) {
					$decrypted_array['other_params'] .= '*'.$params[$i];
					$decrypted_string .= $coma.$params[$i++];
					$coma = '*';
				}
			}
		}
		if ($ret_type == 'string') {
			return ($decrypted_string);
		} 
		elseif ($ret_type == 'array') {
			return ($decrypted_array);
		}
	}
	
	public static function ioblId_to_ownId($id, $unit) {
		/*
		// FONCTION : TRANSFORME UN ID ET UN UNIT IOBL EN UN ID OPENWEBNET
		// PARAMS : $id=string|int,$unit=string|int
		// RETURN : $ownId=int
		*/
		$ownId = ($id*16)+$unit;
		return ($ownId);
	}
	
	public static function getIdBoxio($own_id) {
		/*
		// FONCTION : RECUPERATION DE L'ID LEGRAND DANS UN ID OPENWEBNET
		// PARAMS : $own_id=string|int
		// RETURN : $Id=int
		*/
		$UnitSize = 1;
		$IdUnit = dechex($own_id);
		if (strlen($IdUnit) == 7) {
			$UnitSize = 2;
		}
		$Unit = substr($IdUnit, -$UnitSize);
		$Id = hexdec(substr($IdUnit, 0, -$UnitSize).'0')/16;
		return ($Id);
	}

	public static function getUnitBoxio($own_id) {
		/*
		// FONCTION : RECUPERATION DU UNIT DE L'ID LEGRAND DANS UN ID OPENWEBNET
		// PARAMS : $own_id=string|int
		// RETURN : $Unit=int
		*/
		$UnitSize = 1;
		$IdUnit = dechex($own_id);
		if (strlen($IdUnit) == 7) {
			$UnitSize = 2;
		}
		$Unit = hexdec(substr($IdUnit, -$UnitSize));
		return ($Unit);
	}

    /*     * *********************Methode d'instance************************* */

	public function execute($_options = null) {
		
		if ($this->getType() == 'action') {
			$whatdim = $this->getConfiguration('whatdim');
			$media = $this->getEqlogic()->getConfiguration('media');
			$what = $whatdim["what"];
			$dim = $whatdim["dim"];
			
			$unit= $this->getConfiguration('unit');
			
			$logicalId = boxioCmd::ioblId_to_ownId($this->getEqlogic()->getLogicalId(), $unit);

			$value = trim(str_replace("#WHAT#", $what, $this->getLogicalId()));
			$value = trim(str_replace("#what#", $what, $value));
			$value = trim(str_replace("#DIM#", $dim, $value));
			$value = trim(str_replace("#dim#", $dim, $value));
			
						
			$where = $this->getConfiguration('where');
			
			if ($where == "Unicast") {
				$whereid = "";
			} 
			elseif ($where == "Broadcast") {
				$whereid = "0#";
			} 
			elseif ($where == "Multicast") {	
				$whereid = "#";
			} 
			$value = trim(str_replace("#WHERE#", $whereid, $value));
			$value = trim(str_replace("#where#", $whereid, $value));
			
			if ($media == "CPL") {
				$mediaid = "";
			} 
			elseif ($media == "IR") {
				$mediaid = "#2";
			} 
			elseif ($media == "RF") {	
				$mediaid = "#1";
			} 
			$value = trim(str_replace("#MEDIA#", $mediaid, $value));
			$value = trim(str_replace("#media#", $mediaid, $value));
			
			$value = trim(str_replace("#IDUNIT#", $logicalId, $value));
			$value = trim(str_replace("#idunit#", $logicalId, $value));
			foreach ($this->getEqlogic()->getCategory() as $key => $getcat) {
				if ($getcat==1) {
					$category=$key;
				}
            }
			
			if ($category == "heating") {
				$who = "4";
			} 
			elseif ($category == "security") {
				$who = "8";
			} 
			elseif ($category == "energy") {	
				$who = "18";
			} 
			elseif ($category == "light") {	
				$who = "1";
			} 
			elseif ($category == "automatism") {	
				$who = "2";
			} 
						
			$value = trim(str_replace("#WHO#", $who, $value));

			
            switch ($this->getSubType()) {
                case 'slider':
                $value = str_replace('#slider#', strtoupper(intval($_options['slider'])), $value);
				$value = str_replace('#SLIDER#', strtoupper(intval($_options['slider'])), $value);
                break;
                case 'color':
                $value = str_replace('#color#', $_options['color'], $value);
				$value = str_replace('#COLOR#', $_options['color'], $value);
                break;
				}
				
				$values = explode('&&', $value);
			
            if (config::byKey('jeeNetwork::mode') == 'master') {
                foreach (jeeNetwork::byPlugin('boxio') as $jeeNetwork) {
                    foreach ($values as $value) {
                        $socket = socket_create(AF_INET, SOCK_STREAM, 0);
                        socket_connect($socket, $jeeNetwork->getRealIp(), config::byKey('socketport', 'boxio', 55002));
                        socket_write($socket, trim($value), strlen(trim($value)));
                        socket_close($socket);
						log::add ('boxio','event','Send from Jeedom : '.$value);
                    }
                }
            }
            if (config::byKey('port', 'boxio', 'none') != 'none') {
                foreach ($values as $value) {
                    $socket = socket_create(AF_INET, SOCK_STREAM, 0);
                    socket_connect($socket, '127.0.0.1', config::byKey('socketport', 'boxio', 55002));
                    socket_write($socket, trim($value), strlen(trim($value)));
                    socket_close($socket);
					log::add ('boxio','event','Send from Jeedom : '.$value);
                }
            }
        }
    }

    /*     * **********************Getteur Setteur*************************** */

}

class boxio_def {
	//Position GPS par defaut Centre de la France
	public $DEFAULT_LAT = '46.76306';
	public $DEFAULT_LNG = '2.42472';
	
	//temps par default en secondes pour la recherche des mise à jour de la table cron
	public $DEFAULT_UPDATE_TIME_CRONTAB = 30;

	//temps par default en secondes pour la recherche des mise à jour de la table cron
	public $DEFAULT_UPDATE_TIME_TRIGGERTAB = 30;
	
	//temps par default en secondes pour l'ouverture complete d'un volet (si pas de variable move_time defini en DB)
	public $DEFAULT_SHUTTER_MOVE_TIME = 40;

	//temps pour que le relais interne change d'état
	public $SHUTTER_RELAY_TIME = 1;
	
/*	//Definition des equipements Legrand
	public $OWN_EQUIP = array(
			//"REF+UNIT" => array("ref_legrand","nom","family","media","nom_interne","btn","unit","unit_status","possibility","function_code","unit_code"server_opt","commentaire",
			"672551" => array("67255","Inter Individuel Volet Roulant (Derogation)","SHUTTER","CPL","Monte/Descente/Stop","Monte/Descente/Stop","1","2","COMMAND","50","4","NULL","COMMAND=Mouvement volet"),
			"672552" => array("67255","Inter Individuel Volet Roulant (Derogation)","SHUTTER","CPL","STATUS","Memoire Monte/Descente/Stop","2","2","ACTION,STATUS,MEMORY","50","139","NULL","NULL"),
			"672511" => array("67251","Inter Individuel Volet Roulant (Derogation)","SHUTTER","CPL","Monte/Descente/Stop","Monte/Descente/Stop","1","2","COMMAND","50","4","NULL","COMMAND=Mouvement volet"),
			"672512" => array("67251","Inter Individuel Volet Roulant (Derogation)","SHUTTER","CPL","STATUS","Memoire Monte/Descente/Stop","2","2","ACTION,STATUS,MEMORY","50","139","NULL","NULL")
	);
	*/
	//Definition des differentes trames IOBL possibles
	public $OWN_TRAME = array(
			'SPECIAL_REQUEST' => "/^\*#\d{2,4}\*\*\d{1,2}##$/",
			'ACK' => "/^\*#\*1##$/",//  *#*1##
			'NACK' => "/^\*#\*0##$/",//  *#*0##
			'BUS_COMMAND' => "/^\*(\d+)\*(\d+)\*(\d*#*\d+#*\d*)##$/",//  *WHO*WHAT*WHERE##  *1*1*0#13236017##
			'STATUS_REQUEST' => "/^\*#(\d+)\*(\d*#*\d+#*\d*)##$/",//  *#WHO*WHERE
			'DIMENSION_REQUEST' => "/^\*#(\d+)\*(\d*#*\d+#*\d*)\*([\d#]+)\**([\d\*]*)##$/",//  *#WHO*WHERE*DIMENSION(*VAL1*VALn)##
			'DIMENSION_SET' => "/^\*(\d+)\*(\d*)#*([\d#]*)\*(\d*#*\d+#*\d*)##$/"//  *#WHO*WHERE*#DIMENSION*VAL1*VALn##
	);

	//Definition de la partie WHERE d'une trame
	public $OWN_WHERE_DEFINITION = "/(\d+)?#*(\d+)?#*(\d*)$/";

	//Definition des differentes famille de media
	public $OWN_FAMILY_DEFINITION = array(
			"CPL" => "96",
			"RF" => "64",
			"IR" => "128",
			"96" => "CPL",
			"64" => "RF",
			"128" => "IR"
	);

	//Definition des differents media
	public $OWN_MEDIA_DEFINITION = array(
			"" => "CPL",
			"0" => "CPL",
			"1" => "RF",
			"2" => "IR"
	);

	//Definition des differents media
	public $OWN_COMMUNICATION_DEFINITION = array(
			"" => "UNICAST",
			"0" => "BROADCAST",
			"1" => "MULTICAST",
			"2" => "UNICAST_DIRECT",
			"3" => "UNICAST"
	);

	//Definition des differentes scenarios type
	//@TODO: Modifier le type par le unit_code
	public $OWN_SCENARIO_DEFINITION = array(
			"light" => array(
					"ON" => "/^101$/",
					"OFF" => "/^102$/",
					"ON_FORCED" => "/^103$/",
					"OFF_FORCED" => "/^104$/",
					"AUTO" => "/^105$/",
					"AUTO_ON" => "/^101$/",
					"AUTO_OFF" => "/^102$/",
					"LEVEL" => "/^(\d{1,2})$/"
			),
			"automatism" => array(
					"MOVE_UP" => "/^110$/",
					"MOVE_DOWN" => "/^111$/",
					"STOP" => "/^112$/"
			),
			"heating" => array(
					"PRESENCE" => "/^6$/",
					"ECO" => "/^7$/",
					"HORS_GEL" => "/^8$/",
					"AUTOMATIQUE" => "/^5$/",
					"SONDE1" => "/^0$/",
					"SONDE2" => "/^240$/",
			)
	);

	//Definition des differents scenarios type
	public $OWN_STATUS_DEFINITION = array(
			"1" => array(
					"DEFINITION" => array("inter_confort","unknown","status_confort","unknown","unknown","unknown"),
					"TYPE" => array ("heating"),
					"VALUE" => array (
							"status_confort" => array(
								"ON" => "/^100$/",
								"OFF" => "/^0$/"
							)
					)
			),
			"6" => array(
					"DEFINITION" => array("consigne_confort","mode"),
					"TYPE" => array ("heating"),
					"VALUE" => array (
							"mode" => array(
								"CONFORT" => "/^8$/",
								"CONFORT-1" => "/^9$/",
								"CONFORT-2" => "/^10$/",
								"ECO" => "/^3$/",
								"HORS_GEL" => "/^12$/"
							)
					)
			),
			"14" => array(
					"DEFINITION" => array("variator","wanted_level","level","action_for_time","unknown","unknown"),
					"TYPE" => array ("light"),
					"VALUE" => array (
							"action_for_time" => array(
								"ACTION_IN_PROGRESS" => "/^1$/",
								"NO_ACTION" => "/^0$/"
							),
							"level" => array(
								"OFF" => "/^0$/",
								"1"=>"/^1$/", "2"=>"/^2$/", "3"=>"/^3$/", "4"=>"/^4$/", "5"=>"/^5$/", "6"=>"/^6$/", "7"=>"/^7$/", "8"=>"/^8$/", "9"=>"/^9$/",
								"10"=>"/^10$/", "11"=>"/^11$/", "12"=>"/^12$/", "13"=>"/^13$/", "14"=>"/^14$/", "15"=>"/^15$/", "16"=>"/^16$/", "17"=>"/^17$/", "18"=>"/^18$/", "19"=>"/^19$/",
								"20"=>"/^20$/", "21"=>"/^21$/", "22"=>"/^22$/", "23"=>"/^23$/", "24"=>"/^24$/", "25"=>"/^25$/", "26"=>"/^26$/", "27"=>"/^27$/", "28"=>"/^28$/", "29"=>"/^29$/",
								"30"=>"/^30$/", "31"=>"/^31$/", "32"=>"/^32$/", "33"=>"/^33$/", "34"=>"/^34$/", "35"=>"/^35$/", "36"=>"/^36$/", "37"=>"/^37$/", "38"=>"/^38$/", "39"=>"/^39$/",
								"40"=>"/^40$/", "41"=>"/^41$/", "42"=>"/^42$/", "43"=>"/^43$/", "44"=>"/^44$/", "45"=>"/^45$/", "46"=>"/^46$/", "47"=>"/^47$/", "48"=>"/^48$/", "49"=>"/^49$/",
								"50"=>"/^50$/", "51"=>"/^51$/", "52"=>"/^52$/", "53"=>"/^53$/", "54"=>"/^54$/", "55"=>"/^55$/", "56"=>"/^56$/", "57"=>"/^57$/", "58"=>"/^58$/", "59"=>"/^59$/",
								"60"=>"/^60$/", "61"=>"/^61$/", "62"=>"/^62$/", "63"=>"/^63$/", "64"=>"/^64$/", "65"=>"/^65$/", "66"=>"/^66$/", "67"=>"/^67$/", "68"=>"/^68$/", "69"=>"/^69$/",
								"70"=>"/^70$/", "71"=>"/^71$/", "72"=>"/^72$/", "73"=>"/^73$/", "74"=>"/^74$/", "75"=>"/^75$/", "76"=>"/^76$/", "77"=>"/^77$/", "78"=>"/^78$/", "79"=>"/^79$/",
								"80"=>"/^80$/", "81"=>"/^81$/", "82"=>"/^82$/", "83"=>"/^83$/", "84"=>"/^84$/", "85"=>"/^85$/", "86"=>"/^86$/", "87"=>"/^87$/", "88"=>"/^88$/", "89"=>"/^89$/",
								"90"=>"/^90$/", "91"=>"/^91$/", "92"=>"/^92$/", "93"=>"/^93$/", "94"=>"/^94$/", "95"=>"/^95$/", "96"=>"/^96$/", "97"=>"/^97$/", "98"=>"/^98$/", "99"=>"/^99$/",
								"ON" => "/^100$/"
							)
					)
			),
			"15" => array(
					"DEFINITION" => array("variator","wanted_level","level","action_for_time","unknown","unknown"),
					"TYPE" => array ("light"),
					"VALUE" => array (
							"action_for_time" => array(
								"ACTION_IN_PROGRESS" => "/^1$/",
								"NO_ACTION" => "/^0$/"
							),
							"level" => array(
								"OFF" => "/^0$/",
								"1"=>"/^1$/", "2"=>"/^2$/", "3"=>"/^3$/", "4"=>"/^4$/", "5"=>"/^5$/", "6"=>"/^6$/", "7"=>"/^7$/", "8"=>"/^8$/", "9"=>"/^9$/",
								"10"=>"/^10$/", "11"=>"/^11$/", "12"=>"/^12$/", "13"=>"/^13$/", "14"=>"/^14$/", "15"=>"/^15$/", "16"=>"/^16$/", "17"=>"/^17$/", "18"=>"/^18$/", "19"=>"/^19$/",
								"20"=>"/^20$/", "21"=>"/^21$/", "22"=>"/^22$/", "23"=>"/^23$/", "24"=>"/^24$/", "25"=>"/^25$/", "26"=>"/^26$/", "27"=>"/^27$/", "28"=>"/^28$/", "29"=>"/^29$/",
								"30"=>"/^30$/", "31"=>"/^31$/", "32"=>"/^32$/", "33"=>"/^33$/", "34"=>"/^34$/", "35"=>"/^35$/", "36"=>"/^36$/", "37"=>"/^37$/", "38"=>"/^38$/", "39"=>"/^39$/",
								"40"=>"/^40$/", "41"=>"/^41$/", "42"=>"/^42$/", "43"=>"/^43$/", "44"=>"/^44$/", "45"=>"/^45$/", "46"=>"/^46$/", "47"=>"/^47$/", "48"=>"/^48$/", "49"=>"/^49$/",
								"50"=>"/^50$/", "51"=>"/^51$/", "52"=>"/^52$/", "53"=>"/^53$/", "54"=>"/^54$/", "55"=>"/^55$/", "56"=>"/^56$/", "57"=>"/^57$/", "58"=>"/^58$/", "59"=>"/^59$/",
								"60"=>"/^60$/", "61"=>"/^61$/", "62"=>"/^62$/", "63"=>"/^63$/", "64"=>"/^64$/", "65"=>"/^65$/", "66"=>"/^66$/", "67"=>"/^67$/", "68"=>"/^68$/", "69"=>"/^69$/",
								"70"=>"/^70$/", "71"=>"/^71$/", "72"=>"/^72$/", "73"=>"/^73$/", "74"=>"/^74$/", "75"=>"/^75$/", "76"=>"/^76$/", "77"=>"/^77$/", "78"=>"/^78$/", "79"=>"/^79$/",
								"80"=>"/^80$/", "81"=>"/^81$/", "82"=>"/^82$/", "83"=>"/^83$/", "84"=>"/^84$/", "85"=>"/^85$/", "86"=>"/^86$/", "87"=>"/^87$/", "88"=>"/^88$/", "89"=>"/^89$/",
								"90"=>"/^90$/", "91"=>"/^91$/", "92"=>"/^92$/", "93"=>"/^93$/", "94"=>"/^94$/", "95"=>"/^95$/", "96"=>"/^96$/", "97"=>"/^97$/", "98"=>"/^98$/", "99"=>"/^99$/",
								"ON" => "/^100$/"
							)
					)
			),
			"129" => array(
					"DEFINITION" => array("inter","level"),
					"TYPE" => array ("light"),
					"VALUE" => array (
							"level" => array(
								"ON" => "/^128$/",
								"OFF" => "/^0$/",
								"ON_DEROGATION" => "/^129$/",
								"OFF_DEROGATION" => "/^1$/",
								"ON_DETECTION" => "/^130$/"
							)
					)
			),
			"133" => array(
					"DEFINITION" => array("consigne_confort","mode"),
					"TYPE" => array ("heating"),
					"VALUE" => array (
							"mode" => array(
								"AUTO/ON" => "/^33$/",
								"OFF/OFF" => "/^0$/",
								"ON/ON" => "/^17$/",
								"AUTO/OFF" => "/^32$/"
							)
					)
			),
			"139" => array(
					"DEFINITION" => array("shutter","mode","unknown","unknown","unknown","unknown"),
					"TYPE" => array ("automatism"),
					"VALUE" => array (
							"mode" => array(
								"OPEN" => "/^100$/",
								"UP" => "/^102$/",
								"DOWN" => "/^103$/",
								"CLOSED" => "/^0$/",
								"50" => "/^101$/"
							)
					)
			),
			"141" => array(
					"DEFINITION" => array("fan","fan_speed","unknown","unknown","unknown","unknown"),
					"TYPE" => array ("heating"),
					"VALUE" => array (
							"fan_speed" => array(
								"LOW_FAN_SPEED" => "/^101$/",
								"HIGH_FAN_SPEED" => "/^102$/"
							)
					)
			),
			"143" => array(
					"DEFINITION" => array("variator","wanted_level","level","action_for_time","unknown","unknown"),
					"TYPE" => array ("light"),
					"VALUE" => array (
							"action_for_time" => array(
								"ACTION_IN_PROGRESS" => "/^1$/",
								"NO_ACTION" => "/^0$/"
							),
							"level" => array(
								"OFF" => "/^0$/",
								"1"=>"/^1$/", "2"=>"/^2$/", "3"=>"/^3$/", "4"=>"/^4$/", "5"=>"/^5$/", "6"=>"/^6$/", "7"=>"/^7$/", "8"=>"/^8$/", "9"=>"/^9$/",
								"10"=>"/^10$/", "11"=>"/^11$/", "12"=>"/^12$/", "13"=>"/^13$/", "14"=>"/^14$/", "15"=>"/^15$/", "16"=>"/^16$/", "17"=>"/^17$/", "18"=>"/^18$/", "19"=>"/^19$/",
								"20"=>"/^20$/", "21"=>"/^21$/", "22"=>"/^22$/", "23"=>"/^23$/", "24"=>"/^24$/", "25"=>"/^25$/", "26"=>"/^26$/", "27"=>"/^27$/", "28"=>"/^28$/", "29"=>"/^29$/",
								"30"=>"/^30$/", "31"=>"/^31$/", "32"=>"/^32$/", "33"=>"/^33$/", "34"=>"/^34$/", "35"=>"/^35$/", "36"=>"/^36$/", "37"=>"/^37$/", "38"=>"/^38$/", "39"=>"/^39$/",
								"40"=>"/^40$/", "41"=>"/^41$/", "42"=>"/^42$/", "43"=>"/^43$/", "44"=>"/^44$/", "45"=>"/^45$/", "46"=>"/^46$/", "47"=>"/^47$/", "48"=>"/^48$/", "49"=>"/^49$/",
								"50"=>"/^50$/", "51"=>"/^51$/", "52"=>"/^52$/", "53"=>"/^53$/", "54"=>"/^54$/", "55"=>"/^55$/", "56"=>"/^56$/", "57"=>"/^57$/", "58"=>"/^58$/", "59"=>"/^59$/",
								"60"=>"/^60$/", "61"=>"/^61$/", "62"=>"/^62$/", "63"=>"/^63$/", "64"=>"/^64$/", "65"=>"/^65$/", "66"=>"/^66$/", "67"=>"/^67$/", "68"=>"/^68$/", "69"=>"/^69$/",
								"70"=>"/^70$/", "71"=>"/^71$/", "72"=>"/^72$/", "73"=>"/^73$/", "74"=>"/^74$/", "75"=>"/^75$/", "76"=>"/^76$/", "77"=>"/^77$/", "78"=>"/^78$/", "79"=>"/^79$/",
								"80"=>"/^80$/", "81"=>"/^81$/", "82"=>"/^82$/", "83"=>"/^83$/", "84"=>"/^84$/", "85"=>"/^85$/", "86"=>"/^86$/", "87"=>"/^87$/", "88"=>"/^88$/", "89"=>"/^89$/",
								"90"=>"/^90$/", "91"=>"/^91$/", "92"=>"/^92$/", "93"=>"/^93$/", "94"=>"/^94$/", "95"=>"/^95$/", "96"=>"/^96$/", "97"=>"/^97$/", "98"=>"/^98$/", "99"=>"/^99$/",
								"ON" => "/^100$/"
							)
					)
			),
			"149" => array(
					"DEFINITION" => array("confort","mode","internal_temp_multiplicator","internal_temp","wanted_temp_multiplicator","wanted_temp"),
					"TYPE" => array ("heating"),
					"VALUE" => array (
							"mode" => array(
								"CONFORT" => "/^0$/",
								"HORS_GEL" => "/^4$/",
								"REDUIT" => "/^3$/",
								"MANUEL" => "/^38$/"
							)
					)
			),
			"150" => array(
					"DEFINITION" => array("consigne_confort","mode","unknown","unknown","unknown","unknown"),
					"TYPE" => array ("heating"),
					"VALUE" => array (
							"mode" => array(
								"CONFORT_PRESENCE" => "/^2$/",
								"AUTO" => "/^0$/",
								"ECO_ABSENSE" => "/^16$/",
								"HORS_GEL" => "/^17$/"
							)
					)
			)
	);

	//Definition des function code
	public $OWN_FUNCTION_CODE = array(
			"48" => "INTERFACE",
			"49" => "VARIATOR",
			"50" => "automatism",
			"51" => "heating",
			"53" => "scene",
			"55" => "light"
	);
	
	//Definition des differents parametres
	public $OWN_PARAM_DEFINITION = array(
			"GO_TO_LEVEL_TIME" => array("level", "time"),
			"DIM_STEP" => array("step", "time"),
			"CONSIGNE" => array("consigne", "0x00"),
			"DEROGATION_CONSIGNE" => array("duration_consigne", "0x00"),
			"GO_TO_TEMPERATURE" => array("temp"),
			"CONFORT_JOUR_ROUGE" => array("consigne"),
			"COMMANDE_ECS" => array("ECS"),
			"SET_TEMP_CONFORT" => array("temp"),
			"INDICATION_TEMP_CONFORT" => array("temp"),
			"INFORMATION_TARIF" => array("tarif"),
			"INDEX_BASE" => array("1_index_base", "index_HP"),
			"INDEX_HC" => array("2_index_HC","index_HP", "index_HC"),
			"INDEX_BLEU" => array("3_index_bleu", "index_HP", "index_HC"),
			"INDEX_BLANC" => array("4_index_blanc", "index_HP", "index_HC"),
			"INDEX_ROUGE" => array("5_index_rouge", "index_HP", "index_HC"),
			"SET_TEMP_ECO" => array("temp"),
			"INDICATION_TEMP_ECO" => array("temp"),
			"SET_V3V_CONSIGNE" => array("temp_consigne", "temp_aux", "temp_security", "band", "forcing"),
			"BATTERY_WEAK" => array("battery_weak_indicator"),
			"CLOCK_SYNCHRONISATION" => array("hour", "minute", "second", "unknown", "unknown", "day", "month", "year"),
			"SET_CLOCK_TIME_PARAMETERS" => array("year", "month", "day", "hour", "minute", "second"),
			"INDICATION_CLOCK_TIME_PARAMETERS" => array("year", "month", "day", "hour", "minute", "second"),
			"OVERRIDE_FOR_TIME" => array("time"),
			"ACTION_FOR_TIME" => array("time"),
			"ACTION_IN_TIME" => array("time"),
			"INFO_SCENE_OFF" => array("family_type", "address"),
			"REQUEST_ID" => array("function_or_reference"),
			"DEVICE_DESCRIPTION_STATUS" => array("reference", "version", "function_code", "units_count"),
			"MEMORY_DATA" => array("family_type", "address", "preset_value", "frame_number", "message_length"),
			"MEMORY_DEPTH_INDICATION" => array("depth"),
			"EXTENDED_MEMORY_DATA" => array("family_type", "address", "preset_value", "frame_number"),
			"MEMORY_WRITE" => array("family_type", "address", "preset_value"),
			"UNIT_DESCRIPTION_STATUS" => array("unit_code", "unit_status"),
			"SET_COMMUNICATION_PARAMETER" => array("node_parameter"),
			"SET_CLOCK_TIME_PARAMETER" => array("year", "month", "day", "hour", "minute", "second"),
			"INDICATION_CLOCK_TIME_PARAMETER" => array("hour", "minute", "second", "unknown", "unknown", "day", "month", "year"),
			"OPEN_LEARNING" => array("function_code", "0xFF", "extended_command_code"),
			"ADDRESS_ERASE" => array("family_type", "address"),
			"OCCUPIED" => array("mode", "duration"),
			"UNOCCUPIED" => array("mode", "duration")
	);
	//Definition des differents type de contenu d'une trame
	public $OWN_TRAME_DEFINITION = array(
			"1" => array(
				"TYPE" => "light",
				"1" => "ON",
				"0" => "OFF",
				"38" => "DIM_STOP",
				"DIMENSION" => array(
					"#10_" => "DIM_STEP",
					"#1_" => "GO_TO_LEVEL_TIME"
				)
			), //1 light
			"2" => array(
				"TYPE" => "automatism",
				"0" => "MOVE_STOP",
				"1" => "MOVE_UP",
				"2" => "MOVE_DOWN"
			), //2 Automations
			"4" => array(
				"TYPE" => "heating",
				"50_" => "CONSIGNE",
				"51_" => "DEROGATION_CONSIGNE",
				"52" => "FIN_DEROGATION",
				"53_" => "GO_TO_TEMPERATURE",
				"54" => "ARRET",
				"55" => "FIN_ARRET",
				"56" => "STOP_FAN_SPEED",
				"57" => "LOW_FAN_SPEED",
				"58" => "HIGH_FAN_SPEED",
				"59_" => "CONFORT_JOUR_ROUGE",
				"DIMENSION" => array(
					"#40_" => "COMMANDE_ECS",
					"42_" => "INFORMATION_TARIF",
					"43" => "QUEL_INDEX",
					"43*1_" => "INDEX_BASE",
					"43*2_" => "INDEX_HC",
					"43*3_" => "INDEX_BLEU",
					"43*4_" => "INDEX_BLANC",
					"43*5_" => "INDEX_ROUGE",
					"#41_" => "SET_TEMP_CONFORT",
					"41" => "READ_TEMP_CONFORT",
					"41_" => "INDICATION_TEMP_CONFORT",
					"#44_" => "SET_TEMP_ECO",
					"44" => "READ_TEMP_ECO",
					"44_" => "INDICATION_TEMP_ECO",
					"#45_" => "SET_V3V_CONSIGNE",
					"45" => "CONSIGN_V3V_REQUEST"
				)
			), //4 Thermoregulation (Heating)
			"8" => array(
				"TYPE" => "security",
				"1" => "CONCIERGE_CALL",
				"19" => "LOCKER_CONTROL"
			), //8 Door Entry System
			"25" => array(
				"TYPE" => "scene",
				"11" => "ACTION",
				"16" => "STOP_ACTION",
				"17_" => "ACTION_FOR_TIME",
				"18_" => "ACTION_IN_TIME",
				"19_" => "INFO_SCENE_OFF"
			), //25 Scenarios
			"13" => array(
				"TYPE" => "MANAGEMENT",
				"23_" => "CLOCK_SYNCHRONISATION",
				"24_" => "LOW_BATTERY",
				"DIMENSION" => array(
					"22" => "READ_CLOCK_TIME_PARAMETER",
					"22_" => "INDICATION_CLOCK_TIME_PARAMETER",
					"#22_" => "SET_CLOCK_TIME_PARAMETER"
				)
			), //13 Management
			"14" => array(
				"TYPE" => "SPECIAL_COMMAND",
				"0_" => "OVERRIDE_FOR_TIME",
				"1" => "END_OF_OVERRIDE"
			), //14 Special commands
			"1000" => array(
				"TYPE" => "CONFIGURATION",
				"61_" => "OPEN_LEARNING",
				"62" => "CLOSE_LEARNING",
				"63_" => "ADDRESS_ERASE",
				"64" => "MEMORY_RESET",
				"65" => "MEMORY_FULL",
				"66" => "MEMORY_READ",
				"72" => "VALID_ACTION",
				"73" => "INVALID_ACTION",
				"68" => "CANCEL_ID",
				"69_" => "MANAGEMENT_CLOCK_SYNCHRONISATION",
				"70_" => "OCCUPIED",
				"71_" => "UNOCCUPIED",
				"DIMENSION" => array(
					"13" => "ANNOUNCE_ID",
					"51" => "DEVICE_DESCRIPTION_REQUEST",
					"51_" => "DEVICE_DESCRIPTION_STATUS",
					"13_" => "REQUEST_ID",
					"53_" => "EXTENDED_MEMORY_DATA",
					"56_" => "MEMORY_DEPTH_INDICATION",
					"52_" => "MEMORY_DATA",
					"55" => "UNIT_DESCRIPTION_REQUEST",
					"55_" => "UNIT_DESCRIPTION_STATUS",
					"#54_" => "MEMORY_WRITE",
					"#57_" => "SET_COMMUNICATION_PARAMETER"
				)
			)
	);
}

?>
