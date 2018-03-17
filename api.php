<?php

include 'include.php';

global $REMOTE_IP;

if (REQUIRE_EXTERNAL_API_PASSWORD && ! isLocalIPAddress($REMOTE_IP)) {
	$password = isset($_REQUEST['password']) ? $_REQUEST['password'] : '';
	if ($password != EXTERNAL_API_PASSWORD) {
		echo 'Invalid API Password';
		APILog('Attempted API Access, invalid or no password provided.');

		exit;
	}
}

if (RESTRICT_EXTERNAL_PORT == 1 && ! isLocalIPAddress($REMOTE_IP)) {
	if ($_SERVER['SERVER_PORT'] != EXTERNAL_PORT) {
		echo 'Invalid Port';
		APILog('Attempted API Access on invalid port');

		exit;
	}
}

$function = isset($_REQUEST['fx']) ? $_REQUEST['fx'] : ''; //Toggle or Brightness
$type = isset($_REQUEST['type']) ? $_REQUEST['type'] : ''; //Device or Room
$UID = isset($_REQUEST['uid']) ? $_REQUEST['uid'] : '';	//DeviceID or Room ID
$val = isset($_REQUEST['val']) ? $_REQUEST['val'] : '';	//DeviceID or Room ID

APILog('- Function: '.$function.' Type: ' . $type . ' ID : ' . $UID . ' Value: ' . $val);

$val = $val < 0 ? 0 : $val;
$val = $val > 100 ? 100 : $val;

/**
 * Facilitate device dimming on / off
 *
 * @param $deviceInfoArray
 * @param $onOff
 *
 * @return mixed
 */
function dimOnOff($deviceInfoArray, $onOff)
{
	$baseCommand = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>' . TOKEN . '</token><did>' . $deviceInfoArray['did'] . '</did>';

	// Check state
	if ($onOff == 1) {
		// Fade on
        //Todo: Should this be value 1?
        getCurlReturn($baseCommand . '<value>0</value><type>level</type>' . '</gip>');

		// Set on
        getCurlReturn($baseCommand . '<value>1</value>' . '</gip>');
	} else {
        // Fade off
        getCurlReturn($baseCommand . '<value>0</value><type>level</type>' . '</gip>');

        // Set off
        getCurlReturn($baseCommand . '<value>0</value>' . '</gip>');
	}

    // Dim to original level
    return getCurlReturn($baseCommand . '<value>' . $deviceInfoArray['level'] . '</value><type>level</type>' . '</gip>');
}

/**
 * @param $UID
 *
 * @return null
 */
function deviceOn($UID)
{
	$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$UID.'</did><value>1</value></gip>';

	getCurlReturn($CMD);

	return null;
}

if ($function != '' && $type != '' && $UID != '' && $val != '') {
	$DEVICES = getDevices();

	if ($type == 'device') {
		$THE_DEVICE = null;
		if (sizeof($DEVICES) > 0) {
			foreach ($DEVICES as $device) {
				if ($device['did'] == $UID ) {
					$THE_DEVICE = $device;

					break;
				}
			}
		}

		switch ($function) {
			case 'toggle':
				//$val = 1 | 0 - on | off
				$val = ($val > 0) ? 1 : 0;

				if (($val == 1 && FORCE_FADE_ON) || ($val == 0 && FORCE_FADE_OFF)) {
                    dimOnOff($THE_DEVICE, $val);

                    echo json_encode([
                        'toggle' => $val,
                        'device' => $UID,
                        'return' => 'DimFade'
                    ]);
				} else {
					$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>' . TOKEN . '</token><did>' . $UID . '</did><value>' . $val . '</value></gip>';

					echo json_encode([
					    'toggle' => $val,
                        'device' => $UID,
                        'return' => xmlToArray(getCurlReturn($CMD))
                    ]);
				}

			    break;
			case 'dim':
				if ($THE_DEVICE['state'] == 0) {
					//turn light on
					deviceOn($UID);
				}

				$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$UID.'</did><value>'.$val.'</value><type>level</type></gip>';

				echo json_encode([
				    'dim' => $val,
                    'device' => $UID,
                    'return' => xmlToArray(getCurlReturn($CMD))
                ]);

			    break;
			case 'dimby':
				$darkenTo = $THE_DEVICE['level'] - $val;

				if ($darkenTo <= 0) {
				    $darkenTo = 0;
				}

				$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$UID.'</did><value>'.$darkenTo.'</value><type>level</type></gip>';

				echo json_encode([
				    'dimby' => $val,
                    'device' => $UID,
                    'return' => xmlToArray(getCurlReturn($CMD))
                ]);

			    break;
			case 'brightenby':
				if ($THE_DEVICE['state'] == 0) {
					//turn light on
					deviceOn($UID);
				}

				$brightenTo = $THE_DEVICE['level'] + $val;
				if ($brightenTo > 100) {
				    $brightenTo = 100;
				}

				$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$UID.'</did><value>'.$brightenTo.'</value><type>level</type></gip>';

				echo json_encode([
				    'brightenby' => $val,
                    'device' => $UID,
                    'return' => xmlToArray(getCurlReturn($CMD))
                ]);

			    break;
			default:
			    echo json_encode([
			        'error' => 'unknown function, required: toggle | dim'
                ]);
		}
	} else if ($type == 'room') {
		$THE_ROOM = null;

		//Get State of System Data
		$CMD = 'cmd=GWRBatch&data=<gwrcmds><gwrcmd><gcmd>RoomGetCarousel</gcmd><gdata><gip><version>1</version><token>'.TOKEN.'</token><fields>name,image,imageurl,control,power,product,class,realtype,status</fields></gip></gdata></gwrcmd></gwrcmds>&fmt=xml';
		$array = xmlToArray(getCurlReturn($CMD));
		$DATA = $array['gwrcmd']['gdata']['gip']['room'];

        foreach ($DATA as $room) {
            if (is_array($room['device']) && $room['rid'] == $UID) {
                $device = (array)$room['device'];

                //Todo: Find the impact of this previously being (incorrectly) $THE_ROOM == $room
                $THE_ROOM = $room;

                if (isset($device['did'])) {
                    $THE_ROOM['brightness'] = $room['device']['level'];
                } else {
                    //Todo: Convert this to foreach?
                    for ($x = 0; $x < sizeof($device); $x++) {
                        if (isset($device[$x]) && is_array($device[$x]) && ! empty($device[$x])) {
                            $THE_ROOM['brightness'] += $device[$x]['level'];
                        }
                    }

                    $THE_ROOM['brightness'] = $THE_ROOM['brightness'] / sizeof($device);
                }

                break;
            }
        }

		if ($function == 'toggle') {
			//turn on | off
			$tval = ($val > 0) ? 1 : 0;
			if (($tval == 1 && FORCE_FADE_ON) || ($tval == 0 && FORCE_FADE_OFF)) {

				$DEVICES = [];
				foreach ($DATA as $room) {
					if (is_array($room['device']) && $room['rid'] == $UID) {
						$device = (array)$room['device'];

						if (isset($device['did'])) {
							//item is singular device
							$room['device']['roomID'] = $room['rid'];
							$room['device']['roomName'] = $room['name'];
							$DEVICES[] = $room['device'];
						} else {
                            //Todo: Convert this to foreach?
							for ($x = 0; $x < sizeof($device); $x++) {
								if (isset($device[$x]) && is_array($device[$x]) && ! empty($device[$x])) {
									$device[$x]['roomID'] = $room['rid'];
									$device[$x]['roomName'] = $room['name'];
									$DEVICES[] = $device[$x];
								}
							}
						}
					}
				}

				// Get current room lighting value??
				if (sizeof($DEVICES) > 0) {
					$dcount = 0;
					$roomBrightness = 0;

					foreach ($DEVICES as $device) {
						if ($device['roomID'] == $UID) {
							$roomBrightness += $device['level'];
							$dcount++;
						}
					}

					if ($dcount > 0) {
						$roomBrightness = ($roomBrightness/$dcount);
					} else {
						$roomBrightness = 100;
					}
				} else {
					$roomBrightness = 100;
				}

				$result = '';

				if ($tval == 1) {
					//fade on --ensure off?
					$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>0</value><type>level</type></gip>';
					$result = getCurlReturn($CMD);
					//turn on
					$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>1</value></gip>';
					$result.= getCurlReturn($CMD);
					//fade to 100
					$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>'.$roomBrightness.'</value><type>level</type></gip>';
					$result.= getCurlReturn($CMD);

				} else {
					//fade off -- ensure already off
					$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>0</value><type>level</type></gip>';
					$result = getCurlReturn($CMD);
					//turn off
					$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>0</value></gip>';
					$result.= getCurlReturn($CMD);
					//reset brightness to 100
					$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>'.$roomBrightness.'</value><type>level</type></gip>';
					$result.= getCurlReturn($CMD);
				}


				$val = $roomBrightness;
				$array = $result;

			} else {
				$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>'.$val.'</value></gip>';
				$result = getCurlReturn($CMD);
				$array = xmlToArray($result);
			}



		} else if ($function == 'dim') {

			//turn room on if by chance it is off
			$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>1</value></gip>';
			$result = getCurlReturn($CMD);
			$array = xmlToArray($result);


			// dim
			$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>'.$val.'</value><type>level</type></gip>';

			$result = getCurlReturn($CMD);
			$array = xmlToArray($result);
		} else if ($function == 'dimby' ) {

			$roomBrightness = $THE_ROOM['brightness'];
			$roomBrightness -= $val;
			if ($roomBrightness < 0 ) { $roomBrightness = 0; }


			$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>'.$roomBrightness.'</value><type>level</type></gip>';

			$result = getCurlReturn($CMD);
			$array = xmlToArray($result);
		} else if ($function == 'brightenby' ) {
			$roomBrightness = $THE_ROOM['brightness'];
			$roomBrightness += $val;

			if ($roomBrightness > 100 ) {
			    $roomBrightness = 100;
			}

			$CMD = 'cmd=RoomSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><rid>'.$UID.'</rid><value>'.$roomBrightness.'</value><type>level</type></gip>';

			$result = getCurlReturn($CMD);
			$array = xmlToArray($result);
		}

		echo json_encode([
		    'room' => $UID,
            'fx' => $function,
            'val' => $val,
            'return' => $array
        ]);

	} else if ($type == 'all') {
		if (sizeof($DEVICES) > 0 ) {
			foreach ($DEVICES as $device) {
				if ($function == 'toggle' ) {
					//only toggle if it needs to be toggled
					if (isset($device['state']) && $device['state']  != $val ) {
						$tval = ($val > 0) ? 1 : 0;

						if (($tval == 1 && FORCE_FADE_ON) || ($tval == 0 && FORCE_FADE_OFF)) {
							$result = dimOnOff( $device, $tval );
						} else {
							$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$device['did'].'</did><value>'.$val.'</value></gip>';
							$result = getCurlReturn($CMD);
						}
					}
				} else if ($function == 'dim') {
					$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$device['did'].'</did><value>'.$val.'</value><type>level</type></gip>';
					$result = getCurlReturn($CMD);

					//turn light on if it is not in order to dim it
					if (isset($device['state']) && $device['state']  == 0) {
						$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$device['did'].'</did><value>1</value></gip>';
						$result = getCurlReturn($CMD);
					}
				} else if ($function == 'dimby' ) {
					$dBrightness = isset($device['level']) ? $device['level'] : 100;
					$dBrightness -= $val;

					if ($dBrightness < 0 ) {
					    $dBrightness = 0;
					}

					$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$device['did'].'</did><value>'.$dBrightness.'</value><type>level</type></gip>';

					$result = getCurlReturn($CMD);
					$array = xmlToArray($result);

					//turn light on if it is not in order to dim it
					if (isset($device['state']) && $device['state'] == 0) {
						$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$device['did'].'</did><value>1</value></gip>';
						$result = getCurlReturn($CMD);
					}
				} else if ($function == 'brightenby' ) {
					//turn light on if it is not in order to dim it
					if (isset($device['state']) && $device['state'] == 0) {
						$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$device['did'].'</did><value>1</value></gip>';
						$result = getCurlReturn($CMD);
					}

					$dBrightness = isset($device['level']) ? $device['level'] : 0;
					$dBrightness += $val;

					if ($dBrightness > 100) {
					    $dBrightness = 100;
					}

					$CMD = 'cmd=DeviceSendCommand&data=<gip><version>1</version><token>'.TOKEN.'</token><did>'.$device['did'].'</did><value>'.$dBrightness.'</value><type>level</type></gip>';

					$result = getCurlReturn($CMD);
					$array = xmlToArray($result);
				}
			}

			echo json_encode([
			    'success' => 1,
                'devices' => sizeof($DEVICES),
                'fx' => $function,
                'val' => $val
            ]);
		} else {
			echo json_encode([
			    'error' => 'no devices in home'
            ]);
		}
	} else {
		echo json_encode([
		    'error' => 'unknown type, required: device | room'
        ]);
	}
} else {
    if ($function == 'scene' && $type != '' && $UID != '') {
        //Run scene
        $CMD = null;

        if ($type == 'run') {
            $CMD = 'cmd=SceneRun&data=<gip><version>1</version><token>' . TOKEN . '</token><sid>' . $UID . '</sid></gip>';
        } else if ($type == 'off') {
            $CMD = 'cmd=SceneRun&data=<gip><version>1</version><token>' . TOKEN . '</token><sid>' . $UID . '</sid><val>0</val></gip>';
        } else if ($type == 'on') {
            $CMD = 'cmd=SceneRun&data=<gip><version>1</version><token>' . TOKEN . '</token><sid>' . $UID . '</sid><val>1</val></gip>';
            /*} else if ($type == 'delete' ) {
                //$CMD = 'cmd=SceneDelete&data=<gip><version>1</version><token>'.TOKEN.'</token><sid>'.$UID.'</sid></gip>';
            }*/

            if (null !== $CMD) {
                echo json_encode([
                    'success' => 1,
                    'scene' => $UID,
                    'fx' => $function,
                    'resp' => xmlToArray(getCurlReturn($CMD))
                ]);
            } else {
                echo json_encode([
                    'error' => 'No Scene mode specified'
                ]);
            }

            exit;
        }

        $sceneDevices = [
            'rooms' => [],
            'devices' => [],
            'count' => 0
        ];
        $sceneList = [];

        if ($function == 'getSceneState' || $function == 'getState') {
            if ($UID != '' || $function == 'getState') {

                $CMD = 'cmd=SceneGetListDetails&data=<gip><version>1</version><token>' . TOKEN . '</token><bigicon>1</bigicon></gip>';
                $result = getCurlReturn($CMD);
                $array = xmlToArray($result);
                $scenes = $array['scene'];
                if (is_array($scenes)) {
                    $sceneItemCount = 0;
                    //Todo: Update to foreach?
                    for ($x = 0; $x < sizeof($scenes); $x++) {
                        $sceneList[] = [
                            'id' => $scenes[$x]['sid'],
                            'name' => $scenes[$x]['name'],
                            'icon' => $scenes[$x]['icon'],
                            'active' => $scenes[$x]['active']
                        ];

                        if ($scenes[$x]['sid'] == $UID) {
                            if (isset($scenes[$x]['device']['id'])) {
                                $sceneItemCount = $sceneItemCount + 1;
                                //one item in scene

                                $item = $scenes[$x]['device'];
                                if ($item['type'] == 'D') {
                                    $sceneDevices['devices'][] = $item['id'];
                                } else if ($item['type'] == 'R') {
                                    $sceneDevices['rooms'][] = $item['id'];
                                }
                            } else if (is_array($scenes[$x]['device'])) {
                                foreach ($scenes[$x]['device'] as $d) {
                                    if (isset($d['id'])) {
                                        $sceneItemCount = $sceneItemCount + 1;
                                        if ($d['type'] == 'D') {
                                            $sceneDevices['devices'][] = $d['id'];
                                        } else if ($d['type'] == 'R') {
                                            $sceneDevices['rooms'][] = $d['id'];
                                        }
                                    }
                                }
                            }

                            $sceneDevices['count'] = $sceneItemCount;
                        }
                    }
                }
            } else {
                echo json_encode([
                    'error' => 'No Scene ID specified'
                ]);
            }
        }

        if ($function == 'getState' || $function == 'getDeviceState' || $function == 'getRoomState' || $function == 'getSceneState') {
            $sceneDeviceObjectsON = 0;

            $CMD = 'cmd=GWRBatch&data=<gwrcmds><gwrcmd><gcmd>RoomGetCarousel</gcmd><gdata><gip><version>1</version><token>' . TOKEN . '</token><fields>name,image,imageurl,control,power,product,class,realtype,status</fields></gip></gdata></gwrcmd></gwrcmds>&fmt=xml';

            $array = xmlToArray(getCurlReturn($CMD));

            if (!isset($array['gwrcmd'])) {
                exit;
            }

            $DEVICES = [];

            if (isset($array['gwrcmd']['gdata']['gip']['room'])) {
                $DATA = $array['gwrcmd']['gdata']['gip']['room'];
            } else {
                exit;
            }

            $ROOMS = [];
            $BRIDGE = [];

            if (sizeof($DATA) > 0) {
                if (isset($DATA['rid'])) {
                    $DATA = [$DATA];
                }

                foreach ($DATA as $room) {
                    $thisRoom = [];

                    if (isset($room['rid'])) {
                        $thisRoom['room_id'] = $room['rid'];
                        $thisRoom['name'] = $room['name'];
                        $thisRoom['color'] = $room['color'];
                        $thisRoom['colorid'] = $room['colorid'];
                        $thisRoom['brightness'] = 0;
                        $thisRoom['state'] = 0;

                        if (is_array($room['device'])) {
                            // Todo: Inverse so no empty if body?
                        } else {
                            $device = (array)$room['device'];

                            if (isset($device['did'])) {
                                $rd = [];

                                $rd['id'] = $device['did'];
                                $rd['name'] = $device['name'];
                                $rd['level'] = ($device['level'] != null ? (int)$device['level'] : 0);
                                $rd['state'] = $device['state'];
                                $rd['online'] = (isset($device['offline']) && $device['offline'] == 1) ? 1 : 0;

                                if (isset($device['other']) && isset($device['other']['rcgroup']) && $device['other']['rcgroup'] != null) {
                                    $rd['buttonNum'] = $device['other']['rcgroup'];
                                }

                                $thisRoom['brightness'] += $rd['level'];
                                $thisRoom['devices'][] = $rd;

                                if ($device['state'] > 0) {
                                    $thisRoom['state'] = (int)$thisRoom['state'] + 1;
                                }

                                if ($function == 'getSceneState' && in_array($rd['id'], $sceneDevices['devices']) && $rd['state'] > 0) {
                                    $sceneDeviceObjectsON++;
                                }

                                if ($function == 'getDeviceState' && $UID == $device['did']) {
                                    ob_clean();

                                    echo trim($device['state']);

                                    exit;
                                }

                            } else {
                                // Todo: Update to foreach?
                                for ($x = 0; $x < sizeof($device); $x++) {
                                    if (isset($device[$x]) && is_array($device[$x]) && !empty($device[$x])) {
                                        $rd = [];

                                        $rd['id'] = $device[$x]['did'];
                                        $rd['name'] = $device[$x]['name'];
                                        $rd['level'] = ($device[$x]['level'] != null ? (int)$device[$x]['level'] : 0);
                                        $rd['state'] = $device[$x]['state'];
                                        $rd['online'] = (isset($device[$x]['offline']) && $device[$x]['offline'] == 1) ? 1 : 0;

                                        if (isset($device[$x]['other']) && isset($device[$x]['other']['rcgroup']) && $device[$x]['other']['rcgroup'] != null) {
                                            $rd['buttonNum'] = $device[$x]['other']['rcgroup'];
                                        }

                                        $thisRoom['brightness'] += $rd['level'];
                                        $thisRoom['devices'][] = $rd;

                                        if ($device[$x]['state'] > 0) {
                                            $thisRoom['state'] = (int)$thisRoom['state'] + 1;
                                        }

                                        if ($function == 'getSceneState' && in_array($rd['id'], $sceneDevices['devices']) && $rd['state'] > 0) {
                                            $sceneDeviceObjectsON++;
                                        }


                                        if ($function == 'getDeviceState' && $UID == $device[$x]['did']) {
                                            ob_clean(); //Todo: Find out what this is here for

                                            echo trim($device[$x]['state']);

                                            exit;
                                        }
                                    }
                                }
                            }
                        }

                        if ($function == 'getRoomState' && $UID == $room['rid']) {
                            ob_clean(); //Todo: Find out what this is here for
                            echo ($thisRoom['state'] > 0) ? 1 : 0;

                            exit;
                        }

                        if ($function == 'getSceneState' && in_array($room['rid'], $sceneDevices['rooms']) && $thisRoom['state'] > 0) {
                            $sceneDeviceObjectsON++;
                        }

                        $thisRoom['devicesCount'] = sizeof($thisRoom['devices']);
                        $thisRoom['brightness'] = (int)($thisRoom['brightness'] / $thisRoom['devicesCount']);
                        $thisRoom['state'] = ($thisRoom['state'] > 0) ? ($thisRoom['state'] / sizeof($thisRoom['devices'])) : 0;

                        $ROOMS[] = $thisRoom;
                    }
                }
            }

            $BRIDGE['rooms'] = $ROOMS;
            $BRIDGE['roomCount'] = sizeof($ROOMS);
            $BRIDGE['scenes'] = $sceneList;
            $BRIDGE['sceneCount'] = sizeof($sceneList);

            if ($function == 'getState') {
                header('Content-Type: application/json');
                echo json_encode($BRIDGE);

                exit;
            } else if ($function == 'getSceneState') {
                if ($sceneDeviceObjectsON == 0) {
                    echo 0;
                } else if ($sceneDeviceObjectsON == $sceneDevices['count']) {
                    echo 1;
                } else {
                    echo $sceneDeviceObjectsON / $sceneDevices['count'];
                }
                exit;
            } else {
                echo '-1';

                exit;
            }
        }

        //Todo: Fix typo in received
        echo json_encode([
            'error' => 'argument empty or invalid. Required: fx, type, UID, val',
            'recieved' => $_REQUEST
        ]);
    }
}