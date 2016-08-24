
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

 $('#bt_healthboxio').on('click', function () {
    $('#md_modal').dialog({title: "{{Santé Boxio}}"});
    $('#md_modal').load('index.php?v=d&plugin=boxio&modal=health').dialog('open');
});

$("#table_cmd").sortable({axis: "y", cursor: "move", items: ".cmd", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});
$("#table_mem").sortable({axis: "y", cursor: "move", items: ".mem", placeholder: "ui-state-highlight", tolerance: "intersect", forcePlaceholderSize: true});

$('body').delegate('.cmdAttr[data-l1key=type]','change',function(){
	var tr = $(this).closest('tr');
	if ( $(this).value() =="info") {
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=whatdim]').hide()
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=where]').hide()
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=DureeCmd]').show()
	} else  if ( $(this).value() =="action") {
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=whatdim]').show()
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=where]').show()
		tr.find('.cmdAttr[data-l1key=configuration][data-l2key=DureeCmd]').hide()
	}
});

function printEqLogic(_mem) {
	var versiondispo ='';
	var releasenotes ='';
	var color ='red';
	$.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/boxio/core/ajax/boxio.ajax.php", // url du fichier php
        data: {
            action: "checktemplate",
            id: $('.eqLogicAttr[data-l1key=logicalId]').value(),
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data_init) { // si l'appel a bien fonctionné
			if (data_init.state != 'ok') {
				$('#div_DashboardAlert').showAlert({message: data_init.result, level: 'danger'});
				return;
			}else{
				versiondispo = data_init.result['version'];
				releasenotes =  data_init.result['update'];
				$('#vdispo').append(versiondispo);
				$('#rnotes').append(releasenotes);
				if (data_init.result['versioninst'] < data_init.result['version']) {
					$('#vinst').css({'background-color': '#ff4343'});
				}else{
					$('#vinst').css({'background-color': '#e7e7e7'});
				}
			}
		}
	});
	
	
	$.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/boxio/core/ajax/boxio.ajax.php", // url du fichier php
        data: {
            action: "checkscenario",
            id: $('.eqLogicAttr[data-l1key=logicalId]').value(),
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data_init) { // si l'appel a bien fonctionné
			if (data_init.state != 'ok') {
				$('#div_DashboardAlert').showAlert({message: data_init.result, level: 'danger'});
				return;
			}else{
				$('#table_mem tbody').empty();
				for(i = 0; i < data_init.result.length; i++) {
					for(j = 0; j < data_init.result.length; j++) {
						var media = '';
					
						if (data_init.result[j]['frame_number']==i) {
							if (data_init.result[j]['media_listen'] == '0') { media='CPL';}
							else if (data_init.result[j]['media_listen'] == '1') {media='RF';}
							else if (data_init.result[j]['media_listen'] == '2') {media='IR';}
							
							var trm = '<tr>';
							trm += '<td>';
							trm += '<input class="eqLogicAttr form-control input-sm" value="' + data_init.result[j]['frame_number']  + '" readonly="true">';
							trm += '</td>';
							trm += '<td>';
							trm += '<input class="eqLogicAttr form-control input-sm" value="' + data_init.result[j]['id_legrand'] + '" readonly="true">';
							trm += '</td>';
							trm += '<td>';
							trm += '<input class="eqLogicAttr form-control input-sm" value="' + data_init.result[j]['unit'] + '" readonly="true">';
							trm += '</td>';
							trm += '<td>';
							trm += '<input class="eqLogicAttr form-control input-sm" value="' + data_init.result[j]['id_legrand_listen'] + '" readonly="true">';
							trm += '</td>';
							trm += '<td>';
							trm += '<input class="eqLogicAttr form-control input-sm" value="' + data_init.result[j]['unit_listen'] + '" readonly="true">';
							trm += '</td>';
							trm += '<td>';
							trm += '<input class="eqLogicAttr form-control input-sm" value="' + data_init.result[j]['value_listen'] + '" readonly="true">';
							trm += '</td>';
							trm += '<td>';
							trm += '<input class="eqLogicAttr form-control input-sm" value="' + media + '" readonly="true">';
							trm += '</td>';
							$('#table_mem tbody').append(trm);
							trm += '</tr>';
						}
					}
				}
				var trm = $('#table_mem tbody trm:last');
			}
		}
	});
}

function addCmdToTable(_cmd) {
    if (!isset(_cmd)) {
        var _cmd = {configuration: {}};
    }
	
	var selWhatDim = '<select style="width : 120px; margin-top : 5px;" class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="whatdim">';
	
	if (isset(_cmd.configuration.whatdim)) {
		selWhatDim += '<option value={"what":"' + _cmd.configuration.whatdim.what + '","dim":"' + _cmd.configuration.whatdim.dim + '","nom":"' + _cmd.configuration.whatdim.nom + '"}>{{' + _cmd.configuration.whatdim.nom + '}}</option>';
	} else {
		selWhatDim += '<option value="">Aucune</option>';
	}
	
	if ($('.eqLogicAttr[data-l1key=category][data-l2key=light]').value()==1) {
	
		
		//Commande What pour lumiere
		selWhatDim += '<option value={"what":"0","dim":"NULL","nom":"On"}>{{On}}</option>';
		selWhatDim += '<option value={"what":"1","dim":"NULL,"nom":"Off"}>{{Off}}</option>';
		selWhatDim += '<option value={"what":"38","dim":"NULL,"nom":"Dim_Stop"}>{{Dim_Stop}}</option>';
	
		//Commande Dimension pour lumiere
		selWhatDim += '<option value={"what":"NULL","dim":"1","nom":"Go_To_Level_Time"}>{{Go_To_Level_Time}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"10","nom":"Dim_Step"}>{{Dim_Step}}</option>';
	
	} else if ($('.eqLogicAttr[data-l1key=category][data-l2key=automatism]').value()==1) {
		
		//Commande What pour automatisme
		selWhatDim += '<option value={"what":"1","dim":"NULL","nom":"Move_Up"}>{{Move_Up}}</option>';
		selWhatDim += '<option value={"what":"2","dim":"NULL","nom":"Move_Down"}>{{Move_Down}}</option>';
		selWhatDim += '<option value={"what":"0","dim":"NULL","nom":"Move_Stop"}>{{Move_Stop}}</option>';
		
	} else if ($('.eqLogicAttr[data-l1key=category][data-l2key=heating]').value()==1)	{	
		//Commande What pour Chauffage
		selWhatDim += '<option value={"what":"50","dim":"NULL","nom":"Consigne"}>{{Consigne}}</option>';
		selWhatDim += '<option value={"what":"51","dim":"NULL","nom":"Derogation_Consigne"}>{{Derogation_Consigne}}</option>';
		selWhatDim += '<option value={"what":"52","dim":"NULL","nom":"Fin_Derogation"}>{{Fin_Derogation}}</option>';
		selWhatDim += '<option value={"what":"53","dim":"NULL","nom":"Go_To_Temperature"}>{{Go_To_Temperature}}</option>';
		selWhatDim += '<option value={"what":"54","dim":"NULL","nom":"Arret"}>{{Arret}}</option>';
		selWhatDim += '<option value={"what":"55","dim":"NULL","nom":"Fin_Arret"}>{{Fin_Arret}}</option>';
		selWhatDim += '<option value={"what":"56","dim":"NULL","nom":"Fan_Stop"}>{{Fan_Stop}}</option>';
		selWhatDim += '<option value={"what":"57","dim":"NULL","nom":"Low_Fan_Speed"}>{{Low_Fan_Speed}}</option>';
		selWhatDim += '<option value={"what":"58","dim":"NULL","nom":"High_Fan_Speed"}>{{High_Fan_Speed}}</option>';
		selWhatDim += '<option value={"what":"58","dim":"NULL","nom":"Comfort_Jour_Rouge"}>{{Comfort_Jour_Rouge}}</option>';
		
		//Commande Dimension pour Chauffage
		selWhatDim += '<option value={"what":"NULL","dim":"40","nom":"Commande_ECS"}>{{Commande_ECS}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"41","nom":"Set_Temp_Confort"}>{{Set_Temp_Confort}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"41","nom":"Read_Temp_Confort"}>{{Read_Temp_Confort}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"41","nom":"Indication_Temp_Confort"}>{{Indication_Temp_Confort}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"42","nom":"Information_Tarif"}>{{Information_Tarif}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"43","nom":"Index_Base"}>{{Index_Base}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"43","nom":"Index_HC"}>{{Index_HC}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"43","nom":"Index_Bleu"}>{{Index_Bleu}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"43","nom":"Index_Blanc"}>{{Index_Blanc}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"43","nom":"Index_Rouge"}>{{Index_Rouge}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"43","nom":"Quel_Index"}>{{Quel_Index}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"44","nom":"Set_Temp_Eco"}>{{Set_Temp_Eco}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"44","nom":"Read_Temp_Eco"}>{{Read_Temp_Eco}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"44","nom":"Indication_Temp_Eco"}>{{Indication_Temp_Eco}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"45","nom":"Set_V3V_consigne"}>{{Set_V3V_consigne}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"45","nom":"Consign_V3V_Request"}>{{Consign_V3V_Request}}</option>';
		
	} else if ($('.eqLogicAttr[data-l1key=category][data-l2key=security]').value()==1) {
		//Commande What pour Portier
		selWhatDim += '<option value={"what":"19","dim":"NULL","nom":"Locker_Control"}>{{Locker_Control}}</option>';
		selWhatDim += '<option value={"what":"1","dim":"NULL","nom":"Concierge_Call"}>{{Concierge_Call}}</option>';
	}
		
		//Commande What pour Commande Speciale
		selWhatDim += '<option value={"what":"24","dim":"NULL","nom":"Who13-Battery_Weak"}>{{Who13-Battery_Weak}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"23","nom":"Who13-Clock_Synchronization"}>{{Who13-Clock_Synchronization}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"22","nom":"Who13-Set_Clock_Time_Parameters"}>{{Who13-Set_Clock_Time_Parameters}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"22","nom":"Who13-Read_Clock_Time_Parameters"}>{{Who13-Read_Clock_Time_Parameters}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"22","nom":"Who13-Indication_Clock_Time_Parameters"}>{{Who13-Indication_Clock_Time_Parameters}}</option>';
		selWhatDim += '<option value={"what":"0","dim":"NULL","nom":"Who14-Override_for_Time"}>{{Who14-Override_for_Time}}</option>';
		selWhatDim += '<option value={"what":"1","dim":"NULL","nom":"Who14-End_Of_Override"}>{{Who14-End_Of_Override}}</option>';
		
		//Commande What pour Commande Scenario
		selWhatDim += '<option value={"what":"11","dim":"NULL","nom":"Who25-Action"}>{{Who25-Action}}</option>';
		selWhatDim += '<option value={"what":"17","dim":"NULL","nom":"Who25-Action_For_Time"}>{{Who25-Action_For_Time}}</option>';
		selWhatDim += '<option value={"what":"16","dim":"NULL","nom":"Who25-Stop_Action"}>{{Who25-Stop_Action}}</option>';
		selWhatDim += '<option value={"what":"18","dim":"NULL","nom":"Who25-Action_In_Time"}>{{Who25-Action_In_Time}}</option>';
		selWhatDim += '<option value={"what":"19","dim":"NULL","nom":"Who25-Info_Scene_Off"}>{{Who25-Info_Scene_Off}}</option>';
		
		//Commande What et Dimension pour Management et Configuration
		selWhatDim += '<option value={"what":"NULL","dim":"13","nom":"Who13-Announce_ID"}>{{Who13-Announce_ID}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"13","nom":"Who13-Request_ID"}>{{Who13-Request_ID}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"51","nom":"Who13-Device_Description_Request"}>{{Who13-Device_Description_Request}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"51","nom":"Who13-Device_Description_Status"}>{{Who13-Device_Description_Status}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"52","nom":"Who13-Memory_Data"}>{{Who13-Memory_Data}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"53","nom":"Who13-Extended_Memory_Data"}>{{Who13-Extended_Memory_Data}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"54","nom":"Who13-Memory_Write"}>{{Who13-Memory_Write}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"55","nom":"Who13-Unit_Description_Request"}>{{Who13-Unit_Description_Request}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"55","nom":"Who13-Unit_Description_Status"}>{{Who13-Unit_Description_Status}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"56","nom":"Who13-Memory_Depth_Indication"}>{{Who13-Memory_Depth_Indication}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"57","nom":"Who13-Set_Communication_Parameter"}>{{Who13-Set_Communication_Parameter}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"58","nom":"Who13-Set_Clock_Time_Parameter"}>{{Who13-Set_Clock_Time_Parameter}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"58","nom":"Who13-Read_Clock_Time_Parameter"}>{{Who13-Read_Clock_Time_Parameter}}</option>';
		selWhatDim += '<option value={"what":"NULL","dim":"58","nom":"Who13-Indication_Clocktime_Parameter"}>{{Who13-Indication_Clocktime_Parameter}}</option>';
		selWhatDim += '<option value={"what":"61","dim":"NULL","nom":"Who13-Open_Learning"}>{{Who13-Open_Learning}}</option>';
		selWhatDim += '<option value={"what":"62","dim":"NULL","nom":"Who13-Close_Learning"}>{{Who13-Close_Learning}}</option>';
		selWhatDim += '<option value={"what":"63","dim":"NULL","nom":"Who13-Address_Erase_Broadcast"}>{{Who13-Address_Erase_Broadcast}}</option>';
		selWhatDim += '<option value={"what":"63","dim":"NULL","nom":"Who13-Address_Erase_Unicast"}>{{Who13-Address_Erase_Unicast}}</option>';
		selWhatDim += '<option value={"what":"64","dim":"NULL","nom":"Who13-Memory_Reset"}>{{Who13-Memory_Reset}}</option>';
		selWhatDim += '<option value={"what":"65","dim":"NULL","nom":"Who13-Memory_Full"}>{{Who13-Memory_Full}}</option>';
		selWhatDim += '<option value={"what":"66","dim":"NULL","nom":"Who13-Memory_Read"}>{{Who13-Memory_Read}}</option>';
		selWhatDim += '<option value={"what":"67","dim":"NULL","nom":"Who13-Return_Factory_Config"}>{{Who13-Return_Factory_Config}}</option>';
		selWhatDim += '<option value={"what":"68","dim":"NULL","nom":"Who13-Cancel_ID}"}>{{Who13-Cancel_ID}}</option>';
		selWhatDim += '<option value={"what":"69","dim":"NULL","nom":"Who13-Clock_Synchronization"}>{{Who13-Clock_Synchronization}}</option>';
		selWhatDim += '<option value={"what":"70","dim":"NULL","nom":"Who13-Occupied"}>{{Who13-Occupied}}</option>';
		selWhatDim += '<option value={"what":"71","dim":"NULL","nom":"Who13-Unoccupied"}>{{Who13-Unoccupied}}</option>';
		selWhatDim += '<option value={"what":"72","dim":"NULL","nom":"Who13-Valid_Action"}>{{Who13-Valid_Action}}</option>';
		selWhatDim += '<option value={"what":"73","dim":"NULL","nom":"Who13-Invalid_Action"}>{{Who13-Invalid_Action}}</option>';
	selWhatDim += '</select>';
    
    var tr = '<tr class="cmd" data-cmd_id="' + init(_cmd.id) + '">';
    tr += '<td>';
    tr += '<div class="row">';
    tr += '<div class="col-sm-6">';
    tr += '<a class="cmdAction btn btn-default btn-sm" data-l1key="chooseIcon"><i class="fa fa-flag"></i> Icône</a>';
    tr += '<span class="cmdAttr" data-l1key="display" data-l2key="icon" style="margin-left : 10px;"></span>';
    tr += '</div>';
    tr += '<div class="col-sm-6">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="name">';
	tr += '</div>';
    tr += '</div>';
    //tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="value" style="display : none;margin-top : 5px;" title="La valeur de la commande vaut par défaut la commande">';
    //tr += '<option value="">Aucune</option>';
    //tr += '</select>';
    tr += '</td>';
    tr += '<td>';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="id" style="display : none;">';
    tr += '<span class="type" type="' + init(_cmd.type) + '">' + jeedom.cmd.availableType() + '</span>';
    tr += '<span class="subType" subType="' + init(_cmd.subType) + '"></span>';
   	tr += '' + selWhatDim;
    tr += '</td>';
	tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="unit">';
    tr += '</td>';
	tr += '<td>';
    tr += '<select class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="where">';
	if (isset(_cmd.configuration.where)) {
		tr += '<option value="' + _cmd.configuration.where + '">' + _cmd.configuration.where + '</option>';
	} else {
		tr += '<option value="">Aucune</option>';
	}
	tr += '<option value="Unicast">Unicast</option>';
    tr += '<option value="Broadcast">Broadcast</option>';
	tr += '<option value="Multicast">Multicast</option>';
	tr += '</select>';
    tr += '</td>';
    tr += '<td><input class="cmdAttr form-control input-sm" data-l1key="logicalId"  value="0">';
	tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="DureeCmd" placeholder="{{Durée de la commande (en seconde)}}">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateValue" placeholder="{{Valeur retour d\'état}}" style="margin-top : 5px;">';
    tr += '<input class="cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="returnStateTime" placeholder="{{Durée avant retour d\'état (min)}}" style="margin-top : 5px;">';
    tr += '</td>';
    tr += '<td>';
    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-l1key="isHistorized" data-size="mini" data-label-text="{{Historiser}}" /></span>';
    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-l1key="isVisible" data-size="mini" data-label-text="{{Afficher}}" checked/></span>';
    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-l1key="eventOnly"data-label-text="{{Evénement}}" data-size="mini" /></span> ';
    tr += '<span><input type="checkbox" class="cmdAttr bootstrapSwitch" data-l1key="display" data-label-text="{{Inverser}}" data-size="mini" data-l2key="invertBinary" /></span> ';
    tr += '<input style="width : 150px;" class="tooltips cmdAttr form-control input-sm" data-l1key="cache" data-l2key="lifetime" placeholder="Lifetime cache">';
    tr += '</td>';
    tr += '<td>';
    //tr += '<select class="cmdAttr form-control tooltips input-sm" data-l1key="configuration" data-l2key="updateCmdId" style="display : none;margin-top : 5px;" title="Commande d\'information à mettre à jour">';
    //tr += '<option value="">Aucune</option>';
    //tr += '</select>';
    //tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="updateCmdToValue" placeholder="Valeur de l\'information" style="display : none;margin-top : 5px;">';
    tr += '<input class="cmdAttr form-control tooltips input-sm" data-l1key="unite"  style="width : 100px;" placeholder="Unité" title="Unité">';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="minValue" placeholder="Min" title="Min"> ';
    tr += '<input class="tooltips cmdAttr form-control input-sm" data-l1key="configuration" data-l2key="maxValue" placeholder="Max" title="Max" style="margin-top : 5px;">';
    tr += '</td>';
    tr += '<td>';
    if (is_numeric(_cmd.id)) {
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="configure"><i class="fa fa-cogs"></i></a> ';
        tr += '<a class="btn btn-default btn-xs cmdAction" data-action="test"><i class="fa fa-rss"></i> Tester </a>';
    }
    tr += '<i class="fa fa-minus-circle pull-right cmdAction cursor" data-action="remove"></i></td>';
    tr += '</tr>';
    $('#table_cmd tbody').append(tr);
    var tr = $('#table_cmd tbody tr:last');
    jeedom.eqLogic.builSelectCmd({
        id: $(".li_eqLogic.active").attr('data-eqLogic_id'),
        filter: {type: 'info'},
        error: function (error) {
            $('#div_alert').showAlert({message: error.message, level: 'danger'});
        },
        success: function (result) {
            tr.find('.cmdAttr[data-l1key=value]').append(result);
            tr.find('.cmdAttr[data-l1key=configuration][data-l2key=updateCmdId]').append(result);
            tr.setValues(_cmd, '.cmdAttr');
            jeedom.cmd.changeType(tr, init(_cmd.subType));
        }
    });
}

$('#bt_updateMemory').on('click',function(){
	//$('#div_alert').showAlert({message: $('.eqLogicAttr[data-l1key=logicalId]').value(), level: 'danger'}); 
 $.ajax({// fonction permettant de faire de l'ajax
        type: "POST", // methode de transmission des données au fichier php
        url: "plugins/boxio/core/ajax/boxio.ajax.php", // url du fichier php
        data: {
            action: "updateMemory",
            id: $('.eqLogicAttr[data-l1key=id]').value(),
			idtrame: $('.eqLogicAttr[data-l1key=logicalId]').value(),
        },
        dataType: 'json',
        error: function (request, status, error) {
            handleAjaxError(request, status, error);
        },
        success: function (data) { // si l'appel a bien fonctionné
        if (data.state != 'ok') {
            $('#div_alert').showAlert({message: data.result, level: 'danger'});
            return;
        }
		var vars = getUrlVars();
        var url = 'index.php??v=d&m=boxio&p=boxio&';
        url += 'id=' +  $('.eqLogicAttr[data-l1key=id]').value();
		window.location.href = url;
	}
});
                         

});

