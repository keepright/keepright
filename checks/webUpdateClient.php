<?php
/*
script for updating the web presentation

this script file is used to upload dump files via ftp to
your web space provider and load the dumps into the database
It runs locally on your pc and calls webUpdateServer.php
which resides on your webspace (runs on the server)

steps required for a database update:

1) create or empty error_view_XX_shadow table
2) upload bz2 compressed dump files to web space
3) load dump files into MySQL shadow tables
4) toggle tables: rename error_view_XX to error_view_XX_old;
   rename error_view_XX_shadow to error_view_XX
5) re-open errors marked as ingnore-temporarily (use SQL provided
   at the end of run-checks.php)
6) update file updated_XX with date of last database update

*/


echo "argc=$argc\n";
print_r($argv);

if ($argc<2 || ($argv[1]<>'--local' && $argv[1]<>'--remote')) {
	echo "Usage: \"php export_errors.php --local | --remote 17 | --export_comments\"\n";
	echo "will upload dump file 17 created by export_errors.php to the web server\n";
	exit;
}

if ($argv[2]=='--export_comments') $schema=1; else $schema=$argv[2];

require('config.inc.php');
require('helpers.inc.php');


// local/remote operation, choose URL
switch ($argv[1]) {
	case '--local':
		$URL=$UPDATE_TABLES_URL_LOCAL;
	break;
	case '--remote':
		$URL=$UPDATE_TABLES_URL;
	break;
	default:
		echo "unknown upload destination '$argv[1]'\n";
		exit;
}

if ($argv[2]=='--export_comments') {
	echo "exporting comments on server\n";

	$SID=login($URL);
	//echo "session id is $SID";

	if ($SID) {

		$myURL="$URL?cmd=export_comments&PHPSESSID=$SID";

		echo "$myURL\n";
		$result = readHTTP($myURL);
		echo implode("\n", $result);

		logout($URL, $SID);
	}

} else {
	echo "uploading to $URL schema $schema\n";

	$SID=login($URL);
	//echo "session id is $SID";

	if ($SID) {
		if ($argv[1]=='--remote') ftp_upload($MAIN_DB_NAME, $schema);

		$fname="error_view_$schema.txt.bz2";


		$myURL="$URL?schema=$schema&cmd=update&PHPSESSID=$SID" .
			"&updated_date=" . date("Y-m-d") .
			"&error_view_filename=$fname";

		echo "$myURL\n";
		$result = readHTTP($myURL);
		echo implode("\n", $result);

		logout($URL, $SID);
	}
}


// establish a session with the server module
// return the session id on success, 0 on error
function login($URL) {
	global $UPDATE_TABLES_USERNAME, $UPDATE_TABLES_PASSWD;
	echo "\n\nlogging in----------------------------------------------\n\n";

	// call the server script to receive the session id and challenge
	$result1 = readHTTP($URL);
	echo implode("\n", $result1);

	$response=md5($UPDATE_TABLES_USERNAME . trim($result1[1]) . $UPDATE_TABLES_PASSWD);

	// now respond...
	$result2 = readHTTP("$URL?username=$UPDATE_TABLES_USERNAME&response=$response&PHPSESSID=" . trim($result1[2]));
	echo implode("\n", $result2);

	if (trim($result2[0])=="OK welcome!") {
		echo "session id is " . trim($result1[2]) . "\n";
		return trim($result1[2]);		// return Session ID
	} else {
		echo "error logging in\n";
		return 0;
	}
}

function logout($URL, $SID) {
	echo "\n\nlogging out---------------------------------------------\n\n";
	$result = readHTTP("$URL?cmd=logout&PHPSESSID=$SID");
	echo implode("\n", $result);
}


function ftp_upload($db, $schema) {
	global $FTP_USER, $FTP_PASS, $FTP_HOST, $FTP_PATH;
	echo "\n\nuploading dump file-------------------------------------\n\n";

	$ftp_url="ftp://$FTP_USER:$FTP_PASS@$FTP_HOST/$FTP_PATH";

	$filenames="../results/error_view_{$schema}.txt.bz2 ../results/error_types.txt";

	// call wput, overwrite files if already existing, dont create directories
	// upload the error_view dumps and the error_types dump
	system("/usr/bin/wput --timestamping --dont-continue --reupload --binary --no-directories --basename=../results/ $filenames \"$ftp_url\" 2>&1");
}


// opens a http url and reads its contents
// instead of the file() function this one allows
// for an arbitrary timeout value
// copied from http://de.php.net/manual/de/function.stream-set-timeout.php
function readHTTP($URL) {
	// Timeout in seconds
	$timeout = 600;
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