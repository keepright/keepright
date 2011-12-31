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

in case this script doesn't work check your php.ini file on the web server:
session.use_only_cookies should not be set to 1 (the default) because
this script won't accept any cookies

*/

if (count(get_included_files())<=1) {	// we're running from commandline if not there are already files included

	require_once('helpers.php');
	require_once('../config/config.php');


	if ($argc<2 || ($argv[1]<>'--local' && $argv[1]<>'--remote')) {
		echo "Usage: \"php webUpdateClient.php --local 17 | --remote 17 | --export_comments | --get_state\"\n";
		echo "will upload dump file 17 created by export_errors.php to the web server\n";
		exit;
	}


	if ($argv[2]=='--export_comments' || $argv[2]=='--get_state')
		remote_command($argv[1], $argv[2]);
	else
		remote_command($argv[1], '--upload_errors', $argv[2]);

}


// execute a remote command on the webserver
// $location: [ --local | --remote ]
// $cmd: [ --export_comments | --get_state | --upload_errors ]
// $schema: schema number (only required when $cmd==--upload_errors)
function remote_command($location, $cmd, $schema=0) {
	global $config;

	// local/remote operation, choose URL
	switch ($location) {
		case '--local':
			$URL=$config['upload']['url_local'];
		break;
		case '--remote':
			$URL=$config['upload']['url'];
		break;
		default:
			logger("unknown upload destination '$location'", KR_ERROR);
			exit;
	}


	if ($cmd=='--export_comments') {
		echo "exporting comments on server\n";

		$session_ID=login($URL);
		//echo "session id is $session_ID";

		if ($session_ID) {

			$myURL="$URL?cmd=export_comments&PHPSESSID=$session_ID";

			echo "$myURL\n";
			$result = readHTTP($myURL);
			echo implode("\n", $result);

			logout($URL, $session_ID);
		}

	} else if ($cmd=='--get_state') {
		echo "retrieving server state\n";

		$session_ID=login($URL);
		//echo "session id is $session_ID";

		if ($session_ID) {

			$myURL="$URL?cmd=get_state&PHPSESSID=$session_ID";

			echo "$myURL\n";
			$result = readHTTP($myURL);

			logout($URL, $session_ID);

			return unserialize(implode("\n", $result));
		}

	}else {
		echo "uploading to $URL schema $schema\n";

		$session_ID=login($URL);
		//echo "session id is $session_ID";

		if ($session_ID) {
			if ($location=='--remote') ftp_upload($schema);

			$fname="error_view_$schema.txt.bz2";


			$myURL="$URL?schema=$schema&cmd=update&PHPSESSID=$session_ID" .
				"&updated_date=" . date("Y-m-d") .
				"&error_view_filename=$fname";

			echo "$myURL\n";
			$result = readHTTP($myURL);
			echo implode("\n", $result);

			logout($URL, $session_ID);
		}
	}
}




// establish a session with the server module
// return the session id on success, 0 on error
function login($URL) {
	global $config;
	echo "\n\nlogging in----------------------------------------------\n\n";

	// call the server script to receive the session id and challenge
	$result1 = readHTTP($URL);
	echo "result:\n" . implode("\n", $result1) . "\n(end of result)	\n";

	$response=md5($config['account']['user'] . trim($result1[1]) . $config['account']['password']);

	// now respond...
	$result2 = readHTTP("$URL?username=" . $config['account']['user'] . "&response=$response&PHPSESSID=" . trim($result1[2]));
	echo "result:\n" . implode("\n", $result2) . "\n(end of result)	\n";

	if (trim($result2[0])=="OK welcome!") {
		echo "session id is " . trim($result1[2]) . "\n";
		return trim($result1[2]);		// return Session ID
	} else {
		echo "error logging in\n";
		return 0;
	}
}

function logout($URL, $session_ID) {
	echo "\n\nlogging out---------------------------------------------\n\n";
	$result = readHTTP("$URL?cmd=logout&PHPSESSID=$session_ID");
	echo implode("\n", $result);
}


function ftp_upload($schema) {
	global $config;
	echo "\n\nuploading dump file-------------------------------------\n\n";

	$filename="../results/error_view_{$schema}.txt.bz2";
	$remote_file="error_view_{$schema}.txt.bz2";


	// set up basic connection
	$conn_id = ftp_connect($config['upload']['ftp_host']);

	if (!$conn_id) {
		echo "couldn't conect to ftp server " . $config['upload']['ftp_host'] . "\n";
		return;
	}

	// login with username and password
	if (!ftp_login($conn_id, $config['upload']['ftp_user'], $config['upload']['ftp_password'])) {
		echo "Couldn't login to " . $config['upload']['ftp_host'] . " as " . $config['upload']['ftp_user'] ."\n";

		ftp_close($conn_id);
		return;
	}

	// change to destination directory
	if (!ftp_chdir($conn_id, '/' . $config['upload']['ftp_path'])) {
		echo "Couldn't change directory on ftp server\n";

		ftp_close($conn_id);
		return;
	}

	// upload the file
	if (ftp_put($conn_id, $remote_file, $filename, FTP_ASCII)) {
		echo "successfully uploaded $filename\n";
	} else {
		echo "There was a problem while uploading $filename\n";
	}

	// close the connection
	ftp_close($conn_id);
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
			ob_flush();
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