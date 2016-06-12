<?php

// keepright main entrance script

// script for updating multiple keepright databases
//
// usage:
// php main.php [starting-schema]
// make keepright check one or more databases and upload the results.
// the script runs in a loop until a file with name $config['stop_indicator'] is found.
// optionally provide a schema name where to start. This is useful
// for restarting at a given position in the loop
//

require('helpers.php');
require('../config/config.php');


// leave program if not all prerequisites are fulfilled
if (check_prerequisites()) exit(1);

// TODO: eliminate every single "echo" statement and replace it with logger()-calls

$lastsuccessfulschema=file_get_contents($config['base_dir'] . 'logs/lastsuccessfulschema');

if ($argc==2) $startschema=$argv[1]; else {	// startschema given on command line

	if ($lastsuccessfulschema!==false && array_key_exists($lastsuccessfulschema, $schemas)) {

		$foundlast=false;		// find the last successfully processed schema of this user and proceed to the next one to find $startschema

		foreach ($schemas as $schema=>$schema_cfg) {

			if (($schema_cfg['user'] == $config['account']['user']) &&
				$foundlast) {

				$startschema=$schema;		// we are finished
				break;
			}

			if (($schema_cfg['user'] == $config['account']['user']) &&
				$schema==$lastsuccessfulschema) {

				$foundlast=true;		// remember if last successfully processed schema was reached
			}
		}
	}
	else {

		$startschema=0;			// default: start with the first schema in the list
	}
}

$firstrun=true;
$processed_a_schema=false;

foreach ($schemas as $schema=>$schema_cfg) {

	if (($schema_cfg['user'] == $config['account']['user']) &&
		(!$firstrun || $schema==$startschema || $startschema==0)) {

		// update source code from svn (switch up to base directory)
		$oldpath=getcwd();
		chdir($config['base_dir']);
		system('svn up');
		chdir($oldpath);


		logger("processing schema $schema");

		log_rotate($schema);

		// call process_schema.php
		system('php "' . $config['base_dir'] . 'checks/process_schema.php" "' . $schema .
			'" > "' . $config['base_dir'] . 'logs/' . $schema . '.log" 2>&1');


		// remember the schema number of the last successfully processed schema for restart		
		file_put_contents($config['base_dir'] . 'logs/lastsuccessfulschema', $schema); 

		$firstrun=false;
		$processed_a_schema=true;

	}

	if (file_exists($config['stop_indicator'])) {
		logger('stopping keepright as requested');
		exit;
	}
}



if (!$processed_a_schema) {
	logger('Didn\'t find a single schema to process. Stopping keepright.');
	exit;
}



// perform database maintenance on error tables only once per complete loop
// FULL vacuuming necessary on 'error_view' only; in 'errors' rows are never dropped
$db1 = pg_pconnect(connectstring(), PGSQL_CONNECT_FORCE_NEW);
query("VACUUM ANALYZE public.errors", $db1);
query("VACUUM FULL ANALYZE public.error_view", $db1);
pg_close($db1);



// restart yourself
// this clumsy way of building an infinite loop allows for switching to
// a new version of the running code in case of svn updates
if (platform()=='Linux') {

	system('(php ' . $config['base_dir'] . 'checks/main.php &) >> "' . $config['main_logfile'] . '"');

} else {
	// http://de2.php.net/manual/en/function.exec.php#43917
	$WshShell = new COM('WScript.Shell');
	$oExec = $WshShell->Run('cmd /S %windir% /C php "' .
		$config['base_dir'] . 'checks/main.php" >> "' .
		$config['main_logfile'] . '"', 0, false);
}


// rename logfiles for a given schema so that up to $config['logfile_count'] versions
// of the log remain in the logs directory
function log_rotate($schema) {
	global $config;

	$logfile=$config['base_dir'] . 'logs/' . $schema . '.log';

	if (!file_exists($logfile)) return 0;	// nothing to do

	for ($level=$config['logfile_count']; $level>0; $level--) {

		if (file_exists($logfile . '.' . $level . '.bz2')) {
			rename($logfile . '.' . $level . '.bz2', $logfile . '.' . ($level+1) . '.bz2');
		}
	}

	// compress the latest logfile
	system('bzip2 -c "' . $logfile . '" > "' . $logfile . '.1.bz2"');
}

?>
