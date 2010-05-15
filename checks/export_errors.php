<?php

/*
export errors from public.error_view into a text file
*/

if ($argc<1) {
	echo "Usage: \">php export_errors.php 17\"\n";
	echo "will export entries from error_view, schema 17 into a text dump file\n";
	echo "database credentials are configured in config.";
	exit(0);
}


$schema=pg_escape_string($argv[1]);
$dbschema='schema' . pg_escape_string($argv[1]);


require('config.inc.php');
require('helpers.inc.php');

echo "exporting errors for $dbschema into dumpfile\n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);

// terminate if no db connection was established
if ($db1 === false) {
	echo "could not establish a database connection\n";
	exit(1);
}


$fname=$ERROR_VIEW_FILE .'_'. $schema . '.txt';
$f = fopen($fname, 'w');

if ($f) {

	$result = query("
		SELECT *, date_trunc('hour',first_occurrence) AS fo, date_trunc('hour',last_checked) AS lc, date_trunc('second',object_timestamp) AS ts
		FROM public.error_view
		WHERE NOT (state='cleared') AND schema='$schema'
		ORDER BY error_id
	", $db1);

	while ($row=pg_fetch_assoc($result)) {
		fwrite($f, $row['schema'] ."\t". $row['error_id'] ."\t". $row['error_type'] ."\t". $row['error_name'] ."\t". $row['object_type'] ."\t". $row['object_id'] ."\t". $row['state'] ."\t". strtr($row['description'], array("\t"=>" ")) ."\t". $row['fo'] ."\t". $row['lc'] ."\t". $row['ts'] ."\t".  $row['lat'] . "\t". $row['lon'] . "\t". $row['msgid'] . "\t". $row['txt1'] . "\t". $row['txt2'] . "\t". $row['txt3'] . "\t". $row['txt4'] . "\t". $row['txt5'] . "\n");
	}
	pg_free_result($result);
	fclose($f);

	if ($CREATE_COMPRESSED_DUMPS<>'0') system("bzip2 -c \"$fname\" > \"$fname.bz2\"");

} else {
	echo "Cannot open error_view file ($filename) for writing";
	exit(1);
}


$fname = $ERROR_TYPES_FILE .'_'. $schema . '.txt';
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
?>
