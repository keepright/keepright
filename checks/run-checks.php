<?php

/*
this is the main entrance script.
it will establish database connections and
call any check that is enabled in config
after that state information in the errors table
is updated, newly found errors are inserted
and the error-view is rebuilt
*/

if ($argc<2) {
	echo "Usage: \">php run-checks.php AT [50...]\"";
	echo "will run all checks on the database for Austria or\n";
	echo "if present, run only given error checks.\n";
	echo "database credentials and checks to be run are configured in config";
	exit;
}

$schema=$argv[1];		// schema number identifying planet part
require('config.inc.php');
require('helpers.inc.php');
require('BufferedInserter.php');


echo "Running checks for $schema\n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db2 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db3 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db4 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db5 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db6 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);


// first of all: check if the tables are populated
if (!(query_firstval('SELECT COUNT(*) FROM nodes', $db1, false)>0 &&
	query_firstval('SELECT COUNT(*) FROM ways', $db1, false)>0 &&
	query_firstval('SELECT COUNT(*) FROM relations', $db1, false)>0 &&
	query_firstval('SELECT COUNT(*) FROM node_tags', $db1, false)>0 &&
	query_firstval('SELECT COUNT(*) FROM way_tags', $db1, false)>0 &&
	query_firstval('SELECT COUNT(*) FROM relation_tags', $db1, false)>0 &&
	query_firstval('SELECT COUNT(*) FROM relation_members', $db1, false)>0 &&
	query_firstval('SELECT COUNT(*) FROM way_nodes', $db1, false)>0)) {

		echo "!!!!!!!!!!!!!!!!!!!!!!!!\n";
		echo "!!!! A L E R T\n";
		echo "!!!!!!!!!!!!!!!!!!!!!!!!\n";
		echo "one of the base tables is empty! I won't tell you which one. Go find out yourself.\n";

	exit;
}



// (re)create temporary errors-table
// check routines drop their errors into _tmp_errors. A syncing-job updates state information
// in the "real" errors-table at the end of this script

if (!type_exists($db1, 'type_error_state', 'public'))
	query("CREATE TYPE public.type_error_state AS ENUM('new','cleared','ignored','reopened')", $db1, false);

if (!type_exists($db1, 'type_object_type', 'public'))
	query("CREATE TYPE public.type_object_type AS ENUM('node','way','relation')", $db1, false);



query("DROP TABLE IF EXISTS _tmp_errors", $db1, false);
query("
	CREATE TABLE _tmp_errors (
	error_type int NOT NULL,
	object_type public.type_object_type NOT NULL,
	object_id bigint NOT NULL,
	description text NOT NULL,
	last_checked timestamp NOT NULL,
	lat double precision,
	lon double precision,
	UNIQUE (error_type, object_type, object_id, lat, lon)
	)
", $db1, false);
add_insert_ignore_rule('_tmp_errors', array('error_type', 'object_type', 'object_id', 'lat', 'lon'), $db1);


// the "real" errors-table. it looks like _tmp_errors with one difference:
// errors has state information (new, closed , ignored...) and is persistent
if (!table_exists($db1, 'errors', 'public')) {
	query("
		CREATE TABLE public.errors (
		error_id serial,
		error_type int NOT NULL,
		object_type public.type_object_type NOT NULL,
		object_id bigint NOT NULL,
		state type_error_state NOT NULL,
		description text NOT NULL,
		first_occurrence timestamp NOT NULL,
		last_checked timestamp NOT NULL,
		lat double precision,
		lon double precision,
		schema VARCHAR(8) NOT NULL DEFAULT '',
		UNIQUE (error_type, object_type, object_id, lat, lon)
		)
	", $db1, false);
	query("CREATE INDEX idx_errors_schema ON public.errors (schema);", $db1);
	query("CREATE INDEX idx_errors_object_id ON public.errors (object_id);", $db1);
	query("CREATE INDEX idx_errors_state ON public.errors (state);", $db1);
	add_insert_ignore_rule('public.errors', array('error_type', 'object_type', 'object_id', 'lat', 'lon'), $db1);
}
// (re)create table of error type descriptions out of definition-array in config.inc
query("DROP TABLE IF EXISTS error_types;", $db1, false);
query("
	CREATE TABLE error_types (
	error_type int NOT NULL,
	error_name varchar(100) NOT NULL,
	error_description text NOT NULL,
	PRIMARY KEY (error_type)
	)
", $db1, false);

// insert any error-type that is defined and enabled in $error_types in config.inc
foreach ($error_types as $error_type=>$error) {
	if ($error['ENABLED'] != '0' ) {
		query("
			INSERT INTO error_types(error_type, error_name, error_description) 
			VALUES(" . pg_escape_string($db1, $error_type) . ",'" . pg_escape_string($db1, $error['NAME']) . "','" . pg_escape_string($db1, $error['DESCRIPTION']) . "')
		", $db1, false);

		// insert any subtype if some exist
		if (is_array($error['SUBTYPES'])) foreach ($error['SUBTYPES'] as $subtype_id=>$subtype) {
			query("
				INSERT INTO error_types(error_type, error_name, error_description) 
				VALUES(" . pg_escape_string($db1, $subtype_id) . ",'" . pg_escape_string($db1, $subtype) . "','" . pg_escape_string($db1, $error['DESCRIPTION']) . "')
			", $db1, false);
		}
	}

}



// helper functions that are used in database-queries are created here (and dropped at the end)
create_postgres_functions($db1);



// execute all enabled jobs from $joblist as defined in config.inc
// or any job number given on the command line that is found in $joblist
// filename convention is: 0000_name_of_check.php
// with 4 leading digits numbering the checks in steps of 10
$checks_executed='10*FLOOR(e.error_type/10) IN (0';
$jobreport=array();

foreach ($error_types as $error_type=>$error) {

	// regardless of enabled state: if a check is called on the commandline
	// it shall be executed. So look up in the arguments list
	// if the check is named there, execute it
	// cmdline arguments are padded on the left with zeroes before comparing with $error_type
	for ($arg_counter=2;$arg_counter<$argc;$arg_counter++) {
		if ($called = $error_type==str_pad($argv[$arg_counter], 4, '0', STR_PAD_LEFT)) break;
	}

	// two options here: a) no checks are called on commandline -> execute all enabled checks
	// b) the check is found in the command line arguments -> execute it, no matter what $enabled says
	if (($error['ENABLED']!='0' && $argc<3) || $called) {
		echo "-------------------------------------------------------------------\n";
		$starttime=microtime(true);
		echo strftime('%D %T') . ": starting check " . $error['FILE'] . "...\n";

		// including the file means executing the job
		if (strlen(trim($error['FILE']))>0)
			include($error['FILE']);

		// remember which jobs got executed because only these jobs have to be included  in syncing
		$checks_executed.=',' . (1*$error_type);

		$checktime=microtime(true)-$starttime;
		echo "\ntotal check time: " . format_time($checktime) . "\n";
		$jobreport[$error['FILE']]=format_time($checktime);
	}
}

$checks_executed.=')';
//echo "checks_executed: $checks_executed\n";

// now sync _tmp_errors with errors:
// * insert new errors into errors
// * update state information for persistent errors
// * update state information for removed errors

// please note: lat/lon may be null in _tmp_errors and in errors.
// But in SQL standard NULL == NULL always returns NULL,
// which is interpreted as false. So you cannot join on NULL values!
// The workaround is to use 'IS NOT DISTINCT FROM' which will return false
// if one value is not null and return true if both are null

query("CREATE INDEX idx_tmp_errors_object_id ON _tmp_errors (object_id);", $db1);
query("CREATE INDEX idx_tmp_errors_object_type ON _tmp_errors (object_type);", $db1);
query("CREATE INDEX idx_tmp_errors_error_type ON _tmp_errors (error_type);", $db1);
query("CREATE INDEX idx_tmp_errors_latlon ON _tmp_errors (lat, lon);", $db1);

// update last-checked timestamp for all errors that (still) exist
// set reopened-state for cleared errors that are now found in _tmp_errors again
query("
	UPDATE public.errors AS e
	SET schema='$schema', last_checked=te.last_checked,
	description=te.description,
	state = CAST(CASE e.state WHEN 'cleared' THEN 'reopened' ELSE 'new' END AS type_error_state)
	FROM _tmp_errors te
	WHERE e.error_type=te.error_type AND e.object_type=te.object_type AND e.object_id=te.object_id AND e.lat IS NOT DISTINCT FROM te.lat AND e.lon IS NOT DISTINCT FROM te.lon
", $db1);



// set cleared-state for errors that are not found in _tmp_errors any more
query("
	UPDATE public.errors e
	SET state='cleared', last_checked=NOW()
	WHERE e.schema='$schema' AND e.state<>'cleared' AND ($checks_executed) AND
	NOT EXISTS (SELECT * FROM _tmp_errors WHERE e.error_type=_tmp_errors.error_type AND e.object_type=_tmp_errors.object_type AND e.object_id=_tmp_errors.object_id AND e.lat IS NOT DISTINCT FROM _tmp_errors.lat AND e.lon IS NOT DISTINCT FROM _tmp_errors.lon)
", $db1);


// add newly found errors
query("
	INSERT INTO public.errors (schema, error_type, object_type, object_id, state, description, first_occurrence, last_checked, lat, lon)
	SELECT '$schema', e.error_type, e.object_type, e.object_id, CAST('new' AS type_error_state), e.description, e.last_checked, e.last_checked, e.lat, e.lon
	FROM _tmp_errors AS e LEFT JOIN public.errors ON (e.error_type=errors.error_type AND e.object_type=errors.object_type AND e.object_id=errors.object_id AND e.lat IS NOT DISTINCT FROM errors.lat AND e.lon IS NOT DISTINCT FROM errors.lon)
	WHERE public.errors.object_id IS NULL AND ($checks_executed)
", $db1);

// clear errors not present in any schema
// don't need that any more.
//query("
//	UPDATE public.errors AS e
//	SET state = CAST('cleared' AS type_error_state)
//	WHERE schema='' OR schema IS NULL
//", $db1);


// rebuild the error-view:
// error_view looks like errors but has additional information joined in:
// whereas in table errors lat/lon are optional, they are mandatory in error_view

// checks normally leave lat/lon empty as these values are retrieved
// at this point following these rules:
// * for object_type==node lat/lon of the given node_id are retrieved
// * for object_type==way lat/lon of the _first_ node of given way are inserted
// * for relations all ways and nodes included in the relation are retrieved and their center of gravity is inserted

// only in special cases (e.g. a check wants to point to the _last_ node of a way,
// a check may specify values for lat/lon

if (!table_exists($db1, 'error_view', 'public')) {
	query("
		CREATE TABLE public.error_view (
		error_id int NOT NULL,
		db_name VARCHAR(50) NOT NULL,
		schema VARCHAR(8) NOT NULL DEFAULT '',
		error_type int NOT NULL,
		error_name VARCHAR(100) NOT NULL DEFAULT '',
		object_type public.type_object_type NOT NULL,
		object_id bigint NOT NULL,
		state type_error_state NOT NULL,
		description text NOT NULL,
		first_occurrence timestamp NOT NULL,
		last_checked timestamp NOT NULL,
		object_timestamp timestamp NOT NULL DEFAULT '1970-01-01',
		lat int NOT NULL,
		lon int NOT NULL
		)
	", $db1, false);
}

if (!index_exists($db1, 'idx_tmp_error_view_schema', 'public')) {
	query("CREATE INDEX idx_tmp_error_view_schema ON public.error_view (schema);", $db1);
}
if (!column_exists('error_view', 'object_timestamp', $db1, 'public')) {
	query("ALTER TABLE public.error_view ADD COLUMN object_timestamp timestamp NOT NULL DEFAULT '1970-01-01'", $db1);
}

// delete anything from this (sub-)database
query("
	DELETE FROM public.error_view
	WHERE schema='$schema' OR schema IS NULL or schema=''
", $db1);

// _tmp_ev is used as helper table to find the locations of relations
query("DROP TABLE IF EXISTS _tmp_ev", $db1, false);
query("CREATE TABLE _tmp_ev (LIKE public.error_view
	INCLUDING DEFAULTS INCLUDING CONSTRAINTS INCLUDING INDEXES)
", $db1, false);

query("
	INSERT INTO _tmp_ev (error_id, db_name, schema, error_type, object_type, object_id, state, description, first_occurrence, last_checked, lat, lon)
	SELECT DISTINCT e.error_id, '$MAIN_DB_NAME', '$schema', e.error_type, e.object_type, e.object_id, e.state, e.description, e.first_occurrence, e.last_checked, 0, 0
	FROM public.errors e
	WHERE e.schema='$schema' AND e.object_type='relation' AND state<>'cleared' AND (e.lat IS NULL OR e.lon IS NULL)
", $db1);
query("CREATE INDEX idx_tmp_error_view_object_id ON _tmp_ev (object_id);", $db1, false);
query("CREATE INDEX idx_tmp_error_view_latlon ON _tmp_ev (lat,lon);", $db1, false);
query("ANALYZE _tmp_ev", $db1, false);

query("
	UPDATE _tmp_ev e
	SET lat=1e7*n.lat, lon=1e7*n.lon
	FROM relation_members m INNER JOIN nodes n ON m.member_id=n.id
	WHERE m.relation_id=e.object_id AND m.member_type='N'
", $db1);

query("
	UPDATE _tmp_ev e
	SET lat=1e7*wn.lat, lon=1e7*wn.lon
	FROM relation_members m INNER JOIN way_nodes wn ON m.member_id=wn.way_id
	WHERE e.lat=0 AND e.lon=0 AND m.relation_id=e.object_id AND m.member_type='W'
", $db1);

$result=query("
	SELECT e.object_id
	FROM _tmp_ev e
	WHERE e.lat=0 AND e.lon=0
", $db1, false);

while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
	$latlong = locate_relation($row['object_id'], $db3);
	if ($latlong['lat']<>'0' && $latlong['lon']<>'0') {
		query("
			UPDATE _tmp_ev e
			SET lat=1e7*{$latlong['lat']}, lon=1e7*{$latlong['lon']}
			WHERE e.schema='$schema' AND e.object_type='relation' AND e.object_id={$row['object_id']} AND e.lat=0 AND e.lon=0
		", $db2, false);
	}
}
pg_free_result($result);
query("INSERT INTO public.error_view SELECT * FROM _tmp_ev", $db1);
query("DROP TABLE IF EXISTS _tmp_ev", $db1, false);




// first insert errors on nodes that don't have lat/lon
query("
	INSERT INTO public.error_view (error_id, db_name, schema, error_type, object_type, object_id,
		state, description, first_occurrence, last_checked, object_timestamp, lat, lon)
	SELECT e.error_id, '$MAIN_DB_NAME', '$schema', e.error_type, e.object_type, e.object_id,
		e.state, e.description, e.first_occurrence, e.last_checked, n.tstamp,
		1e7*n.lat, 1e7*n.lon
	FROM public.errors e INNER JOIN nodes n ON (e.object_id = n.id)
	WHERE e.schema='$schema' AND e.object_type='node' AND state<>'cleared' AND (e.lat IS NULL OR e.lon IS NULL)
		AND n.lat IS NOT NULL AND n.lon IS NOT NULL
", $db1);

// second insert errors on ways that don't have lat/lon
query("
	INSERT INTO public.error_view (error_id, db_name, schema, error_type, object_type, object_id,
		state, description, first_occurrence, last_checked, object_timestamp, lat, lon)
	SELECT e.error_id, '$MAIN_DB_NAME', '$schema', e.error_type, e.object_type, e.object_id,
		e.state, e.description, e.first_occurrence, e.last_checked, w.tstamp,
		1e7*w.first_node_lat AS lat, 1e7*w.first_node_lon AS lon
	FROM public.errors e INNER JOIN ways w ON w.id=e.object_id
	WHERE e.schema='$schema' AND e.object_type='way' AND state<>'cleared' AND (e.lat IS NULL OR e.lon IS NULL)
		AND w.first_node_lat IS NOT NULL AND w.first_node_lon IS NOT NULL
	GROUP BY e.error_id, e.error_type, e.object_type, e.object_id, e.state,
		e.description, e.first_occurrence, e.last_checked, w.tstamp,
		1e7*w.first_node_lat, 1e7*w.first_node_lon
", $db1);


// finally insert errors on ways/nodes/relations that do have lat/lon values
query("
	INSERT INTO public.error_view (error_id, db_name, schema, error_type, object_type, object_id,
		state, description, first_occurrence, last_checked, lat, lon)
	SELECT DISTINCT e.error_id, '$MAIN_DB_NAME' as db_name, e.schema, e.error_type,
		e.object_type, e.object_id, e.state, e.description,
		e.first_occurrence, e.last_checked, e.lat, e.lon
	FROM public.errors e
	WHERE e.schema='$schema' AND state<>'cleared' AND NOT(e.lat IS NULL OR e.lon IS NULL)
", $db1);




// drop errors outside of the scope, if scope is defined
$left=$db_params[$schema]['LEFT'];
$right=$db_params[$schema]['RIGHT'];
$top=$db_params[$schema]['TOP'];
$bottom=$db_params[$schema]['BOTTOM'];
if (isset($left) && isset($right) && isset($top) && isset($bottom)) {

	echo "clipping of errors at boundaries.\n";
	query("
		DELETE FROM public.error_view e
		WHERE e.schema='$schema' AND
		(e.lat<1e7*$bottom OR e.lat>1e7*$top OR
		e.lon<1e7*$left OR e.lon>1e7*$right)
	", $db1);

} else {
	echo "boundaries not specified, skip clipping of errors.\n";
}




// finally add the error names
// first for error types that don't have subtypes...
query("
	UPDATE public.error_view v SET error_name=t.error_name
	FROM error_types t
	WHERE v.schema='$schema' AND (10*floor(v.error_type/10) = t.error_type)
", $db1);
// and second the subtypes (they have individual names)
query("
	UPDATE public.error_view v SET error_name=t.error_name
	FROM error_types t
	WHERE v.schema='$schema' AND v.error_type = t.error_type
", $db1);


foreach (array('node', 'way', 'relation') as $item) {
	query("
		UPDATE public.error_view v SET object_timestamp=t.tstamp
		FROM ${item}s t
		WHERE v.schema='$schema' AND v.object_timestamp='1970-01-01' AND v.object_type='$item' AND t.id=v.object_id
	", $db1);
}


// drop temporary table
//query("DROP TABLE _tmp_errors;", $db1);

echo "-----------------------\n";
print_r($jobreport);
echo "-----------------------\n";


// dump the number of nodes per sware degree
$fname='nodes_'. $schema . '.txt';
$f = fopen($fname, 'w');

if (isset($left) && isset($right) && isset($top) && isset($bottom)) {
	$boundary_clipper="e.lat>=$bottom AND e.lat<=$top AND
		e.lon>=$left AND e.lon<=$right";
} else {
	$boundary_clipper='';
}

if ($f) {
	$result = query("
		SELECT '$schema' AS schema,
		round(e.lat) AS lat, round(e.lon) AS lon, COUNT(e.id) AS cnt
		FROM nodes e
		" . ($boundary_clipper ? "WHERE $boundary_clipper " : '') . "
		GROUP BY round(e.lat), round(e.lon)
	", $db1);

	while ($row=pg_fetch_assoc($result)) {
		fwrite($f, $row['schema'] ."\t". $row['lat'] ."\t". $row['lon'] ."\t". $row['cnt'] . "\n");
	}
	pg_free_result($result);
	fclose($f);

} else {
	echo "dumping of node counts failed. Cannot open $fname for writing\n";
}



// clean up...
drop_postgres_functions($db1);
pg_close($db1);
pg_close($db2);
pg_close($db3);
pg_close($db4);
pg_close($db5);
pg_close($db6);

?>