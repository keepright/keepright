<?php

/*
export errors from public.error_view into a text file
*/


if (count(get_included_files())<=1) {	// we're running from commandline if not there are already files included

	require_once('helpers.php');
	require_once('../config/config.php');

	if ($argc<1) {
		echo ("Usage: \">php export_errors.php 17\"\n" .
			"will export entries from error_view, schema 17 into a text dump file\n" .
			"database credentials are configured in config.php.");
		exit(1);
	}


	export_errors(pg_escape_string($argv[1]));
}




function export_errors($schema) {
	global $config;

	$dbschema='schema' . $schema;

	logger("exporting errors for $dbschema into dumpfile");

	$db1 = pg_pconnect(connectstring($schema));

	// terminate if no db connection was established
	if ($db1 === false) {
		logger("could not establish a database connection", KR_ERROR);
		exit(1);
	}

	$fname=$config['results_dir'] . 'error_view_' . $schema . '.txt';
	$f = fopen($fname, 'w');

	if ($f) {

		$result = query("
			SELECT *, date_trunc('hour',first_occurrence) AS fo, date_trunc('hour',last_checked) AS lc, date_trunc('second',object_timestamp) AS ts
			FROM public.error_view
			WHERE NOT (state='cleared') AND schema='$schema'
			ORDER BY error_id
		", $db1);

		while ($row=pg_fetch_assoc($result)) {
			fwrite($f, smooth_text($row['schema'] ."\t". $row['error_id'] ."\t". $row['error_type'] ."\t". $row['error_name'] ."\t". $row['object_type'] ."\t". $row['object_id'] ."\t". $row['state'] ."\t". strtr($row['description'], array("\t"=>" ")) ."\t". $row['fo'] ."\t". $row['lc'] ."\t". $row['ts'] ."\t".  $row['lat'] . "\t". $row['lon'] . "\t". $row['msgid'] . "\t". $row['txt1'] . "\t". $row['txt2'] . "\t". $row['txt3'] . "\t". $row['txt4'] . "\t". $row['txt5']) . "\n");
		}
		pg_free_result($result);
		fclose($f);

		system("bzip2 -c \"$fname\" > \"$fname.bz2\"");

	} else {
		echo "Cannot open error_view file ($fname) for writing";
		exit(1);
	}

	pg_close($db1);
}



// remove any newline characters
function smooth_text($txt) {
	return strtr($txt, array("\r\n"=>' ', "\r"=>' ', "\n"=>' '));
}


?>
