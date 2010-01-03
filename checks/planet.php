<?php


if ($argc<2 || ($argv[1]<>'--cut' && $argv[1]<>'--update')) {
	echo "Usage: \">php planet.php --cut planet.osm | --update  [schemas]\"\n";
	echo "this script will call osmosis to cut a planet file in pieces'\n";
	echo "or update pieces cut in a previous step\n";
	exit;
}

$dont_care_about_missing_db_parameters=true;
require('config.inc.php');
require('helpers.inc.php');


switch ($argv[1]) {

	case '--cut':
		$cmd="$OSMOSIS_BIN --rx {$argv[2]} --tee " . ($argc-3);
		for ($i=3;$i<$argc;$i++) {
			$cmd .= " --bb " . get_bbox_parameters($argv[$i]) . " idTrackerType=BitSet completeWays=yes completeRelations=yes --wx $TMPDIR/{$argv[$i]}.osm ";
			echo "$cmd\n";
			system($cmd, $errorlevel);
			if ($errorlevel) exit;

			init_workingDir($argv[$i]);
		}

	break;
	case '--update':
		for ($i=2;$i<$argc;$i++) {
			$workingDirectory=$file="$TMPDIR/{$argv[$i]}";

			init_workingDir($argv[$i]);

			$cmd="$OSMOSIS_BIN --rri workingDirectory=$workingDirectory " .
			" --simplify-change --rx $file.osm --ac --bb " . get_bbox_parameters($argv[$i]) . " idTrackerType=BitSet completeWays=yes completeRelations=yes --wx $file.osm.new ";
			echo "$cmd\n";
			system($cmd, $errorlevel);
			if ($errorlevel) exit;

			if (file_exists("$file.osm.old")) unlink("$file.osm.old");
			rename("$file.osm", "$file.osm.old");
			rename("$file.osm.new", "$file.osm");
		}
	break;
}


// make sure the osmosis working directory does exist and config file is right
function init_workingDir($schema) {
	global $TMPDIR, $OSMOSIS_BIN;
	$workingDirectory="$TMPDIR/$schema";

	if (!is_dir($workingDirectory)) {
		mkdir($workingDirectory);

		$cmd="$OSMOSIS_BIN --rrii workingDirectory=$workingDirectory";
		echo "$cmd\n";
		system($cmd, $errorlevel);
		if ($errorlevel) exit;


		// now fix config file with appropriate URL and without limit of downloading files
		$f=fopen("$workingDirectory/configuration.txt", 'w');
		fwrite($f, "# The URL of the directory containing change files.\n");
		fwrite($f, "baseUrl=http://planet.openstreetmap.org/hour-replicate\n\n");
		fwrite($f, "# Defines the maximum time interval in seconds to download in a single invocation.\n");
		fwrite($f, "# Setting to 0 disables this feature.\n");
		fwrite($f, "maxInterval = 0\n");
		fclose($f);

		echo "please download the appropriate state.txt file from http://planet.openstreetmap.org/hour-replicate/ according to the date of your planet file and place it into $workingDirectory/state.txt before updating your planet excerpts\n";
	}
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
	global $db_params, $MARGIN;

	$LEFT = fit_limits_lon(merc_lon(merc_x($db_params[$schema]['LEFT']) - $MARGIN));
	$RIGHT = fit_limits_lon(merc_lon(merc_x($db_params[$schema]['RIGHT']) + $MARGIN));
	$TOP = fit_limits_lat(merc_lat(merc_y($db_params[$schema]['TOP']) + $MARGIN));
	$BOTTOM = fit_limits_lat(merc_lat(merc_y($db_params[$schema]['BOTTOM']) - $MARGIN));

	return "left=$LEFT top=$TOP right=$RIGHT bottom=$BOTTOM";
}

?>
