<?php

// config file for webserver db credentials

$db_host="localhost";
$db_user="root";
$db_pass="haraldk";
$db_name="osm_EU";


if (!isset($db)) {
	if (isset($_GET['db'])) {
		$db=addslashes($_GET['db']);
	} else {
		$db='osm_EU';
	}
}
$error_view_name="error_view_" . $db;
$error_types_name="error_types_" . $db;
$comments_name="comments_" . $db;
$comments_historic_name="comments_historic_" . $db;


?>
