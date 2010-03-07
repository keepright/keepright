<?php

// config file for webserver db credentials

$db_host="localhost";
$db_user="root";
$db_pass="haraldk";
$db_name="osm_EU";

// $db_name is the name of the physical database on the MySQL server for connecting

// $db is a value used for selection rows out of error_view.db_name
// this allows to have different countries inside one table and select them via URL


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


// precedence for db parameter: 1: URL, 2: cookie, 3: fixed "osm_EU"
if (!isset($db)) {
	if (isset($_GET['db'])) {
		$db=addslashes($_GET['db']);
	} else {
		if ($cookie) $db=$cookie[0]; else $db='osm_EU';
	}
}

// transparently translate CA into US database
if ($_GET['db']=='osm_CA') $_GET['db']='osm_US';
if ($db=='osm_CA') $db='osm_US';


$error_view_name="error_view_" . $db;
$error_view_old_name="error_view_" . $db . "_old";
$error_types_name="error_types_" . $db;
$comments_name="comments_" . $db;
$comments_historic_name="comments_historic_" . $db;
$updated_file_name="updated_" . $db;
$planetfile_date_file_name="planetfile_date_" . $db;


$USERS = array(
	'harald.kleiner@web.de' => array(	// super guru has access to all DBs
		'password' => 'shhh!',
		'DB' => array(
			'%' => array('%')
		)
	),
	'lennard' => array(			// Lennard: Belgium/Netherlands/Luxembourg
		'password' => 'shhh!',
		'DB' => array(
			'osm_EU' => array('2')
		)
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
