<?php

// this script will call osmosis to cut a planet file in pieces
// or update pieces cut in a previous step


if (count(get_included_files())<=1) {	// we're running from commandline if not there are already files included

	require_once('helpers.php');
	require_once('../config/config.php');

	if ($argc<=2 || ($argv[1]!=='--update' && $argv[1]!=='--cut')) {
		logger('usage: $ php planet.php --cut planet.pbf schema result.pbf', KR_ERROR);
		logger('usage: $ php planet.php --update schema', KR_ERROR);
		exit(1);
	}

	if ($argv[1]=='--update') planet_update($argv[2], 'update-only');

	if ($argv[1]=='--cut') planet_cut($argv[2], $argv[3]);

}




function planet_cut($planetfile, $schema) {
	global $config;

	if (!file_exists($planetfile)) {

		logger("Main planet file $planetfile not found.", KR_ERROR);

		exit(1);
	}

	$cmd=$config['osmosis_bin'] . ' --rb "' . $planetfile . '" --bb ' . get_bbox_parameters($schema) . ' completeWays=yes completeRelations=yes --wb "' . $config['planet_dir'] . $schema . '.pbf" compress=none';

	shellcmd($cmd, 'osmosis');

	//init_workingDir($schema);
}



function planet_update($schema, $mode='') {
	global $config;

	$planetDirectory=$config['planet_dir'];
	$workingDirectory=$planetDirectory . $schema;
	$planetfile=$planetDirectory . $schema . '.pbf';
	$statefile=$workingDirectory . '/state.txt';

	if (!file_exists($planetfile)) {
		logger("planet file $planetfile not found.", KR_ERROR);
		exit(1);
	}

	if ($config['update_source_data'] || $mode=='update-only') {

		// read replication diffs, apply them on the planet file,
		// write the planet file to disk and create data files
		// using the custom osmosis-plugin "pl"

		// this part would produce the pg simple format we're not using yet:
		//--wpd " . $config['temp_dir'] . " enableBboxBuilder=yes enableLinestringBuilder=yes nodeLocationStoreType=TempFile

		$oldpath=getcwd();
		chdir($planetDirectory);

		$cmd='"' . $config['osmosis_bin'] . '"' .
			' --rri workingDirectory="' . $workingDirectory . '" ' .
			' --simc ' .
			' --rb "' . $planetfile . '" ' .
			' --ac ' .
			' --bb ' . get_bbox_parameters($schema) . ' completeWays=yes completeRelations=yes ';

		if ($mode=='update-only')		// just update the file and store it
			$cmd.=	' --b bufferCapacity=10000 ' .
				' --wb "' . $planetfile . '.new" compress=none ';

		else
			$cmd.=	' --tee 2 ' .		// update the file and create dump files for db loading
				' --b bufferCapacity=10000 ' .
				' --wb "' . $planetfile . '.new" compress=none ' .
				' --b bufferCapacity=10000 ' .
				' --pl directory="' . $config['temp_dir'] . '"';


		copy($statefile, $statefile . '.old');

		$errorlevel = shellcmd($cmd, 'osmosis', false);

		if ($errorlevel) {
			// in case osmosis crashes save the old state file as we will have to start over from there
			copy($statefile . '.old', $statefile);
			exit($errorlevel);
		}

		chdir($oldpath);

		rename($planetfile, $planetfile . '.old');
		rename($planetfile . '.new', $planetfile);

	} else {

		// just convert the planet file to textfiles suitable for db loading

		$oldpath=getcwd();
		chdir($planetDirectory);

		$cmd='"' . $config['osmosis_bin'] . '"' .
			' --rb "' . $planetfile . '" ' .
			' --pl directory="' . $config['temp_dir'] . '"';


		shellcmd($cmd, 'osmosis');
		chdir($oldpath);
	}
}


// make sure the osmosis working directory does exist and config file is right
function init_workingDir($schema) {
	global $config;
	$workingDirectory=$config['planet_dir'] . $schema;

	if (is_dir($workingDirectory)) return;

	mkdir($workingDirectory);

	shellcmd($config['osmosis_bin'] . " --rrii workingDirectory=$workingDirectory", 'osmosis');


	// now fix config file with appropriate URL and without limit of downloading files
	$f=fopen("$workingDirectory/configuration.txt", 'w');
	fwrite($f, "# The URL of the directory containing change files.\n");
	fwrite($f, "baseUrl=http://planet.openstreetmap.org/replication/hour\n\n");
	fwrite($f, "# Defines the maximum time interval in seconds to download in a single invocation.\n");
	fwrite($f, "# Setting to 0 disables this feature.\n");
	fwrite($f, "maxInterval = 0\n");
	fclose($f);


	echo "please download the appropriate state.txt file from http://planet.openstreetmap.org/replication/hour according to the date of your planet file and place it into $workingDirectory/state.txt before updating your planet excerpts\n";
}



// make a lon value reside between -180..+180
function fit_limits_lon($value) {
	if ($value>180.0) return 180.0;
	if ($value<-180.0) return -180.0;
	return $value;
}

// make a lat value reside between -90..+90
function fit_limits_lat($value) {
	if ($value>90.0) return 90.0;
	if ($value<-90.0) return -90.0;
	return $value;
}

// build a string suitable for inserting in an osmosis bbox cutting command
function get_bbox_parameters($schema) {
	global $schemas, $config;

	$m = $config['cutting_margin'];

	$LEFT = fit_limits_lon(merc_lon(merc_x($schemas[$schema]['left']) - $m));
	$RIGHT = fit_limits_lon(merc_lon(merc_x($schemas[$schema]['right']) + $m));
	$TOP = fit_limits_lat(merc_lat(merc_y($schemas[$schema]['top']) + $m));
	$BOTTOM = fit_limits_lat(merc_lat(merc_y($schemas[$schema]['bottom']) - $m));

	return "left=$LEFT top=$TOP right=$RIGHT bottom=$BOTTOM";
}

?>
