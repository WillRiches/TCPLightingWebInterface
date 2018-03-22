<?php
define('LIGTHING_BRIDGE_IP', '192.168.0.4'); // IP address of TCP Bridge/Gateway
define('LIGHTING_BRIDGE_PORT', '443'); // 443 for new firmware, 80 for legacy - If you don't know, leave it at 443
define('LOCAL_URL', 'http://localhost'); // Address of your web server running this - this is used in runSchedule to call the API

define('USER_EMAIL', 'you@email.com'); // I think this is so you don't have to regenerate tokens if you run this script elsewhere
define('USER_PASSWORD', 'can-be-anything'); // Can be anything

define('FORCE_FADE_ON', 0); // Makes it so when lights are turned off they fade to 0 (Like Philips Bulbs)
define('FORCE_FADE_OFF', 0); // Makes it so when lights are turned on they fade to their assigned value from 0 (Like Philips Bulbs)

define('SAVE_SCHEDULE', 1); // Saves schedule to a binary file on save schedule.sched
define('LOG_ACTIONS', 1); // Saves completed actions to schedule.actioned
define('LOG_API_CALLS', 1); // Log issued API calls

/*
	IFTTT Integration - https://github.com/bren1818/TCPLightingWebInterface/wiki/IFTTT-Integration
	These settings  should be used in conjunction with your firewall and the .htaccess file.
*/

define('ALLOW_EXTERNAL_API_ACCESS', 0); // Allow outside access (Non Lan) (1 = true, 0 = false)
define('EXTERNAL_DDNS_URL', 'http://your-address.ddns.net');

define('REQUIRE_EXTERNAL_API_PASSWORD', 1); // Require a password for external (non lan) use IE for IFTTT? (1 = true, 0 = false)
define('EXTERNAL_API_PASSWORD', 'P@ssW0rd'); // Set what the password should be
define('RESTRICT_EXTERNAL_PORT', 1); // If request is an external (API) user, should they only be on a specific port? (1= yes, 2=no)
define('EXTERNAL_PORT', 443); // If you wish to use an alternate external port change this number to the corresponding port number

define('SCHEME', (LIGHTING_BRIDGE_PORT == 80) ? 'http' : 'https'); // Don't modify

define('TOKEN', '');

define('USE_LOCAL_API_IP', 1);
define('LOG_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR . 'logs');

date_default_timezone_set('Europe/London');

define('LATITUDE', 52);
define('LONGITUDE', -1.4);