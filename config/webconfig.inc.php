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
$error_types_name="error_types_" . $db;
$comments_name="comments_" . $db;
$comments_historic_name="comments_historic_" . $db;
$updated_file_name="updated_" . $db;
$planetfile_date_file_name="planetfile_date_" . $db;

$UPDATE_TABLES_PASSWD="shhh!";

?>
