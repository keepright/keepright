<html><body>
<?php include('navigate.php');

/*
script for assistance in updating the web presentation

this script file is used to upload dump files via ftp to
your web space provider and load the dumps into the database
It runs locally on your pc and calls webUpdateServer.php
which resides on your webspace (runs on the server)

steps required for a database update:

1) create or empty error_view_osm_XX_shadow table
2) upload bz2 compressed dump files to web space
3) load dump files into MySQL tables
4) toggle tables: rename error_view_osm_XX to error_view_osm_XX_old;
   rename error_view_osm_XX_shadow to error_view_osm_XX
5) re-open errors marked as ingnore-temporarily (use SQL provided
   at the end of run-checks.php)
6) update file updated_osm_XX with date of last database update
7) update file planetfile_date_osm_XX with date of planet file download
*/
?>

<h3>result files</h3>

<pre>
<?php
	// show a list of dump files available
	system('ls -lh results/error_view_osm_??_*.bz2');
?>
</pre>


<h3>upload results via ftp, load them into the db and activate the new table</h3>
<?php

// establish a session with the server module
// return the session id on success, 0 on error
function login() {
global $UPDATE_TABLES_URL, $UPDATE_TABLES_USERNAME, $UPDATE_TABLES_PASSWD;
	echo "\n\nlogging in----------------------------------------------\n\n";

	// call the server script to receive the session id and challenge
	$result1 = readHTTP($UPDATE_TABLES_URL);
	//echo implode("", $result1) . "\n";

	$response=md5($UPDATE_TABLES_USERNAME . trim($result1[1]) . $UPDATE_TABLES_PASSWD);

	// now respond...
	$result2 = readHTTP("$UPDATE_TABLES_URL?username=$UPDATE_TABLES_USERNAME&response=$response&PHPSESSID=" . trim($result1[2]));

	if (trim($result2[0])=="OK welcome!") {
		echo "session id is " . trim($result1[2]) . "\n";
		return trim($result1[2]);		// return Session ID
	} else {
		echo "error logging in";
		return 0;
	}
}

function logout($SID) {
global $UPDATE_TABLES_URL;
	echo "\n\nlogging out---------------------------------------------\n\n";
	$result = readHTTP("$UPDATE_TABLES_URL?cmd=logout&PHPSESSID=$SID");
	echo implode("", $result) . "\n";
}

function toggle_tables1($SID, $db, $schema) {
global $UPDATE_TABLES_URL;
	echo "\n\ntoggling tables 1---------------------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&schema=$schema&cmd=toggle_tables1&PHPSESSID=$SID";
	echo "$URL\n";
	$result = readHTTP($URL);
	echo implode("", $result) . "\n";
}

function toggle_tables2($SID, $db, $schema) {
global $UPDATE_TABLES_URL;
	echo "\n\ntoggling tables 2---------------------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&schema=$schema&cmd=toggle_tables2&PHPSESSID=$SID";
	echo "$URL\n";
	$result = readHTTP($URL);
	echo implode("", $result) . "\n";
}

function ftp_upload($db) {
global $FTP_USER, $FTP_PASS, $FTP_HOST, $FTP_PATH;
	echo "\n\nuploading dump files------------------------------------\n\n";

	$FTPURL="ftp://$FTP_USER:$FTP_PASS@$FTP_HOST/$FTP_PATH";

	// call wput, overwrite files if already existing, dont create directories
	// upload the error_view dumps and the error_types dump
	system("/usr/bin/wput --timestamping --dont-continue --reupload --binary --no-directories --basename=results/  results/error_view_{$db}.txt.bz2 results/error_types_{$db}.txt \"$FTPURL\" 2>&1");
}

function empty_error_types_table($SID, $db, $schema) {
global $UPDATE_TABLES_URL;
	echo "\n\nemptying error_types table -----------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&schema=$schema&date=$date&cmd=empty_error_types_table&PHPSESSID=$SID";
	echo "$URL\n";
	$result = readHTTP($URL);
	echo implode("", $result) . "\n";
}


function load_dump_helper($SID, $db, $schema, $filename, $destination) {
global $UPDATE_TABLES_URL;
	$WEBURL=$UPDATE_TABLES_URL . "?db=$db&schema=$schema&cmd=load_dump&filename=" . basename($filename) . "&destination=$destination&PHPSESSID=$SID";

	// call updateTables.php script, redirect output to stdout (into the webpage)
	$cmd = '/usr/bin/wget -O - "' . $WEBURL . '" 2>&1';
	echo "$cmd\n";
	system($cmd);
}

// call the server script to load every dump file part (error_view + error_types)
function load_dump($SID, $db, $schema) {
	echo "\n\nloading dump files--------------------------------------\n\n";

	if ($schema!=='%') $dbname=$schema; else $dbname=$db;

	load_dump_helper($SID, $db, $schema, "results/error_view_{$dbname}.txt.bz2", 'error_view');

	empty_error_types_table($SID, $db, $schema);
	// now load the error_types
	load_dump_helper($SID, $db, $schema, "error_types_{$dbname}.txt.bz2", 'error_types');
}


function reopen_errors($SID, $db, $schema, $date) {
global $UPDATE_TABLES_URL;
	echo "\n\nreopening temp.ignored errors---------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&schema=$schema&date=$date&cmd=reopen_errors&PHPSESSID=$SID";
	echo "$URL\n";
	$result = readHTTP($URL);
	echo implode("", $result) . "\n";
}


function set_updated_date($SID, $db, $schema, $date) {
global $UPDATE_TABLES_URL;
	echo "\n\nupdating updated date-----------------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&schema=$schema&date=$date&cmd=set_updated_date&PHPSESSID=$SID";
	echo "$URL\n";
	$result = readHTTP($URL);
	echo implode("", $result) . "\n";
}


function set_planetfile_date($SID, $db, $schema, $date) {
global $UPDATE_TABLES_URL;
	echo "\n\nupdating planet file date-----------------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&schema=$schema&date=$date&cmd=set_planetfile_date&PHPSESSID=$SID";
	echo "$URL\n";
	$result = readHTTP($URL);
	echo implode("", $result) . "\n";
}



// start uploading procedure
if (isset($_POST['isocode']) && strlen($_POST['isocode'])<=4) {

	$db='osm_' . addslashes(htmlspecialchars($_POST['isocode']));

	if (strlen($_POST['schema'])==2)
		$schema='osm_' . addslashes(htmlspecialchars($_POST['schema']));
	else
		$schema='%';

	if (isset($_POST['complete_run'])) {
		echo '<pre>';

		$SID=login();
		//echo "session id is $SID";

		if ($SID) {
			ftp_upload($db);
			toggle_tables1($SID, $db, $schema);
			load_dump($SID, $db, $schema);
			toggle_tables2($SID, $db, $schema);

			$FILE=$db_params[addslashes(htmlspecialchars($_POST['isocode']))]['FILE'];

			if (file_exists("planet/$FILE")) {
				$planetfile_date=date("Y-m-d", filemtime("planet/$FILE"));
				reopen_errors($SID, $db, $schema, $planetfile_date);
				set_planetfile_date($SID, $db, $schema, $planetfile_date);
			} else
				echo "ERROR: planet file 'planet/$FILE' not found. Cannot reopen temp.ignored errors because I cannot determine the date of planet file download\n";

			set_updated_date($SID, $db, $schema, date("Y-m-d"));
			logout($SID);
		}

		echo '</pre>';
	}
}

// opens a http url and reads its contents
// instead of the file() function this one allows
// for an arbitrary timeout value
// copied from http://de.php.net/manual/de/function.stream-set-timeout.php
function readHTTP($URL) {
	// Timeout in seconds
	$timeout = 300;
	$data='';
	$URLparts = parse_url($URL);

	$fp = fsockopen($URLparts['host'], 80, $errno, $errstr, $timeout);

	if ($fp) {
		fwrite($fp, "GET " . $URLparts['path'].'?'.$URLparts['query']. " HTTP/1.0\r\n");
		fwrite($fp, "Host: " . $URLparts['host'] . "\r\n");
		fwrite($fp, "Connection: Close\r\n\r\n");

		stream_set_blocking($fp, TRUE);
		stream_set_timeout($fp,$timeout);
		$info = stream_get_meta_data($fp);

		while ((!feof($fp)) && (!$info['timed_out'])) {
			$data .= fgets($fp, 4096);
			$info = stream_get_meta_data($fp);
			ob_flush;
			flush();
		}

		if ($info['timed_out']) {
			echo "Connection Timed Out!";
		}
	}
	// strip away http response header

	return explode("\n", substr($data, 4+strpos($data, "\r\n\r\n")));
}

?>


<form name="complete_run" action="webUpdateClient.php" method="post">
	<input type="submit" name="complete_run" value="one button does it all">
	<input type="text" name="isocode" size="4" value="EU">
	<input type="text" name="schema" size="4" value="AT">
</form>

</body></html>