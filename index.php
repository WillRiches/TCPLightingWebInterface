<?php
include 'include.php';
pageHeader('TCP Lighting Controller');

/*
 * TCP Ligthing Web UI
 */

if (TOKEN != '') {
	//Get State of System Data
	$CMD = 'cmd=GWRBatch&data=<gwrcmds><gwrcmd><gcmd>RoomGetCarousel</gcmd><gdata><gip><version>1</version><token>'.TOKEN.'</token><fields>name,image,imageurl,control,power,product,class,realtype,status</fields></gip></gdata></gwrcmd></gwrcmds>';
	$result = getCurlReturn($CMD);

	$array = xmlToArray($result);

	//check if token is expired
	if (!isset($array['gwrcmd'])) {
		echo '<p>GWR Command not returned, this likely indicates your token is expired, or invalid.<p>';
		echo '<p>Remove token and try regenerating a new one.</p>';

		pageFooter();
		exit;
	}

	if (isset( $array['gwrcmd']['gdata']['gip']['room'] )) {
		$DATA = $array['gwrcmd']['gdata']['gip']['room'];
	} else {
			echo 'No Room Data';
			pa( $array );
			$DATA =  array();
			pageFooter();
			exit;
	}

	$deviceCount = 0;

	if (sizeof($DATA) > 0 ) {
		if (isset( $DATA['rid'] )) { $DATA = array( $DATA ); }

		foreach ($DATA as $room) {
			if (isset($room['rid'] )) {
				echo '<div data-room-id="'. $room['rid'].'">';
				echo '<div class="room-color room-color-'.$room['colorid'].'"><div class="room-name">'.$room['name'].'</div><a class="info" href="info.php?rid='.$room['rid'].'"><span class="fa fa-edit"></span></a></div>';
				echo '<div>';


				$DEVICES = [];

				if (!is_array($room['device'])) {

				} else {
					$device = (array)$room['device'];
					if (isset($device['did'])) {
						//item is singular device
						$DEVICES[] = $room['device'];
						$deviceCount++;
					} else {

						for ($x = 0; $x < sizeof($device); $x++ ) {
							if (isset($device[$x]) && is_array($device[$x]) && ! empty($device[$x])) {
								$DEVICES[] = $device[$x];
								$deviceCount++;
							}
						}
					}
				}

				if (sizeof($DEVICES) > 0 ) {
					echo '<div class="devices">';
						echo '<div class="room-devices">';
						$unplugged = 0;
						$roomBrightness = 0;
						$roomDevices = 0;
						foreach ($DEVICES as $device) {

							echo '<div class="'.((isset($device['offline']) && $device['offline'] == 1) ? 'unplugged' : 'plugged' ).' device '.($device['state'] == 1 ? 'light-on' : 'light-off' ).' '.($device['prodtype'] == 'Light Fixture' ? 'light-fixture' : '' ).'" data-device-id="'.$device['did'].'">'; //power > 0 then enabled
								if (isset($device['offline']) && $device['offline'] == 1) { $unplugged++; }

								if (isset($device["other"]) && isset( $device["other"]["rcgroup"] ) && $device["other"]["rcgroup"] != null &&  $device["other"]["rcgroup"]  <= 4 ) {
									echo '<div class="control-button">'.$device["other"]["rcgroup"].'</div>';
								}

								echo '<p class="device-name"><b>'.$device['name'].'</b> <a href="info.php?did='.$device['did'].'"><span class="fa fa-edit"></span></a></p>';
								echo '<p class="on-off-buttons"><button data-device-id="'.$device['did'].'" class="onOffDeviceToggleButton btn btn-primary" data-toggle="on">On</button> <button data-device-id="'.$device['did'].'" class="onOffDeviceToggleButton btn btn-dark" data-toggle="off">Off</button></p>';
								echo '<div class="clear"></div>';
								echo '<p>Brightness:</p>';
								echo '<div class="device-slider" data-value="'.(isset($device['level']) ? $device['level'] : 100).'" data-device-id="'. $device["did"].'"></div>';
							echo '</div>';
							$roomBrightness += (isset($device['level']) ? $device['level'] : 100);
							$roomDevices++;

						}
						echo '</div>';

					echo '</div>';
					echo '</div>';

				} else {
					echo 'No devices?';
					pa( $room );
				}
				// dont render if not more than 1 device, or all devices unplugged
				if (sizeof($DEVICES) > 1 && $unplugged != sizeof($DEVICES)) {
					echo '<div class="room-controls">';
						echo 'Room Brightness: <div class="room-slider" data-value="'.($roomBrightness/$roomDevices).'" data-room-id="'. $room["rid"].'"></div>';
						echo 'Room <button data-room-id="'. $room["rid"].'" class="onOffToggleButton buttonOn btn btn-primary">On</button> | <button data-room-id="'. $room["rid"].'" class="onOffToggleButton buttonOff btn btn-dark">Off</button>';
					echo '</div>';
				}
				echo '</div>';
			}
		}
	}


	if ($deviceCount > 0 ) {
		echo '<div>';
			echo '<div class="room-color" style="background-color: #ccc;"><div class="room-name">Home Controller</div></div>';
			echo '<div class="home-devices">';
				echo '<p><img src="css/images/scene/home.png"><br /><br /></p>';
				echo '<p><button data-device-id="all" class="onOffHouseToggleButton buttonOn">On</button> | <button data-device-id="all" class="onOffHouseToggleButton buttonOff">Off</button></p>';
			echo '</div>';

			echo '<div class="home-controls">';
				echo '<p>Brightness:</p>';
				echo '<div class="house-slider" data-device-id="all"></div>';
			echo '</div>';
		echo '</div>';
	}


	echo '<div id="scenes">';
		echo '<div class="room-color" style="background-color: #ccc;"><div class="room-name">Scenes</div></div>';

		$CMD = "cmd=SceneGetListDetails&data=<gip><version>1</version><token>".TOKEN."</token><bigicon>1</bigicon></gip>";
		$result = getCurlReturn($CMD);
		$array = xmlToArray($result);
		$scenes = $array['scene'];
		if (is_array($scenes)) {
			for ($x = 0; $x < sizeof($scenes); $x++) {
				?>
				<div class="scene-container" id="scene-id-<?php echo $scenes[$x]['sid']; ?>">
                	<div class="scene-info"><a href="scenescreatedit.php?SID=<?php echo $scenes[$x]['sid']; ?>"><span class="fa fa-edit"></span></a></div>
					<p><b><?php echo $scenes[$x]["name"]; ?></b></p>
					<p><img src="css/<?php echo $scenes[$x]['icon']; ?>" /> <?php echo ($scenes[$x]['active'] == 0 ? '&#10074;&#10074; (deactivated)' : '')  ?></p>
					<p>
                        <button data-scene-mode="run" data-scene-id="<?php echo $scenes[$x]['sid']; ?>" class="runScene" <?php echo $scenes[$x]['active'] == 0 ? 'disabled' : ''; ?>>Run Scene</button>
                        <button data-scene-mode="off" data-scene-id="<?php echo $scenes[$x]['sid']; ?>" class="runScene" <?php echo $scenes[$x]['active'] == 0 ? 'disabled' : ''; ?>>Scene Devices Off</button>
                        <button data-scene-mode="on" data-scene-id="<?php echo $scenes[$x]['sid']; ?>" class="runScene" <?php echo $scenes[$x]['active'] == 0 ? 'disabled' : ''; ?>>Scene Devices On</button>
                    </p>
				</div>
				<?php
			}

			?>
			<div class="scene-container" id="scene-id--1">
				<div class="scene-info"><a href="scenescreatedit.php?SID=-1"><span class="fa fa-edit"></span></a></div>
				<p><a href="scenescreatedit.php?SID=-1"><b>Create New</b></a></p>
				<p><img src="css/images/scene/bolt.png" /></p>
			</div>
			<?php
			echo '<div class="clear"></div>';
	}
	echo '</div>';


} else {
	echo '<div style="padding:20px;">';
	echo '<h2>If you are seeing this, you haven\'t generated your token yet.</h2>';

	$CMD = "cmd=GWRLogin&data=<gip><version>1</version><email>".USER_EMAIL."</email><password>".USER_PASSWORD."</password></gip>";

	$result = getCurlReturn($CMD);
	$tokenArray = xmlToArray($result);


	if (!isset($tokenArray['token'])) {
		echo '<p>Could not fetch token. Ensure you have the correct IP for your bridge and that you have hit the <b>sync</b> button before running this.</p>';

		echo '<p><img src="/css/images/syncgateway.png" /></p>';
	} else {
        echo '<p>Result Token: <b>'.$tokenArray['token'].'</b> save this token in the TOKEN definition in the include.php file.</p><p>Full response: | '.htmlentities($result).' | - note this has been turned to html entities for legibility.<p>';
        echo '<p><img src="/css/images/syncgateway.png" /></p>';
	}
	echo '</div>';
}

pageFooter();
