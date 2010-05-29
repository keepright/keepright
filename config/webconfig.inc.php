<?php

// config file for webserver db credentials

$db_host="localhost";
$db_user="root";
$db_pass="haraldk";
$db_name="osm_EU";



// $db_name is the name of the physical database on the MySQL server for connecting

/*
cookie parameters: lon, lat, zoom, error_types to hide, language
cookie content is created in keepright.js/updateCookie()
for example:
keepright_cookie = osm_XA|156412246|481387746|11|0,40,50

$cookie = Array (
	[0] => 156412246
	[1] => 481387746
	[2] => 11
	[3] => 0,40,50
	[4] => de_AT
)
*/

if (isset($_COOKIE["keepright_cookie"])) {
	$cookie=explode('|', addslashes($_COOKIE["keepright_cookie"]));
} else {
	$cookie=false;
}


$error_types_name="error_types";
$comments_name="comments";
$comments_historic_name="comments_historic";

$USERS = array(
	'harald.kleiner@web.de' => array(	// super guru has access to all DBs
		'password' => 'shhh!',
		'schemata' => array('%')
	),
	'lennard' => array(			// Lennard: Belgium/Netherlands/Luxembourg
		'password' => 'shhh!',
		'schemata' => array('1', '2')
	)
);


/*
details on the web path:
website located at eg. http://localhost/kr/index.php
$path == "/kr/"
$baseURL == "http://localhost/kr/"
*/
$path_parts = pathinfo($_SERVER['SCRIPT_NAME']);
$path = $path_parts['dirname'] . ($path_parts['dirname'] == '/' ? '' : '/');
$baseURL="http://" . $_SERVER['SERVER_NAME'] . $path;


// define constants for l10n
define('PROJECT_DIR', realpath('./'));
define('LOCALE_DIR', PROJECT_DIR .'/locale');
define('DEFAULT_LOCALE', 'en_US');
$locale = (isset($_GET['lang'])) ? $_GET['lang'] :
	(isset($cookie[4]) ? $cookie[4] : DEFAULT_LOCALE);

require_once('php-gettext/gettext.inc');

T_setlocale(LC_MESSAGES, $locale);
$domain = 'keepright';
T_bindtextdomain($domain, LOCALE_DIR);
T_bind_textdomain_codeset($domain, 'UTF-8');
T_textdomain($domain);

?>