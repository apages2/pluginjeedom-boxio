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
include_file('core', 'authentification', 'php');
if (!isConnect()) {
    include_file('desktop', '404', 'php');
    die();
}
?>
<form class="form-horizontal">
    <fieldset>
        <legend><i class="fa fa-list-alt"></i> {{Général}}</legend>
		<?php if (config::byKey('jeeNetwork::mode') == 'master') {?>
			<div class="form-group">
				<label class="col-lg-4 control-label">{{Mettre à jour les templates des modules automatiquement}}</label>
				<div class="col-lg-3">
					<input type="checkbox" class="configKey" data-l1key="auto_updateConf"/>
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">{{Bannir les IDs suivants}}</label>
				<div class="col-lg-8">
					<textarea class="configKey form-control" data-l1key="banboxioId" rows="3"/>
				</div>
			</div>
		<?php }
		?>
		<legend><i class="fa fa-cog"></i>  {{Gestion avancée}}</legend>
			<div class="form-group">
				<label class="col-lg-4 control-label">{{Options avancées}}</label>
				<div class="col-lg-3">
					<a class="btn btn-success" id="bt_syncconfigBoxio"><i class="fa fa-refresh"></i> {{Update Templates Modules}}</a>
				</div>
			</div>
        <legend>{{Démon local}}</legend>
			<div class="form-group">
				<label class="col-sm-4 control-label">{{Port Boxio}}</label>
				<div class="col-sm-4">
					<select class="configKey form-control" data-l1key="port">
						<option value="none">{{Aucun}}</option>
						<option value="auto">{{Auto}}</option>
						<?php
							foreach (jeedom::getUsbMapping() as $name => $value) {
								echo '<option value="' . $name . '">' . $name . ' (' . $value . ')</option>';
							}
							$usbMapping = array();
							foreach (ls('/dev/', 'ttyACM*') as $usb) {
								$vendor = '';
								$model = '';
								foreach (explode("\n", shell_exec('/sbin/udevadm info --name=/dev/' . $usb . ' --query=all')) as $line) {
									if (strpos($line, 'E: ID_MODEL=') !== false) {
										$model = trim(str_replace(array('E: ID_MODEL=', '"'), '', $line));
									}
									if (strpos($line, 'E: ID_VENDOR=') !== false) {
										$vendor = trim(str_replace(array('E: ID_VENDOR=', '"'), '', $line));
									}
								}
								if ($vendor == '' && $model == '') {
									$usbMapping['/dev/' . $usb] = '/dev/' . $usb;
								} else {
									$name = trim($vendor . ' ' . $model);
									$number = 2;
									while (isset($usbMapping[$name])) {
										$name = trim($vendor . ' ' . $model . ' ' . $number);
										$number++;
									}
									$usbMapping[$name] = '/dev/' . $usb;
								}
								echo '<option value="' . $usbMapping[$name] . '">' . $name . ' (' . $usbMapping[$name] . ')</option>';
							}
							foreach (ls('/dev/', 'tty*') as $value) {
								echo '<option value="/dev/' . $value . '">/dev/' . $value . '</option>';
							}
						?>
					</select>
				</div>
			</div>
            <div class="form-group">
				<label class="col-sm-4 control-label">{{Enregistrer tous les messages (cela peut ralentir le système)}}</label>
				<div class="col-sm-1">
				<input type="checkbox" class="configKey" data-l1key="enableLogging" />
				</div>
				<div class="col-sm-7">
                <a class="btn btn-default" id="bt_logBoxioMessage"><i class="fa fa-file-o"></i> {{Voir les messages}}</a>
				</div>
			</div>
			<div class="form-group">
				<label class="col-lg-4 control-label">{{Vitesse du port}}</label>
				<div class="col-lg-2">
                <select class="configKey form-control" data-l1key="serial_rate" >
                    <option value="115200">115200</option>
					<option value="38400">38400</option>
					<option value="19200">19200</option>
                </select>    
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-4 control-label">{{Port socket interne (modification dangereuse : entrer la même valeur sur tous les Jeedom déportés avec Boxio)}}</label>
				<div class="col-sm-2">
					<input class="configKey form-control" data-l1key="socketport" value='55002' />
				</div>
			</div>
    </fieldset>
</form>

<?php
if (config::byKey('jeeNetwork::mode') == 'master') {
	foreach (jeeNetwork::byPlugin('boxio') as $jeeNetwork) {
		?>
        <form class="form-horizontal slaveConfig" data-slave_id="<?php echo $jeeNetwork->getId(); ?>">
            <fieldset>
                <legend>{{Démon sur l'esclave}} <?php echo $jeeNetwork->getName() ?></legend>
                <div class="form-group">
                    <label class="col-lg-4 control-label">{{Port Boxio}}</label>
                    <div class="col-lg-4">
                        <select class="slaveConfigKey form-control" data-l1key="port">
                            <option value="none">{{Aucun}}</option>
                            <option value="auto">{{Auto}}</option>
                            <?php
foreach ($jeeNetwork->sendRawRequest('jeedom::getUsbMapping') as $name => $value) {
			echo '<option value="' . $name . '">' . $name . ' (' . $value . ')</option>';
		}
		?>
						</select>
					</div>
				</div>
				<div class="form-group">
					<label class="col-lg-4 control-label">{{Enregistrer tous les messages, cela peut ralentir le système}}</label>
					<div class="col-lg-1">
					<input type="checkbox" class="slaveConfigKey" data-l1key="enableLogging" />
					</div>
					<div class="col-lg-7">
					<a class="btn btn-default bt_logBoxioMessage"><i class="fa fa-file-o"></i> {{Voir les messages}}</a>
					</div>
				</div>
				<div class="form-group expertModeVisible">
				<label class="col-lg-4 control-label">{{Port socket interne (modification dangereuse, doit etre le meme surtout les esclaves)}}</label>
					<div class="col-lg-2">
					<input class="slaveConfigKey form-control" data-l1key="socketport" value='55002' />
					</div>
				</div>
				<div class="form-group">
					<label class="col-sm-4 control-label">{{Vitesse du port}}</label>
					<div class="col-sm-2">
						<select class="slaveConfigKey form-control" data-l1key="serial_rate" >
							<option value="115200">115200</option>
							<option value="38400">38400</option>
							<option value="19200">19200</option>
						</select>
					</div>
				</div>
			</fieldset>
		</form>

<?php
}
}
?>


<script>
    $('.bt_logBoxioMessage').on('click', function () {
		var slave_id = $(this).closest('.slaveConfig').attr('data-slave_id');
		$('#md_modal').dialog({title: "{{Log des messages Boxio}}"});
		$('#md_modal').load('index.php?v=d&plugin=boxio&modal=show.log&slave_id='+slave_id).dialog('open');
	});

    $('#bt_logBoxioMessage').on('click', function () {
        $('#md_modal').dialog({title: "{{Log des messages Boxio}}"});
        $('#md_modal').load('index.php?v=d&plugin=boxio&modal=show.log').dialog('open');
    });
	
	$('#bt_fileconfigBoxio').on('click', function () {
		$('#md_modal').dialog({title: "{{Configuration}}"});
		$('#md_modal').load('index.php?v=d&plugin=boxio&modal=config').dialog('open');
	});
	
	$('#bt_syncconfigBoxio').on('click',function(){
		bootbox.confirm('{{Etes-vous sûr de vouloir télécharger les dernièrs templates des modules ?}}', function (result) {
			if (result) {
				$('#md_modal').dialog({title: "{{Téléchargement des configurations}}"});
				$('#md_modal').load('index.php?v=d&plugin=boxio&modal=syncconf.boxio').dialog('open');
			}
		});
	});
</script>