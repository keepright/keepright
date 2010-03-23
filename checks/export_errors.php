<?php

/*
export errors from public.error_view into a text file
*/

if ($argc<3 || ($argv[1]<>'--db' && $argv[1]<>'--schema')) {
	echo "Usage: \">php export_errors.php --db EU | --schema 17\"\n";
	echo "will export entries from error_view into a text dump file\n";
	echo "database credentials are configured in config.";
	echo "You can choose to export a whole database ";
	echo "or a single schema.";
	exit(0);
}

if ($argv[1]=='--db') {
	$MAIN_DB_NAME='osm_' . pg_escape_string($argv[2]);
	$dbschema='public';
} else {
	$schema=pg_escape_string($argv[2]);
	$dbschema='schema' . pg_escape_string($argv[2]);
}

require('config.inc.php');
require('helpers.inc.php');

echo "exporting errors for $MAIN_DB_NAME.$dbschema into dumpfile\n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);

// terminate if no db connection was established
if ($db1 === false) {
	exit(1);
}


$fname=$ERROR_VIEW_FILE .'_'. (isset($schema) ? $schema : $MAIN_DB_NAME) . '.txt';
$f = fopen($fname, 'w');

if (strlen($schema)>0)
	$schemaselector=" AND schema='$schema'";
else
	$schemaselector='';


if ($f) {

	$result = query("
		SELECT *, date_trunc('hour',first_occurrence) AS fo, date_trunc('hour',last_checked) AS lc, date_trunc('second',object_timestamp) AS ts
		FROM public.error_view
		WHERE NOT (state='cleared') $schemaselector
	", $db1);

	while ($row=pg_fetch_assoc($result)) {
		fwrite($f, $row['schema'] ."\t". $row['error_id'] ."\t". $row['db_name'] ."\t". $row['error_type'] ."\t". $row['error_name'] ."\t". $row['object_type'] ."\t". $row['object_id'] ."\t". $row['state'] ."\t". strtr($row['description'], array("\t"=>" ")) ."\t". $row['fo'] ."\t". $row['lc'] ."\t". $row['ts'] ."\t".  $row['lat'] . "\t". $row['lon'] . "\n");
	}
	pg_free_result($result);
	fclose($f);

	if ($CREATE_COMPRESSED_DUMPS<>'0') system("bzip2 -c \"$fname\" > \"$fname.bz2\"");

} else {
	echo "Cannot open error_view file ($filename) for writing";
	exit(1);
}


$fname = $ERROR_TYPES_FILE .'_'. (isset($schema) ? $schema : $MAIN_DB_NAME) . '.txt';
$f = fopen($fname, 'w');

if ($f) {

	// in databases with schemas (like EU and US) there's no public.error_types
	// table. so pick any schema and take the error_types you'll find there
	if (!table_exists($db1, 'error_types', $dbschema)) {
		$dbschema=query_firstval("
			SELECT schemaname
			FROM pg_tables
			WHERE tablename='error_types'
		", $db1, false);
		echo "no error_types table found in current schema, using the one in schema $dbschema instead\n";
	}

	if (strlen($dbschema)>0) {

		$result = query("SELECT * FROM $dbschema.error_types", $db1);
		while ($row=pg_fetch_assoc($result)) {
			fwrite($f, $row['error_type'] ."\t". $row['error_name'] ."\t". strtr($row['error_description'], array("\t"=>" ")) ."\n");
		}
		pg_free_result($result);
		fclose($f);

		if ($CREATE_COMPRESSED_DUMPS<>'0') system("bzip2 -c \"$fname\" > \"$fname.bz2\"");

	} else {
		echo "no error_types table found in any schema. Cannot export error_types.\n";
		exit(1);
	}

} else {
	echo "Cannot open error-types file ($filename) for writing";
	exit(1);
}

pg_close($db1);

exit(0);
?>
