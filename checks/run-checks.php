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

require('config.inc.php');
require('helpers.inc.php');
require('BufferedInserter.php');


echo "Running checks for $db_postfix\n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db2 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db3 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db4 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db5 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db6 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);



// (re)create temporary errors-table
// check routines drop their errors into _tmp_errors. A syncing-job updates state information
// in the "real" errors-table at the end of this script

if (!pg_exists($db1, 'type', 'type_error_state'))
	query("CREATE TYPE type_error_state AS ENUM('new','cleared','ignored','reopened')", $db1, false);

if (!pg_exists($db1, 'type', 'type_object_type'))
	query("CREATE TYPE type_object_type AS ENUM('node','way','relation')", $db1, false);

query("DROP TABLE IF EXISTS _tmp_errors", $db1, false);
query("
	CREATE TABLE _tmp_errors (
	error_type int NOT NULL,
	object_type type_object_type NOT NULL,
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
if (!pg_exists($db1, 'tables', 'errors')) {
	query("
		CREATE TABLE errors (
		error_id serial,
		error_type int NOT NULL,
		object_type type_object_type NOT NULL,
		object_id bigint NOT NULL,
		state type_error_state NOT NULL,
		description text NOT NULL,
		first_occurrence timestamp NOT NULL,
		last_checked timestamp NOT NULL,
		lat double precision,
		lon double precision,
		UNIQUE (error_type, object_type, object_id, lat, lon)
		)
	", $db1, false);
	query("CREATE INDEX idx_errors_object_id ON errors (object_id);", $db1);
	query("CREATE INDEX idx_errors_state ON errors (state);", $db1);
	add_insert_ignore_rule('errors', array('error_type', 'object_type', 'object_id', 'lat', 'lon'), $db1);
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
			VALUES(" . addslashes($error_type) . ",'" . addslashes($error['NAME']) . "','" . addslashes($error['DESCRIPTION']) . "')
		", $db1, false);

		// insert any subtype if some exist
		if (is_array($error['SUBTYPES'])) foreach ($error['SUBTYPES'] as $subtype_id=>$subtype) {
			query("
				INSERT INTO error_types(error_type, error_name, error_description) 
				VALUES(" . addslashes($subtype_id) . ",'" . addslashes($subtype) . "','" . addslashes($error['DESCRIPTION']) . "')
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

// update last-checked timestamp for all errors that (still) exist
query("CREATE INDEX idx_tmp_errors_object_id ON _tmp_errors (object_id);", $db1);
query("CREATE INDEX idx_tmp_errors_object_type ON _tmp_errors (object_type);", $db1);
query("CREATE INDEX idx_tmp_errors_error_type ON _tmp_errors (error_type);", $db1);
query("
	UPDATE errors AS e
	SET last_checked=_tmp_errors.last_checked, description=_tmp_errors.description
	FROM _tmp_errors
	WHERE ($checks_executed) AND e.error_type=_tmp_errors.error_type AND e.object_type=_tmp_errors.object_type AND e.object_id=_tmp_errors.object_id AND e.lat IS NOT DISTINCT FROM _tmp_errors.lat AND e.lon IS NOT DISTINCT FROM _tmp_errors.lon
", $db1);

// set reopened-state for cleared errors that are now found in _tmp_errors again
query("
	UPDATE errors e
	SET state='reopened', description=_tmp_errors.description
	FROM _tmp_errors
	WHERE e.state='cleared' AND ($checks_executed) AND e.error_type=_tmp_errors.error_type AND e.object_type=_tmp_errors.object_type AND e.object_id=_tmp_errors.object_id AND e.lat IS NOT DISTINCT FROM _tmp_errors.lat AND e.lon IS NOT DISTINCT FROM _tmp_errors.lon
", $db1);

// set cleared-state for errors that are not found in _tmp_errors any more
query("
	UPDATE errors e
	SET state='cleared', last_checked=NOW()
	WHERE e.state<>'cleared' AND ($checks_executed) AND
	NOT EXISTS (SELECT * FROM _tmp_errors WHERE e.error_type=_tmp_errors.error_type AND e.object_type=_tmp_errors.object_type AND e.object_id=_tmp_errors.object_id AND e.lat IS NOT DISTINCT FROM _tmp_errors.lat AND e.lon IS NOT DISTINCT FROM _tmp_errors.lon)
", $db1);

query("
	INSERT INTO errors (error_type, object_type, object_id, state, description, first_occurrence, last_checked, lat, lon)
	SELECT e.error_type, e.object_type, e.object_id, CAST('new' AS type_error_state), e.description, e.last_checked, e.last_checked, e.lat, e.lon
	FROM _tmp_errors AS e LEFT JOIN errors ON (e.error_type=errors.error_type AND e.object_type=errors.object_type AND e.object_id=errors.object_id AND e.lat IS NOT DISTINCT FROM errors.lat AND e.lon IS NOT DISTINCT FROM errors.lon)
	WHERE errors.object_id IS NULL AND ($checks_executed)
", $db1);



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


query("DROP TABLE IF EXISTS error_view", $db1, false);
query("
	CREATE TABLE error_view (
	error_id int NOT NULL,
	db_name VARCHAR(50) NOT NULL,
	error_type int NOT NULL,
	error_name VARCHAR(100) NOT NULL DEFAULT '',
	object_type type_object_type NOT NULL,
	object_id bigint NOT NULL,
	state type_error_state NOT NULL,
	description text NOT NULL,
	first_occurrence timestamp NOT NULL,
	last_checked timestamp NOT NULL,
	lat int NOT NULL,
	lon int NOT NULL
	)
", $db1, false);


// first insert errors on nodes that don't have lat/lon
query("
	INSERT INTO error_view (error_id, db_name, error_type, object_type, object_id,
		state, description, first_occurrence, last_checked, lat, lon)
	SELECT e.error_id, '$MAIN_DB_NAME' as db_name, e.error_type, e.object_type, e.object_id,
		e.state, e.description, e.first_occurrence, e.last_checked,
		1e7*n.lat, 1e7*n.lon
	FROM errors e INNER JOIN nodes n ON (e.object_id = n.id)
	WHERE e.object_type='node' AND (e.lat IS NULL OR e.lon IS NULL)
		AND n.lat IS NOT NULL AND n.lon IS NOT NULL
", $db1);

// second insert errors on ways that don't have lat/lon
query("
	INSERT INTO error_view (error_id, db_name, error_type, object_type, object_id,
		state, description, first_occurrence, last_checked, lat, lon)
	SELECT e.error_id, '$MAIN_DB_NAME' as db_name, e.error_type, e.object_type, e.object_id,
		e.state, e.description, e.first_occurrence, e.last_checked,
		1e7*w.first_node_lat AS lat, 1e7*w.first_node_lon AS lon
	FROM errors e INNER JOIN ways w ON w.id=e.object_id
	WHERE e.object_type='way' AND (e.lat IS NULL OR e.lon IS NULL)
		AND w.first_node_lat IS NOT NULL AND w.first_node_lon IS NOT NULL
	GROUP BY e.error_id, e.error_type, e.object_type, e.object_id, e.state,
		e.description, e.first_occurrence, e.last_checked,
		1e7*w.first_node_lat, 1e7*w.first_node_lon
", $db1);

// now find location for relations
$result=query("
	SELECT DISTINCT e.error_id, e.error_type, e.object_id, e.state, e.description,
		e.first_occurrence, e.last_checked
	FROM errors e
	WHERE e.object_type='relation' AND (e.lat IS NULL OR e.lon IS NULL)
", $db1, false);

while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
	$latlong = locate_relation($row['object_id'], $db3);
	query("
		INSERT INTO error_view (error_id, db_name, error_type, object_type, object_id,
			state, description, first_occurrence, last_checked, lat, lon)
		VALUES (${row['error_id']}, '$MAIN_DB_NAME', '${row['error_type']}',
			'relation', ${row['object_id']}, '${row['state']}',
			'" . addslashes($row['description']) . "', '${row['first_occurrence']}',
			'${row['last_checked']}', 1e7*${latlong['lat']}, 1e7*${latlong['lon']})
	", $db2, false);
}
pg_free_result($result);



// finally insert errors on ways/nodes/relations that do have lat/lon values
query("
	INSERT INTO error_view (error_id, db_name, error_type, object_type, object_id,
		state, description, first_occurrence, last_checked, lat, lon)
	SELECT DISTINCT e.error_id, '$MAIN_DB_NAME' as db_name, e.error_type,
		e.object_type, e.object_id, e.state, e.description,
		e.first_occurrence, e.last_checked, e.lat, e.lon
	FROM errors e
	WHERE NOT(e.lat IS NULL OR e.lon IS NULL)
", $db1);




// finally add the error names
// first for error types that don't have subtypes...
query("
	UPDATE error_view v SET error_name=t.error_name
	FROM error_types t
	WHERE (10*floor(v.error_type/10) = t.error_type)
", $db1);
// and second the subtypes (they have individual names)
query("
	UPDATE error_view v SET error_name=t.error_name
	FROM error_types t
	WHERE v.error_type = t.error_type
", $db1);


// drop temporary table
//query("DROP TABLE _tmp_errors;", $db1);

echo "-----------------------\n";
print_r($jobreport);
echo "-----------------------\n";


/*
// draw a statistic about error counts
$result = query("
	SELECT et.error_type, et.error_name,
		(SELECT COUNT(*) FROM errors e WHERE 10*FLOOR(e.error_type/10)=et.error_type) as total,
		(SELECT COUNT(*) FROM errors e WHERE 10*FLOOR(e.error_type/10)=et.error_type AND e.last_checked<>e.first_occurrence and state=CAST('new' AS type_error_state)) as persistent,
		(SELECT COUNT(*) FROM errors e WHERE 10*FLOOR(e.error_type/10)=et.error_type AND e.last_checked=e.first_occurrence and state=CAST('new' AS type_error_state)) as new,
		(SELECT COUNT(*) FROM errors e WHERE 10*FLOOR(e.error_type/10)=et.error_type AND state=CAST('cleared' AS type_error_state)) as closed,
		(SELECT COUNT(*) FROM errors e WHERE 10*FLOOR(e.error_type/10)=et.error_type AND state=CAST('reopened' AS type_error_state)) as reopened
	FROM error_types AS et
	ORDER BY et.error_type
", $db1, false);
$sumT=$sumP=$sumN=$sumC=$sumR=0;
echo "<table><tr><th>error type</th><th>total errors</th><th>persistent erros</th><th>new errors</th><th>cleared errors</th><th>reopened errors</th></tr>";
while ($row=pg_fetch_assoc($result)) {
	$sumT+=$row['total'];
	$sumP+=$row['persistent'];
	$sumN+=$row['new'];
	$sumC+=$row['closed'];
	$sumR+=$row['reopened'];
	echo "<tr><td>{$row['error_name']}</td><td>{$row['total']}</td><td>{$row['persistent']}</td><td>{$row['new']}</td><td>{$row['closed']}</td><td>{$row['reopened']}</td></tr>\n";	
}
echo "<tr><td>total</td><td>$sumT</td><td>$sumP</td><td>$sumN</td><td>$sumC</td><td>$sumR</td><tr></table>\n";
pg_free_result($result);



/*
select error_type, state, last_checked>'2009-03-09' as recently, count(error_id)
from error_view
group by error_type, state, recently
order by recently, state, error_type
*/


/*
UPDATE comments_osm_EU inner join error_view_osm_EU using (error_id)
SET comments_osm_EU.state=null,
comments_osm_EU.comment=CONCAT("[error still open, 2009-05-26] ", comments_osm_EU.comment)
WHERE comments_osm_EU.state='ignore_temporarily' AND
error_view_osm_EU.state<>'cleared' AND
comments_osm_EU.timestamp<"2009-05-26"
*/


echo "Exporting result tables into dump files\n";
$f = fopen($ERROR_VIEW_FILE . '_'. $MAIN_DB_NAME . '.txt', 'w');

if ($f) {

	$result = query("
		SELECT * FROM error_view
		WHERE description NOT LIKE '%kms:%'
		AND NOT (state='cleared' AND last_checked < CURRENT_DATE - INTERVAL '2 MONTH')
	", $db1, false);
	while ($row=pg_fetch_assoc($result)) {
		fwrite($f, $row['error_id'] .",'". $row['db_name'] ."',". $row['error_type'] .",'". $row['error_name'] ."','". $row['object_type'] ."',". $row['object_id'] .",'". $row['state'] ."','". strtr($row['description'], array("'"=>"\'")) ."','". $row['first_occurrence'] ."','". $row['last_checked'] ."',".  $row['lat'] . ",". $row['lon'] . "\n");
	}
	pg_free_result($result);
	fclose($f);

} else {
	echo "Cannot open error_view file ($filename) for writing";
}


$f = fopen($ERROR_TYPES_FILE . '_'. $MAIN_DB_NAME . '.txt', 'w');

if ($f) {

	$result = query('SELECT * FROM error_types', $db1, false);
	while ($row=pg_fetch_assoc($result)) {
		fwrite($f, $row['error_type'] .",'". $row['error_name'] ."','". strtr($row['error_description'], array("'"=>"\'")) ."'\n");
	}
	pg_free_result($result);
	fclose($f);

} else {
        echo "Cannot open error-types file ($filename) for writing";
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
