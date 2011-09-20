<?php


// prepareDB asserts an empty database ready for loading OSM data
// TODO: maybe just connecting isn't enough to ensure postgis is installed in the DB
function prepareDB($schema) {
	global $config;

	// try connecting to main database
	$db = @pg_pconnect(connectstring($schema));
	if ($db) {

		pg_close($db);
		dropSchema($schema);

	} else {

		logger('Cannot connect to database. Trying to create a database', KR_WARNING);

		createDB();
	}

	createSchema($schema);
}


function createDB() {
	global $config;

	$tmp_connecstr='host=' . $config['db']['host'] . ' dbname=template1 user=' . $config['db']['user'] . ' password=' . $config['db']['password'];
	$db = pg_pconnect($tmp_connecstr);

	if (!$db)  {
		logger('Cannot connect to database template1 for creating a database', KR_ERROR);
		exit(1);
	}


	query('CREATE DATABASE ' . $config['db']['database'], $db);
	pg_close($db);


	// reconnect to the new database (using public schema)
	$db = pg_pconnect(connectstring());
	query("CREATE SCHEMA schema$schema", $db);

	// activate PL/PGSQL
	query('CREATE LANGUAGE plpgsql', $db);


	// initialize postGIS
	logger("installing postGIS functions...");
	if (execute_any($config['postgis.sql'], $db)) {
		logger("postGIS init file postgis.sql not found. cannot initianlze DB structures", KR_ERROR);
		exit(1);
	}


	// initialize hstore
// 	logger("installing hstore...");
// 	if (execute_any($config['hstore.sql'], $db)) {
// 		logger("hstore init file hstore.sql not found. cannot initianlze DB structures", KR_ERROR);
// 		exit(1);
// 	}

	pg_close($db);
}


function createSchema($schema) {
	global $config;

	// reconnect to the new database (using schema public)
	$db = pg_pconnect(connectstring());
	query("CREATE SCHEMA schema$schema", $db);
	pg_close($db);

	// reconnect to the new database (using the new schema)
	$db = pg_pconnect(connectstring($schema));

	// add SRIDs into spatial_ref_sys table
	query(file_get_contents($config['base_dir'].'planet/spatial_ref_sys.sql'), $db, false);


	// create openstreetmap tables for the non standard simple schema (old schema
	// including arbitrary extensions)
 	query(file_get_contents($config['base_dir'].'planet/pgsql_simple_schema.sql'), $db, false);

	// create openstreetmap tables for simple schema including bbox + linestring
// 	query(file_get_contents($config['base_dir'].'planet/pgsql_simple_schema_0.6.sql'), $db, false);
// 	query(file_get_contents($config['base_dir'].'planet/pgsql_simple_schema_0.6_action.sql'), $db, false);
// 	query(file_get_contents($config['base_dir'].'planet/pgsql_simple_schema_0.6_bbox.sql'), $db, false);
// 	query(file_get_contents($config['base_dir'].'planet/pgsql_simple_schema_0.6_linestring.sql'), $db, false);

	// create keepright tables
	// still not relevant since run-checks does this job
	//query(file_get_contents($config['base_dir'].'planet/keepright.sql'), $db, false);

	pg_close($db);
}


function dropSchema($schema) {
	global $config;

	// connect using schema public
	$db = pg_pconnect(connectstring());

	query("DROP SCHEMA IF EXISTS schema$schema CASCADE", $db);

	pg_close($db);
}

// gets an array like this: array(0->'1.sql', 1->'2.sql')
// and execues the first file that is readable
// returns 1 if no file was found, 0 on success
function execute_any($filenames, $db) {

	foreach ($filenames as $key=>$filename) {
		if (is_readable($filename)) {
			query(file_get_contents($filename), $db, false);
			return 0;
		}
	}
	return 1;
}

?>