<?php

include 'config.inc.php';

function isLocalIPAddress($IPAddress)
{
    if ('127.0.0.1' === $IPAddress) {
        return true;
    }

    return (!filter_var($IPAddress, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
}

$CURRENT_PAGE_SCRIPT = basename($_SERVER['SCRIPT_FILENAME'], '.php');

/**
 * HTML debug print array
 *
 * @param $array
 */
function pa($array)
{
    echo '<pre>' . print_r($array, true) . '</pre>';
}

/**
 * @param $postDataString
 *
 * @return mixed
 */
function getCurlReturn($postDataString)
{
    $URL = SCHEME . '://' . LIGTHING_BRIDGE_IP . ':' . LIGHTING_BRIDGE_PORT . '/gwr/gop.php';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $URL);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataString);

    if ('https' === SCHEME) {
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);

    curl_close($ch);

    return $result;
}

/**
 * @param $string
 *
 * @return mixed
 */
function xmlToArray($string)
{
    $xml = simplexml_load_string($string);
    $json = json_encode($xml);
    $array = json_decode($json, true);

    return $array;
}

/**
 * @return array
 */
function getDevices()
{
    $CMD = 'cmd=GWRBatch&data=<gwrcmds><gwrcmd><gcmd>RoomGetCarousel</gcmd><gdata><gip><version>1</version><token>' . TOKEN . '</token><fields>name,image,imageurl,control,power,product,class,realtype,status</fields></gip></gdata></gwrcmd></gwrcmds>';
    $result = getCurlReturn($CMD);
    $array = xmlToArray($result);
    $data = $array['gwrcmd']['gdata']['gip']['room'];
    $devices = [];

    if (isset($data['rid'])) {
        $data = [$data];
    }

    foreach ($data as $room) {
        if (is_array($room['device'])) {
            $device = (array)$room['device'];

            if (isset($device['did'])) {
                //item is singular device
                $devices[] = $room['device'];
            } else {
                for ($x = 0; $x < sizeof($device); $x++) {
                    if (isset($device[$x]) && is_array($device[$x]) && !empty($device[$x])) {
                        $devices[] = $device[$x];
                    }
                }
            }
        }
    }

    return $devices;
}

function pageHeader($title)
{
    global $CURRENT_PAGE_SCRIPT;
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <title>TCP Control Script</title>
        <link rel="apple-touch-icon" sizes="180x180" href="css/favicons/apple-touch-icon.png">
        <link rel="icon" type="image/png" href="css/favicons/favicon-32x32.png" sizes="32x32">
        <link rel="icon" type="image/png" href="css/favicons/favicon-16x16.png" sizes="16x16">
        <link rel="manifest" href="css/favicons/manifest.json">
        <link rel="mask-icon" href="css/favicons/safari-pinned-tab.svg" color="#5bbad5">
        <link rel="shortcut icon" href="css/favicons/favicon.ico">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="apple-mobile-web-app-title" content="TCP Lighting">
        <meta name="application-name" content="TCP Lighting">
        <meta name="msapplication-config" content="css/favicons/browserconfig.xml">
        <meta name="theme-color" content="#ffffff">
        <link rel="stylesheet" href="css/jquery-ui.min.css"/>
        <link rel="stylesheet" href="css/style.css"/>

        <link rel="stylesheet" href="bootstrap/css/bootstrap.min.css" />
        <script src="jquery-3.3.1.min.js"></script>
        <script src="bootstrap/js/bootstrap.min.js"></script>

        <link rel="stylesheet" href="/lib/jQueryUI/jquery-ui.min.css" />
        <script src="/lib/jQueryUI/jquery-ui.js"></script>

        <link rel="stylesheet" href="/lib/font-awesome/css/fa-solid.css" />
        <link rel="stylesheet" href="/lib/font-awesome/css/fontawesome.min.css" />

        <!--<script src="js/libs.js"></script>-->

        <script>
            var API_IP = '<?php echo $_SERVER['REQUEST_URI']; ?>';
        </script>
        <script src="js/scripts.js"></script>

        <title><?php echo $title; ?></title>
    </head>
    <body>

    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <a class="navbar-brand" href="/">Lite Panel</a>
        <div class="navbar-collapse collapse">
            <div class="navbar-nav">
                <a class="nav-item nav-link" href="index.php">Home</a>
                <a class="nav-item nav-link" href="index.php#scenes">Scenes</a>
                <a class="nav-item nav-link" href="scheduler.php">Scheduler</a>
                <a class="nav-item nav-link" href="setDateTime.php">Set time</a>
                <a class="nav-item nav-link" href="discoverBulbs.php">Scan</a>
            </div>
        </div>
    </nav>
    <?php
}

function pageFooter()
{
    ?>
    </body>
    </html>
    <?php
}

/**
 * @param $string
 */
function SCHEDLog($string)
{
    if (LOG_ACTIONS == 1) {
        file_put_contents(LOG_DIR . DIRECTORY_SEPARATOR . 'Schedule-Actioned.log', date('Y-m-d H:i:s ') . $string . "\n", FILE_APPEND | LOCK_EX);
    }
}

/**
 * @param int $length
 *
 * @return string
 */
function generateRandomString($length = 10)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }

    return $randomString;
}
