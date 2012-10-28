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

if (isset($_COOKIE['keepright_cookie'])) {
	$cookie=explode('|', addslashes($_COOKIE['keepright_cookie']));
} else {
	$cookie=false;
}


$error_types_name='error_types';
$comments_name='comments';
$comments_historic_name='comments_historic';


// list of schema names for use with Martijn's Interface
$schemanames=array(2, 4, 7, 15, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 37, 38, 39, 40, 41, 42, 43, 44, 45, 46, 47, 48, 50, 51, 52, 53, 54, 55, 56, 57, 58, 59, 60, 61, 62, 63, 64, 65, 68, 69, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90, 91, 92, 93, 94, 95, 96, 97, 98, 99, 100, 101, 102, 102);


$USERS = array(
	'harald.kleiner@web.de' => array(	// super guru has access to all DBs
		'password' => 'shhh!',
		'schemata' => array('%')
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
$baseURL='http://' . $_SERVER['SERVER_NAME'] . $path;


// define constants for l10n
define('PROJECT_DIR', realpath('./'));
define('LOCALE_DIR', PROJECT_DIR .'/locale');
define('DEFAULT_LOCALE', 'en');
$locale = (isset($_GET['lang'])) ? $_GET['lang'] :
	(isset($cookie[4]) ? $cookie[4] : DEFAULT_LOCALE);

require_once('php-gettext/gettext.inc');

T_setlocale(LC_MESSAGES, $locale);
$domain = 'keepright';
T_bindtextdomain($domain, LOCALE_DIR);
T_bind_textdomain_codeset($domain, 'UTF-8');
T_textdomain($domain);

?>