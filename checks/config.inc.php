<?php

$error_types=array();
$db_params=array();

// first read default config file shipped with keepright
// then read the user-defined config file to overwrite custom settings
parse_config_vars('config');

// for calling from shell, find config in HOME directory
parse_config_vars($_ENV["HOME"] . '/keepright.config');

// for calling from the admin interface. There is no suitable HOME variable (the script is run by the apache user), instead there is a symlink pointing to keepright.config
parse_config_vars('keepright.config');


function parse_config_vars($filename) {
	global $error_types, $schema, $db_params;

	if (!file_exists($filename)) return;	// ignore missing files silently

	$configfile=file($filename);	// read config file into variable

	$conf_vars = array('MAIN_DB_HOST', 'MAIN_DB_USER', 'MAIN_DB_PASS',
		'WEB_DB_HOST', 'WEB_DB_USER', 'WEB_DB_PASS', 'WEB_DB_NAME',
		'ERROR_VIEW_FILE', 'ERROR_TYPES_FILE', 'RESULTSDIR', 'OSMOSIS_BIN',
		'FTP_HOST', 'FTP_USER', 'FTP_PASS', 'FTP_PATH',
		'UPDATE_TABLES_URL', 'UPDATE_TABLES_URL_LOCAL',
		'UPDATE_TABLES_USERNAME', 'UPDATE_TABLES_PASSWD',
		'ADMIN_USERNAME', 'CREATE_COMPRESSED_DUMPS', 'TMPDIR', 'MARGIN');

	$check_parts = array('NAME', 'ENABLED', 'DESCRIPTION', 'FILE');
	$db_parts = array('URL', 'FILE', 'MAIN_DB_NAME', 'MAIN_SCHEMA_NAME', 'CAT', 'MIN_SIZE', 'LEFT', 'TOP', 'RIGHT', 'BOTTOM');

	foreach ($configfile as $line) {

		if (preg_match('/^\s*#/', $line) === 0) {	// ignore comments (lines starting with #)

			// find database name
			if (preg_match('/^\s*MAIN_DB_NAME_' .$schema. '\s*=\s*"(.*)\"/', $line, $matches))
				$GLOBALS['MAIN_DB_NAME']=$matches[1];

			// find all the other db credentials
			foreach ($conf_vars as $var) {
				if (preg_match('/^\s*' . $var . '\s*=\s*"(.*)\"/', $line, $matches))
					$GLOBALS[$var]=$matches[1];
			}


			// find database parameters
			foreach ($db_parts as $var) {
				if (preg_match('/^\s*' . $var . '_([A-Z0-9]{1,4})\s*=\s*"(.*)\"/', $line, $matches))
					$db_params[$matches[1]][$var] = $matches[2];
			}

			// find check parameters
			foreach ($check_parts as $var) {
				if (preg_match('/^\s*CHECK_([0-9]{4})_' . $var . '\s*=\s*"(.*)\"/', $line, $matches))
					$error_types[1*$matches[1]][$var] = $matches[2];
			}

			// find check subtypes
			if (preg_match('/^\s*SUBTYPE_([0-9]{4})_NAME\s*=\s*"(.*)\"/', $line, $matches)) {
				$main_type = 10*floor($matches[1]/10);
				$error_types[$main_type]['SUBTYPES'][$matches[1]] = $matches[2];
			}

		}
	}
}

//print_r($error_types);
/*
example for $error_types:
Array
(
    [10] => Array
        (
            [NAME] => deleted items
            [ENABLED] => 0
            [FILE] => 0010_deleted_items.php
            [DESCRIPTION] => Deleted items should...
        )

    [20] => Array
        (
            [NAME] => multiple nodes on the same spot
            [ENABLED] => 0
            [FILE] => 0020_multiple_nodes_on_same_spot.php
            [DESCRIPTION] => Try to find nodes that are...
        )
)
*/

if (!isset($dont_care_about_missing_db_parameters)) {

	if (strlen(trim($MAIN_DB_NAME))==0) {
		echo "no database name found in config for '$schema', exiting.\n";
		exit;
	}
	if (strlen(trim($MAIN_DB_HOST))==0) {
		echo "no database host name found in config for '$schema', exiting.\n";
		exit;
	}
}
// BEWARE: postgres really is NOT ALWAYS case sensitive!
// if you create schema osm_TT then in fact osm_tt will be created
// but if you create schema 'osm_TT' then it will be called osm_TT

$connectstring="host=$MAIN_DB_HOST dbname=$MAIN_DB_NAME user=$MAIN_DB_USER password=$MAIN_DB_PASS";


// using schemas is mandatory, even for single-schema continent's databases.
// append it to the connect string to make any connection
// use the given schema automatically
if (isset($schema)) {
	$connectstring.=" options='--search_path=schema$schema,public'";
}



/*
configuration options used for individual checks
*/


// minimum distance between a not connected end of a way and any other segment
// nearby. ways coming closer than min_distance to the end of another way are
// considered to be almost-junctions. specified in meters.
// The value of 10 is chosen because most streets are approximately 10 meters
// wide and people draw them close enough that they _seem_ to be connected
$check0050_min_distance=10;

?>
