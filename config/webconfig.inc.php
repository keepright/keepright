<?php

// config file for webserver db credentials

$db_host="localhost";
$db_user="root";
$db_pass="haraldk";
$db_name="osm_EU";

// $db_name is the name of the physical database on the MySQL server for connecting

// $db is a value used for selection rows out of error_view.db_name
// this allows to have different countries inside one table and select them via URL

if (!isset($db)) {
	if (isset($_GET['db'])) {
		$db=addslashes($_GET['db']);
	} else {
		$db=$db_name;
	}
}
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
			'osm_EU' => array('osm_XK')
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
