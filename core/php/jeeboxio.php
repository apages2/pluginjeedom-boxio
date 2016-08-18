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
require_once dirname(__FILE__) . "/../../../../core/php/core.inc.php";

if (!jeedom::apiAccess(init('apikey'))) {
	connection::failed();
	echo 'Clef API non valide, vous n\'êtes pas autorisé à effectuer cette action (jeeboxio)';
	die();
}

if (isset($_GET['test'])) {
	echo 'OK';
	die();
}

/*if (isset($argv)) {
     	 
        $argList = explode('=', $argv[1]);
        if (isset($argList[0]) && isset($argList[1])) {
			$_GET[$argList[0]] = $argList[1];
        }
    
}*/

if (isset($_GET['trame'])) {


	$trame = str_replace('Y', '*', $_GET['trame']);
	$trame = str_replace('Z', '#', $trame);
	log::add ('boxio','event','Receive to Jeedom : '.$trame);
	
	$tramedecrypt=boxio::decrypt_trame($trame);
	log::add('boxio', 'debug', 'Jeeboxio_Equipement : ' . print_r($tramedecrypt, true));
	
	foreach ($tramedecrypt as $key => $value) {
				if (is_null($value)) {
					$tramedecrypt[$key] = "NULL";
				} else {
					$tramedecrypt[$key] = $value;
				}
	}
	
	
	if ($tramedecrypt["format"]=="ACK" || $tramedecrypt["format"]=="NACK"){
		log::add ('boxio', 'debug', "Jeeboxio_Trame non interprétée");
	}
	
	if ($tramedecrypt["format"]!="ACK" && $tramedecrypt["format"]!="NACK"){			
		$boxio = boxio::byLogicalId($tramedecrypt["id"], 'boxio');
		if (!is_object($boxio)) {
				log::add('boxio', 'debug', 'Jeeboxio_Aucun équipement trouvé pour : ' . $tramedecrypt["id"]. " Création de l'equipement\n");
				$boxio = boxio::createFromDef($tramedecrypt);
				if (!is_object($boxio)) {
					log::add('boxio', 'debug', 'Jeeboxio_Aucun équipement trouvé pour : ' . $tramedecrypt["id"]." Erreur lors de la création de l'équipement\n" );
					die();
				}
		} else if (is_object($boxio) && $tramedecrypt['dimension']=="DEVICE_DESCRIPTION_STATUS") {
			$val=boxioCmd::get_params($tramedecrypt['dimension'], $tramedecrypt['param'], 'array');
			log::add('boxio', 'debug', "Jeeboxio_DEVICE_DESCRIPTION_STATUS : Référence : " . $val['reference'] . "\n Version : " . $val['version'] . "\n Function Code : " . $val['function_code'] . "\n Nbr units : " . $val['units_count'] . "\n");
			if ($boxio->getConfiguration('applyDevice')==''){
				$boxio->setConfiguration('device', $val['reference'] . "::00");
				$boxio->applyModuleConfiguration();
				$boxio->setConfiguration('commentaire', "Référence :" . $val['reference'] . "\nVersion :" . $val['version'] . "\nFunction Code :" . $val['function_code'] . "\nNbr units :" . $val['units_count']);
				$boxio->save();
			}
			if ($boxio->getConfiguration('memorydepth')=='') {
				$mem=boxio::checkMemory($tramedecrypt["id"],2);
			}
		}	else if (is_object($boxio) && $tramedecrypt['dimension']=="UNIT_DESCRIPTION_STATUS") {
			$val=boxioCmd::get_params($tramedecrypt['dimension'], $tramedecrypt['param'], 'array');
			log::add('boxio', 'debug', "Jeeboxio_UNIT_DESCRIPTION_STATUS : Params : " . $tramedecrypt['param'] ."\n");
			
			foreach($val as $key => $value){
				log::add('boxio', 'debug', "Jeeboxio_UNIT_DESCRIPTION_STATUS_PARAMS : Params ".$key." : " . $value . "\n");
			}
			boxio::updateRequestStatus($tramedecrypt);
		} else if (is_object($boxio) && $tramedecrypt['dimension']=="MEMORY_DEPTH_INDICATION"){
			log::add('boxio', 'debug', "Jeeboxio_MEMORY_DEPTH_INDICATION \n");
			boxio::updateMemoryDepth($tramedecrypt);	
			
		} else if (is_object($boxio) && $tramedecrypt['dimension']=="EXTENDED_MEMORY_DATA"){
			log::add('boxio', 'debug', "Jeeboxio_EXTENDED_MEMORY_DATA \n");
			boxio::updateMemory($tramedecrypt);
		
		} else if (is_object($boxio) && $tramedecrypt['format']=="BUS_COMMAND"){
			log::add('boxio', 'debug', "Jeeboxio_BUS_COMMAND \n");
			boxio::updateStatus($tramedecrypt);
			log::add('boxio', 'event', "Jeeboxio_update status");
			
		} else if (is_object($boxio) && $tramedecrypt['format']=="STATUS_REQUEST"){
			log::add('boxio', 'debug', "Jeeboxio_STATUS_REQUEST, Aucun action effectuée \n");
			//boxio::updateRequestStatus($tramedecrypt);
		}else if (is_object($boxio) && $tramedecrypt['format']=="DIMENSION_SET"){
			log::add('boxio', 'debug', "Jeeboxio_DIMENSION_SET, mise a jour des statuts \n");
			boxio::updateStatus($tramedecrypt);
		}else if (is_object($boxio) && $tramedecrypt['format']=="DIMENSION_REQUEST" && isset($tramedecrypt['param'])){
			log::add('boxio', 'debug', "Jeeboxio_DIMENSION_REQUEST avec Parametres, mise a jour des statuts \n");
			boxio::dimensionRequestStatus($tramedecrypt);
		}
	}
}