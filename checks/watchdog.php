<?php

// script for checking health state of keepright

//
// usage:
// php watchdog.php
//
// outputs messages regarding to health-checking rules:
// * file size and modification date of planet files
// * file size and modification date of result files
// * compare file size and modification date of result file with file on webserver
//


require('helpers.php');
require('../config/config.php');
require('webUpdateClient.php');


$serverstate = remote_command('--remote', '--get_state');	// get listing of result files from webserver
//print_r($serverstate);


$issues = array();


foreach ($schemas as $schema=>$schema_cfg) {


	if ($schema=='at' || $schema=='md' || $schema=='50') continue;	// don't check testing schemas and Australia

	$resultfile = 'error_view_' . $schema . '.txt.bz2';
	$resultfile_path = $config['results_dir'] . $resultfile;
	$planetfile = $config['base_dir'] . 'planet/' . $schema . '.pbf';



	// check file size and modification date of planet file

	if (file_exists($planetfile)) {

		$size=filesize($planetfile);
		if ($size<$config['watchdog']['planet_minimum_filesize'])
			$issues[]="planet file for schema $schema is too small. Size is $size";

		$mtime=filemtime($planetfile);
		if ($mtime< time() - $config['watchdog']['schema_max_age'])
			$issues[]="planet file for schema $schema is older than " .
				round($config['watchdog']['schema_max_age']/86400) .
				" days. File date is " . date('d.m.Y H:m:s', $mtime);


	} else $issues[]="planet file for schema $schema not found";



	// check file size and modification date of result file

	// local result file found
	if (file_exists($resultfile_path)) {

		// local result file size
		$size=filesize($resultfile_path);
		if ($size<$config['watchdog']['error_view_minimum_filesize'])
			$issues[]="result file for schema $schema is too small. Size is $size";

		// local result file mtime
		$mtime=filemtime($resultfile_path);
		if ($mtime< time() - $config['watchdog']['schema_max_age'])
			$issues[]="result file for schema $schema is older than " .
				round($config['watchdog']['schema_max_age']/86400) .
				" days. File date is " . date('d.m.Y H:m:s', $mtime);


		// compare file size and modification date of result file with file on webserver

		// remote result file found
		if (array_key_exists($resultfile, $serverstate['files'])) {

			// local vs remote result file size
			if ($size<>$serverstate['files'][$resultfile]['size'])
				$issues[]="result file size (" . $size . ") for schema $schema " .
					"differ with size on web server (" .
					$serverstate['files'][$resultfile]['size'] . ")";

			// local vs remote result file mtime
			if ($mtime>$serverstate['files'][$resultfile]['mtime'])
				$issues[]="result file date (" . date('d.m.Y H:m:s', $mtime) .
					") for schema $schema is newer than version on web server (" .
					date('d.m.Y H:m:s', $serverstate['files'][$resultfile]['mtime']) . ")";

			// check error count from server table	
			if (!($serverstate['files'][$resultfile]['count']>1))
				$issues[]="error_view table seems to be empty for schema $schema";


		} else $issues[]="result file for schema $schema not found on web server";

	} else $issues[]="result file for schema $schema not found on local server";



	// calculate [errors with first_occurrence within last x days]/[errors total] for each error_type
	// and compare with threshhold


}


print_r($issues);
if (count($issues)>0) echo count($issues) . " issues found.\n";

?>