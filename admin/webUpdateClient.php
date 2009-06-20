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
6) update file updated.inc with date of planet file download (your job)
7) update log section of index.php and logs.php, if applicable (your job)

*/
?>

<h3>result files</h3>

<pre>
<?php
	// show a list of dump files available
	system('ls -lh results/error_view_osm_??_part??.bz2');
?>
</pre>


<h3>upload results via ftp, load them into the db and activate the new table</h3>
<?php

// establish a session with the server module
// return the session id on success, 0 on error
function login() {
global $UPDATE_TABLES_URL, $UPDATE_TABLES_PASSWD;
	echo "\n\nlogging in----------------------------------------------\n\n";

	// call the server script to receive the session id and challenge
	$result1 = file($UPDATE_TABLES_URL);
	//echo implode("", $result1) . "\n";

	$response=md5(trim($result1[1]) . $UPDATE_TABLES_PASSWD);

	// now respond...
	$result2 = file("$UPDATE_TABLES_URL?response=$response&PHPSESSID=" . trim($result1[2]));
	echo implode("", $result2) . "\n";

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
	$result = file("$UPDATE_TABLES_URL?cmd=logout&PHPSESSID=$SID");
	echo implode("", $result) . "\n";
}

function toggle_tables1($SID, $db) {
global $UPDATE_TABLES_URL;
	echo "\n\ntoggling tables 1---------------------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&cmd=toggle_tables1&PHPSESSID=$SID";
	echo "$URL\n";
	$result = file($URL);
	echo implode("", $result) . "\n";
}

function toggle_tables2($SID, $db) {
global $UPDATE_TABLES_URL;
	echo "\n\ntoggling tables 2---------------------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&cmd=toggle_tables2&PHPSESSID=$SID";
	echo "$URL\n";
	$result = file($URL);
	echo implode("", $result) . "\n";
}

function ftp_upload($db) {
global $FTP_USER, $FTP_PASS, $FTP_HOST, $FTP_PATH;
	echo "\n\nuploading dump files------------------------------------\n\n";

	$FTPURL="ftp://$FTP_USER:$FTP_PASS@$FTP_HOST/$FTP_PATH";

	// call wput, overwrite files if already existing, dont create directories
	// upload the error_view dumps and the error_types dump
	system("/usr/bin/wput --reupload --binary --no-directories --basename=results/  results/error_view_{$db}_part??.bz2 results/error_types_{$db}.txt \"$FTPURL\" 2>&1");
}

function empty_error_types_table($SID, $db) {
global $UPDATE_TABLES_URL;
	echo "\n\nemptying error_types table -----------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&date=$date&cmd=empty_error_types_table&PHPSESSID=$SID";
	echo "$URL\n";
	$result = file($URL);
	echo implode("", $result) . "\n";
}


function load_dump_helper($SID, $db, $filename, $destination) {
global $UPDATE_TABLES_URL;
	$WEBURL=$UPDATE_TABLES_URL . "?db=$db&cmd=load_dump&filename=" . basename($filename) . "&destination=$destination&PHPSESSID=$SID";

	// call updateTables.php script, redirect output to stdout (into the webpage)
	$cmd = '/usr/bin/wget -O - "' . $WEBURL . '" 2>&1';
	echo "$cmd\n";
	system($cmd);
}

// call the server script to load every dump file part (error_view + error_types)
function load_dump($SID, $db) {
	echo "\n\nloading dump files--------------------------------------\n\n";

	// glob == ls
	foreach (glob("results/error_view_{$db}_part??.bz2") as $filename) {
		load_dump_helper($SID, $db, $filename, 'error_view');
	}

	empty_error_types_table($SID, $db);
	// now load the error_types
	load_dump_helper($SID, $db, "error_types_{$db}.txt", 'error_types');
}


function reopen_errors($SID, $db, $date) {
global $UPDATE_TABLES_URL;
	echo "\n\nreopening temp.ignored errors---------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&date=$date&cmd=reopen_errors&PHPSESSID=$SID";
	echo "$URL\n";
	$result = file($URL);
	echo implode("", $result) . "\n";
}


function set_updated_date($SID, $db, $date) {
global $UPDATE_TABLES_URL;
	echo "\n\nupdating updated date-----------------------------------\n\n";
	$URL="$UPDATE_TABLES_URL?db=$db&date=$date&cmd=set_updated_date&PHPSESSID=$SID";
	echo "$URL\n";
	$result = file($URL);
	echo implode("", $result) . "\n";
}


// start uploading procedure
if (isset($_POST['isocode']) && strlen($_POST['isocode'])==2) {

	$db='osm_' . addslashes(htmlspecialchars($_POST['isocode']));

	if (isset($_POST['complete_run'])) {
		echo '<pre>';

		$SID=login();
		//echo "session id is $SID";

		if ($SID) {
			ftp_upload($db);
			toggle_tables1($SID, $db);
			load_dump($SID, $db);
			toggle_tables2($SID, $db);

			$FILE=$db_params[addslashes(htmlspecialchars($_POST['isocode']))]['FILE'];

			if (file_exists("planet/$FILE"))
				reopen_errors($SID, $db, date("Y-m-d", filemtime("planet/$FILE")));
			else
				echo "ERROR: planet file 'planet/$FILE' not found. cannot reopen temp.ignored errors because I cannot determine the date of planet file download\n";

			set_updated_date($SID, $db, date("Y-m-d"));
			logout($SID);
		}

		echo '</pre>';
	}
}

?>


<form name="complete_run" action="webUpdateClient.php" method="post">
	<input type="submit" name="complete_run" value="one button does it all">
	<input type="text" name="isocode" size="2" value="EU">
</form>

</body></html>