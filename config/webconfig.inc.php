<?php

// config file for webserver db credentials

$db_host="localhost";
$db_user="root";
$db_pass="haraldk";
$db_name="osm_EU";



// $db_name is the name of the physical database on the MySQL server for connecting

/*
cookie parameters: db, lon, lat, zoom, error_types to hide
for example:
keepright_cookie = osm_XA|156412246|481387746|11|0,40,50

$cookie = Array (
	[0] => osm_XA
	[1] => 156412246
	[2] => 481387746
	[3] => 11
	[4] => 0,40,50
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

?>