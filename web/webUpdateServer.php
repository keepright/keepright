<?php
/*
this script does the complete job of updating the web presentation
with new data on the webserver
Call this script from your client pc using webUpdateClient.php

* authenticate session
* upload dump files
* toggle tables (rename _old to _shadow and empty it)
* load dump files
* toggle tables (rename error_view to error_view_old and _shadow to error_view)
* update updated_[schema] file (date of last site update)
* re-open temporarily ignored errors
*/

ini_set('default_charset', 'iso-8859-1');
ini_set('session.use_cookies', 0);	// never use cookies
ini_set('session.use_only_cookies', 0);	// never use cookies
ini_set('session.gc_maxlifetime', 1800);// 30 minutes as max. session lifetime
session_cache_limiter('nocache');	// disallow caching this page for proxies

require('webconfig.inc.php');
require('helpers.inc.php');
require('BufferedInserter_MySQL.php');

session_start();

$user=$USERS[$_GET['username']];

// enforce people to change their password
if (strlen($_GET['username'])>0)
if ($user['password']=="shhh!" || strlen($user['password'])==0) {
	echo "Password not yet configured. Please change your password in webconfig.inc.php";
	exit;
}

// handle calls that don't provide a session id (the first call for each session)
if (empty($_SESSION['authorized']) && empty($_GET['response'])) {

	// create a new challenge (a random value)
	if (empty($_SESSION['challenge'])) $_SESSION['challenge'] = md5(rand(1e5,1e12));
	echo "not authorized\n";
	echo $_SESSION['challenge'] . "\n";
	echo htmlspecialchars(session_id()) . "\n";
}

// handle login calls
if ($_SESSION['authorized'] !== true && !empty($_GET['response']) && !empty($_GET['username']))  {

	// check authenticity of response
	if ($_GET['response'] === md5($_GET['username'] . $_SESSION['challenge'] . $user['password'])) {
		$_SESSION['authorized']=true;
		$_SESSION['username']=$_GET['username'];
		echo "OK welcome!\n";
	} else {
		echo "invalid response\n";
		$_SESSION['challenge'] = md5(rand(1e5,1e12));	// make a new challenge. People should not be able to have as many tries as they want.
		echo $_SESSION['challenge'] . "\n";
		echo htmlspecialchars(session_id()) . "\n";
	}
}

$schema=addslashes($_GET['schema']);

// handle commands for logged in users
if ($_SESSION['authorized']===true) {

	if ($_GET['cmd'] == 'prepare_update') {		// create shadow table ready to receive conents of one or more dump files

		if (!permissions($USERS[$_SESSION['username']], $schema)) {
			die("you are not authorized to access schema $schema\n");
		}

		// remove old dump files
		// this is essential in case the new dump has less files than the old one
		// special precautions here: deleting files on user input!
		// $schema has up to three chars
		foreach (glob('error_view_' . escapeshellcmd(substr($_GET['schema'], 0, 3)) . '.*.txt.bz2') as $fname) {
			unlink($fname);
		}

		$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
		toggle_tables1($db1, $schema);
		mysqli_close($db1);
	}

	if ($_GET['cmd'] == 'load_dump') {		// load one part of the dump file (call repeatedly in case the dump file has more than one part

		if (!permissions($USERS[$_SESSION['username']], $schema)) {
			die("you are not authorized to access schema $schema\n");
		}

		$error_view_filename=escapeshellarg($_GET['error_view_filename']);
		if (!file_exists(substr($error_view_filename, 1, -1))) {
			echo "$error_view_filename does not exist on web server\n";
			exit;
		}

		$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
		load_dump($db1, $error_view_filename, 'error_view', $schema);
		mysqli_close($db1);
	}

	if ($_GET['cmd'] == 'finish_update') {		// toggle back tables, make shadow table visible

		if (!permissions($USERS[$_SESSION['username']], $schema)) {
			die("you are not authorized to access schema $schema\n");
		}

		$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

		toggle_tables2($db1, $schema);
		reopen_errors($db1, $schema);

		// set_updated_date
		write_file("updated_$schema", addslashes($_GET['updated_date']));

		mysqli_close($db1);
	}

	if ($_GET['cmd'] == 'export_comments') {
		$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
		export_comments($db1);
		mysqli_close($db1);
	}


	if ($_GET['cmd'] == 'get_state') {
		$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
		get_state($db1);
		mysqli_close($db1);
	}


	if ($_GET['cmd'] == 'logout') {
		logout();
	}
}


function logout() {
	echo "logout.\n";
	// Unset all of the session variables and destroy the session.
	global $_SESSION;
	$_SESSION = array();
	session_destroy();
	echo "session closed.\n";
}

// check if a given schema name is found in the users permissions array
// which is configured in $USERS in webconfig.inc.php
function permissions($user, $schema) {

	if (in_array('%', $user['schemata'], true))
		return true;			// privileges for any schema
	else
		return (in_array($schema, $user['schemata'], true));	// a given schema
}

// create a dump file containing all comments
function export_comments($db1) {
	global $comments_name;
	$fname=$comments_name . '.txt';
	$f = fopen($fname, 'w');

	if ($f) {
		$result=query("
			SELECT `schema`, error_id, state, comment, timestamp
			FROM $comments_name
			WHERE `schema` IS NOT NULL AND `schema` != \"\"
			ORDER BY `schema`, error_id
		", $db1, false);

		while ($row = mysqli_fetch_assoc($result)) {
			fwrite($f,  smooth_text($row['schema'] ."\t". $row['error_id'] ."\t". $row['state'] ."\t". strtr($row['comment'], array("\t"=>" ", "\r\n"=>"<br>", "\n"=>"<br>")) ."\t". $row['timestamp']) . "\n");
		}

		mysqli_free_result($result);
		fclose($f);
		system("bzip2 --force $fname");
	}
}


// create a status page
// including file names, sizes and mod dates of all error_view dump files
function get_state($db) {

	$result=array();
	$result['files']=array();

	// list all error_view files
	$dir=glob("error_view*.txt.bz2");

	foreach ($dir as $filename) {
		$result['files'][$filename] = array(
			'size'=>filesize($filename),
			'mtime'=>filemtime($filename),
			'count'=>count_star($db, substr($filename, 0, strpos($filename, '.')))		// length := position of the dot
		);
	}

	echo serialize($result);
}


// returns the number of records in given table
function count_star($db, $table) {

	$result=query("
		SELECT COUNT(*) AS c
		FROM $table
	", $db, false);

	while ($row = mysqli_fetch_assoc($result)) {
		$c=$row['c'];
	}

	mysqli_free_result($result);
	return $c;
}


// remove any newline characters
function smooth_text($txt) {
	return strtr($txt, array("\r\n"=>' ', "\r"=>' ', "\n"=>' '));
}


// ensure there is an error_view_osmXX_shadow table for inserting records
function toggle_tables1($db1, $schema){
	global $error_types_name, $comments_name, $comments_historic_name;

	echo "setting up table structures and toggling tables\n";
	query("
		CREATE TABLE IF NOT EXISTS $comments_name (
		`schema` varchar(6) NOT NULL DEFAULT '',
		error_id int(11) NOT NULL,
		state enum('ignore_temporarily','ignore') default NULL,
		`comment` text,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
		ip varchar(255) default NULL,
		user_agent varchar(255) default NULL,
		UNIQUE schema_error_id (`schema`, error_id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);
	query("
		CREATE TABLE IF NOT EXISTS $comments_historic_name (
		`schema` varchar(6) NOT NULL DEFAULT '',
		error_id int(11) NOT NULL,
		state enum('ignore_temporarily','ignore') default NULL,
		`comment` text,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
		ip varchar(255) default NULL,
		user_agent varchar(255) default NULL,
		UNIQUE schema_error_id (`schema`, error_id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);
	query("
		CREATE TABLE IF NOT EXISTS $error_types_name (
		error_type int(11) NOT NULL,
		error_name varchar(100) NOT NULL,
		error_description text NOT NULL,
		PRIMARY KEY  (error_type)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);

	query("DROP TABLE IF EXISTS error_view_{$schema}_shadow", $db1);
	query("
		CREATE TABLE IF NOT EXISTS error_view_{$schema}_shadow (
		`schema` varchar(6) NOT NULL DEFAULT '',
		error_id int(11) NOT NULL,
		error_type int(11) NOT NULL,
		error_name varchar(100) NOT NULL,
		object_type enum('node','way','relation') NOT NULL,
		object_id bigint(64) NOT NULL,
		state enum('new','cleared','ignored','reopened') NOT NULL,
		first_occurrence datetime NOT NULL,
		last_checked datetime NOT NULL,
		object_timestamp datetime NOT NULL,
		user_name text,
		lat int(11) NOT NULL,
		lon int(11) NOT NULL,
		msgid text,
		txt1 text,
		txt2 text,
		txt3 text,
		txt4 text,
		txt5 text,
		UNIQUE schema_error_id (`schema`, error_id),
		KEY lat (lat),
		KEY lon (lon),
		KEY error_type (error_type)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);
	query("ALTER TABLE error_view_{$schema}_shadow DISABLE KEYS", $db1);

	query("
		CREATE TABLE IF NOT EXISTS error_view_{$schema} (
		`schema` varchar(6) NOT NULL DEFAULT '',
		error_id int(11) NOT NULL,
		error_type int(11) NOT NULL,
		object_type enum('node','way','relation') NOT NULL,
		object_id bigint(64) NOT NULL,
		state enum('new','cleared','ignored','reopened') NOT NULL,
		first_occurrence datetime NOT NULL,
		last_checked datetime NOT NULL,
		object_timestamp datetime NOT NULL,
		user_name text NOT NULL,
		lat int(11) NOT NULL,
		lon int(11) NOT NULL,
		msgid text,
		txt1 text,
		txt2 text,
		txt3 text,
		txt4 text,
		txt5 text,
		UNIQUE schema_error_id (`schema`, error_id),
		KEY lat (lat),
		KEY lon (lon),
		KEY error_type (error_type)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);

	query("
		CREATE TABLE IF NOT EXISTS error_counts (
		`schema` varchar(6) NOT NULL,
		error_type int(11) NOT NULL,
		error_count int(1) NOT NULL,
		UNIQUE schema_error_type (`schema`, error_type)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);

	echo "done.\n";
}


// adds a column to a table if it not already exists
function add_column_if_not_exists($db, $table, $column, $attribs) {
	if(!column_exists($db, $table, $column)){
		query("ALTER TABLE `$table` ADD `$column` $attribs", $db, false);
	}
 }


// checks existence of a column
function column_exists($db, $table, $column) {
	$column_exists = false;

	$rows = query("SHOW COLUMNS FROM `$table` WHERE Field='$column'", $db, false);
	while($c = mysqli_fetch_assoc($rows)){
		if($c['Field'] == $column){
			$column_exists = true;
			break;
		}
	}

	return $column_exists;
 }


// adds an index to a table if it not already exists
function add_index_if_not_exists($db, $table, $keyname, $column, $attrib='') {

	if(!index_exists($db, $table, $keyname)){
		query("CREATE $attrib INDEX `$keyname` ON `$table` ($column)", $db, false);
	}
}

// drop an index if exists
function drop_index_if_exists($db, $table, $keyname) {

	if(index_exists($db, $table, $keyname)){
		query("DROP INDEX `$keyname` ON `$table`", $db, false);
	}
}

// check if an index exists
function index_exists($db, $table, $keyname) {

	$rows = query("SHOW INDEX FROM `$table` WHERE Key_name='$keyname'", $db, false);
	while($c = mysqli_fetch_assoc($rows)){
		if($c['Key_name'] == $keyname){
			mysqli_free_result($rows);
			return true;
		}
	}
	mysqli_free_result($rows);
	return false;
}


// switch _shadow table to main table, rename main table to _old
function toggle_tables2($db1, $schema){
	echo "toggling back tables\n";

	query("ALTER TABLE error_view_{$schema}_shadow ENABLE KEYS", $db1);


	query("TRUNCATE error_view_{$schema}", $db1);
	query("INSERT INTO error_view_{$schema} " .
		"(`schema`, error_id, error_type, object_type, object_id, state, " .
			"first_occurrence, last_checked, object_timestamp, user_name, lat, lon, " .
			"msgid, txt1, txt2, txt3, txt4, txt5) " .
		"SELECT `schema`, error_id, error_type, object_type, object_id, state, " .
			"first_occurrence, last_checked, object_timestamp, user_name, lat, lon, " .
			"msgid, txt1, txt2, txt3, txt4, txt5 " .
		"FROM error_view_{$schema}_shadow " .
		"WHERE `schema` = '$schema'", $db1);

	query("DROP TABLE error_view_{$schema}_shadow", $db1);

	// update error counts
	query("DELETE FROM error_counts WHERE `schema`='{$schema}'", $db1);
	query("INSERT INTO error_counts (`schema`, error_type, error_count) " .
		"SELECT '{$schema}', error_type, COUNT(error_id) " .
		"FROM error_view_{$schema} " .
		"GROUP BY error_type", $db1);

	echo "done.\n";
}

function empty_error_types_table($db1){
	global $error_types_name;
	query("
		TRUNCATE $error_types_name
	", $db1);

	echo "done.\n";
}

// overwrite $filename with $content
function write_file($filename, $content) {

	if (!$handle = fopen($filename, 'w')) {
		echo "Cannot open file ($filename)";
		exit;
	}

	if (fwrite($handle, $content) === FALSE) {
		echo "Cannot write to file ($filename)";
		exit;
	}
	fclose($handle);

	echo "done.\n";
}


// update temporarily ignored errors to open again
// if the tmp.ignore state was set after the planet file
// was downloaded (updated). Let's assume that max(object_timestamp)
// equals the time of download.
// Do this only if the error is still open in the newest error_view
function reopen_errors($db1, $schema) {
	global $comments_name;

	echo "reopening errors not solved by this update\n";

	$sql="
		UPDATE $comments_name c INNER JOIN error_view_$schema ev USING (`schema`, error_id)
		SET c.state=null,
		c.comment=CONCAT(\"[error still open, \", CURDATE(), \"] \", c.comment)
		WHERE ev.`schema`='$schema' AND c.state='ignore_temporarily' AND
		ev.state<>'cleared' AND
		c.timestamp<DATE_SUB((

			SELECT MAX(tmp.object_timestamp)
			FROM error_view_$schema tmp

		), INTERVAL 1 HOUR)
	";
	query($sql, $db1);
	echo "\ndone.\n";
}


// load a dump file from the local webspace
// dump file may be plain text or .bz2 compressed
// file format has to be tab-separated text
// just the way you receive from SELECT INTO OUTFILE
function load_dump($db1, $filename, $destination, $schema) {
	global $db_host, $db_user, $db_pass, $db_name, $error_types_name;

	switch ($destination) {
		case "error_types": $tbl=$error_types_name; break;
		case "error_view": $tbl="error_view_{$schema}_shadow"; break;
		default: die('invalid load dump destination: ' . $destination);
	}
	echo "loading dump into $destination (table name is $tbl)\n";

	$fifodir=ini_get('upload_tmp_dir');
	if (strlen($fifodir)==0) $fifodir=sys_get_temp_dir();

	$fifoname=tempnam($fifodir, 'keepright');
	echo "creating fifo file $fifoname\n";
	unlink($fifoname);

	// create a fifo, unzip contents of the dump into fifo
	// and make mysql read from there to do a LOAD DATA INFILE

	posix_mkfifo($fifoname, 0666) or die("Couldn't create fifo.");
	echo "reading dump file $filename\n";

	// remember: $filename is shellescaped and has apos around it!
	if (substr(trim($filename), -5, 4)=='.bz2') {
		$CAT='bzcat';
	} else {
		$CAT='cat';
	}

	system("($CAT $filename > $fifoname) >/dev/null &");	// must run in the background

	query("LOAD DATA LOCAL INFILE '$fifoname' INTO TABLE $tbl", $db1);

	unlink($fifoname);

	echo "done.\n";
}

?>