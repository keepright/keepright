<?php

/*
export errors
*/

if ($argc<2) {
	echo "Usage: \">php export_errors.php AT\"";
	echo "will export entries from error_view into a text dump file\n";
	echo "database credentials are configured in config";
	exit;
}

require('config.inc.php');
require('helpers.inc.php');
require('BufferedInserter.php');


echo "exporting errors for $db_postfix into dumpfile\n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);

$schema=$db_params[$db_postfix]['MAIN_SCHEMA_NAME'];

$fname=$ERROR_VIEW_FILE .'_'. ((strlen($schema)>0 && $schema!='public') ? $schema : $MAIN_DB_NAME) . '.txt';
$f = fopen($fname, 'w');
echo "schema: $schema\n";

if (strlen($schema)>0 && $schema!='public')
	$schemaselector=" AND schema='$schema'";
else
	$schemaselector='';


if ($f) {

	$result = query("
		SELECT *, date_trunc('hour',first_occurrence) AS fo, date_trunc('hour',last_checked) AS lc
		FROM error_view
		WHERE description NOT LIKE '%kms:%'
		AND NOT (state='cleared' AND last_checked < CURRENT_DATE - INTERVAL '1 MONTH') $schemaselector
	", $db1);
	while ($row=pg_fetch_assoc($result)) {
		fwrite($f, $row['schema'] ."\t". $row['error_id'] ."\t". $row['db_name'] ."\t". $row['error_type'] ."\t". $row['error_name'] ."\t". $row['object_type'] ."\t". $row['object_id'] ."\t". $row['state'] ."\t". strtr($row['description'], array("\t"=>" ")) ."\t". $row['fo'] ."\t". $row['lc'] ."\t".  $row['lat'] . "\t". $row['lon'] . "\n");
	}
	pg_free_result($result);
	fclose($f);

	if ($CREATE_COMPRESSED_DUMPS<>'0') system("bzip2 -c \"$fname\" > \"$fname.bz2\"");

} else {
	echo "Cannot open error_view file ($filename) for writing";
}


$fname = $ERROR_TYPES_FILE .'_'. ((strlen($schema)>0 && $schema!='public') ? $schema : $MAIN_DB_NAME) . '.txt';
$f = fopen($fname, 'w');

if ($f) {

	// in databases with schemas (like EU and US) there's no public.error_types
	// table. so pick any schema and take the error_types you'll find there
	if (!table_exists($db1, 'error_types', $schema)) {
		$schema=query_firstval("
			SELECT schemaname
			FROM pg_tables
			WHERE tablename='error_types'
		", $db1, false);
		echo "no error_types table found in current schema, using the one in schema $schema instead\n";
	}

	$result = query("SELECT * FROM $schema.error_types", $db1);
	while ($row=pg_fetch_assoc($result)) {
		fwrite($f, $row['error_type'] ."\t". $row['error_name'] ."\t". strtr($row['error_description'], array("\t"=>" ")) ."\n");
	}
	pg_free_result($result);
	fclose($f);

	if ($CREATE_COMPRESSED_DUMPS<>'0') system("bzip2 -c \"$fname\" > \"$fname.bz2\"");

} else {
	echo "Cannot open error-types file ($filename) for writing";
}

pg_close($db1);
?>
