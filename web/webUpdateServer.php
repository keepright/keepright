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
* update updated_osm_XX file (date of last site update)
* re-open temporarily ignored errors
*/

ini_set('session.use_cookies', false);	// never use cookies
ini_set('session.gc_maxlifetime', 1800);// 30 minutes as max. session lifetime

require('webconfig.inc.php');
require('helpers.inc.php');
require('BufferedInserter_MySQL.php');
//echo "db_name is $db_name";

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

	if ($_GET['cmd'] == 'update') {

		if (!permissions($USERS[$_SESSION['username']], $db, $schema)) {
			die("you are not authorized to access $db.$schema\n");
		}

		$error_view_filename=escapeshellarg($_GET['error_view_filename']);
		if (!file_exists(substr($error_view_filename, 1, -1))) {
			echo "$error_view_filename does not exist on web server\n";
			exit;
		}

		$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

		toggle_tables1($db1, $schema);
		load_dump($db1, $error_view_filename, 'error_view');
		//load_dump($db1, escapeshellarg($_GET['error_types_filename']), 'error_types');
		toggle_tables2($db1, $schema);
		empty_error_types_table($db1);
		reopen_errors($db1, $schema);
		// set_updated_date
		write_file($updated_file_name, addslashes($_GET['updated_date']));
		// set_planetfile_date
		//write_file($planetfile_date_file_name, addslashes($_GET['planetfile_date']));

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

// check if a given db and schema name are found in the users permissions array
// which is configured in $USERS in webconfig.inc.php
function permissions($user, $db, $schema) {

	if (array_key_exists('%', $user['DB'])) {
		if (in_array('%', $user['DB']['%'], true))
			return true;			// any schema in any db
		else
			return (in_array($schema, $user['DB']['%'], true));	// a given schema in any db

	} else if (array_key_exists($db, $user['DB'])) {
		if (in_array('%', $user['DB'][$db], true))
			return true;			// any schema in a given db
		else
			return (in_array($schema, $user['DB'][$db], true));	// a given schema in a given db

	} else return false;
}


// ensure there is an error_view_osmXX_shadow table for inserting records
function toggle_tables1($db1, $schema){
	global $error_types_name, $error_view_name, $error_view_old_name, $comments_name, $comments_historic_name;

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
		KEY `schema` (`schema`),
		KEY error_id (error_id)
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
		KEY `schema` (`schema`),
		KEY error_id (error_id)
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
	query("
		CREATE TABLE IF NOT EXISTS {$error_view_old_name} (
		`schema` varchar(6) NOT NULL DEFAULT '',
		error_id int(11) NOT NULL,
		db_name varchar(50) NOT NULL,
		error_type int(11) NOT NULL,
		error_name varchar(100) NOT NULL,
		object_type enum('node','way','relation') NOT NULL,
		object_id bigint(64) NOT NULL,
		state enum('new','cleared','ignored','reopened') NOT NULL,
		description text NOT NULL,
		first_occurrence datetime NOT NULL,
		last_checked datetime NOT NULL,
		lat int(11) NOT NULL,
		lon int(11) NOT NULL,
		UNIQUE schema_error_id (`schema`, error_id),
		KEY lat (lat),
		KEY lon (lon),
		KEY error_type (error_type),
		KEY state (state)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);
	query("
		CREATE TABLE IF NOT EXISTS $error_view_name (
		`schema` varchar(6) NOT NULL DEFAULT '',
		error_id int(11) NOT NULL,
		db_name varchar(50) NOT NULL,
		error_type int(11) NOT NULL,
		error_name varchar(100) NOT NULL,
		object_type enum('node','way','relation') NOT NULL,
		object_id bigint(64) NOT NULL,
		state enum('new','cleared','ignored','reopened') NOT NULL,
		description text NOT NULL,
		first_occurrence datetime NOT NULL,
		last_checked datetime NOT NULL,
		object_timestamp datetime NOT NULL,
		lat int(11) NOT NULL,
		lon int(11) NOT NULL,
		UNIQUE schema_error_id (`schema`, error_id),
		KEY lat (lat),
		KEY lon (lon),
		KEY error_type (error_type),
		KEY state (state)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);

	// ensure the schema column is added when tables already exist
	add_column_if_not_exists($db1, $comments_name, 'schema', "varchar(6) NOT NULL DEFAULT '' FIRST");
	add_column_if_not_exists($db1, $comments_historic_name, 'schema', "varchar(6) NOT NULL DEFAULT '' FIRST");
	add_column_if_not_exists($db1, $error_view_old_name, 'schema', "varchar(6) NOT NULL DEFAULT '' FIRST");
	add_column_if_not_exists($db1, $error_view_name, 'schema', "varchar(6) NOT NULL DEFAULT '' FIRST");

	add_column_if_not_exists($db1, $error_view_old_name, 'object_timestamp', "datetime NOT NULL AFTER last_checked");
	add_column_if_not_exists($db1, $error_view_name, 'object_timestamp', "datetime NOT NULL AFTER last_checked");

	add_index_if_not_exists($db1, $comments_name, 'schema', '`schema`');
	add_index_if_not_exists($db1, $comments_historic_name, 'schema', '`schema`');
	add_index_if_not_exists($db1, $error_view_old_name, 'schema', '`schema`');
	add_index_if_not_exists($db1, $error_view_name, 'schema', '`schema`');

	query("RENAME TABLE $error_view_old_name TO {$error_view_name}_shadow", $db1);
	query("ALTER TABLE {$error_view_name}_shadow DISABLE KEYS", $db1);
	query("TRUNCATE {$error_view_name}_shadow", $db1);

	echo "done.\n";
}


// adds a column to a table if it not already exists
function add_column_if_not_exists($db, $table, $column, $attribs) {
     $column_exists = false;

     $rows = query("SHOW COLUMNS FROM `$table` WHERE Field='$column'", $db, false);
     while($c = mysqli_fetch_assoc($rows)){
         if($c['Field'] == $column){
             $column_exists = true;
             break;
         }
     }

     if(!$column_exists){
         query("ALTER TABLE `$table` ADD `$column` $attribs", $db, false);
     }
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
	global $error_view_name, $error_view_old_name;

	echo "toggling back tables\n";
	// now add all records _except_ the ones we just loaded from the dump
	if (strlen($schema) && $schema!='%') {
		query("
			INSERT INTO {$error_view_name}_shadow
			SELECT * FROM $error_view_name
			WHERE `schema` NOT IN ($schema)
		", $db1);
	}


	query("
		ALTER TABLE {$error_view_name}_shadow ENABLE KEYS;
	", $db1);
	query("
		RENAME TABLE $error_view_name TO $error_view_old_name;
	", $db1);
	query("
		RENAME TABLE {$error_view_name}_shadow TO $error_view_name;
	", $db1);

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
// if the ignore state was set before the object was edited
// i.e. if the version of the object after the edit was checked.
// lets assume a crace time of maximum two hours between state timestamp
// and object timestamp (users needn't edit the objects at the same
// time as they set the state in keepright.
// do this only if the error is still open in the newest error_view
function reopen_errors($db1, $schema) {
	global $error_view_name, $comments_name;

	echo "reopening errors not solved by this update\n";

	if (strlen($schema) && $schema!=='%') 
		$s="ev.`schema` IN ($schema) AND ";
	else
		$s="";

	$sql="
		UPDATE $comments_name c inner join $error_view_name ev using (`schema`, error_id)
		SET c.state=null,
		c.comment=CONCAT(\"[error still open, \", CURDATE(), \"] \", c.comment)
		WHERE $s c.state='ignore_temporarily' AND
		ev.state<>'cleared' AND
		c.timestamp<DATE_SUB(ev.object_timestamp, INTERVAL 2 HOUR)
	";
	query($sql, $db1);
	echo "\ndone.\n";
}


// load a dump file from the local webspace
// dump file may be plain text or .bz2 compressed
// file format has to be tab-separated text
// just the way you receive from SELECT INTO OUTFILE
function load_dump($db1, $filename, $destination) {
	global $db_host, $db_user, $db_pass, $db_name, $error_types_name, $error_view_name;

	switch ($destination) {
		case "error_types": $tbl=$error_types_name; break;
		case "error_view": $tbl=$error_view_name . '_shadow'; break;
		default: die('invalid load dump destination: ' . $destination);
	}
	echo "loading dump into $destination\n";

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


	system("mysql -h$db_host -u$db_user -p$db_pass -e \"LOAD DATA LOCAL INFILE '$fifoname' INTO TABLE $tbl\" $db_name");

	unlink($fifoname);


	// now check if only schemas were inserted that were given in the command line

	if (strlen($schema) && $schema!=='%') {

		$rows = query("SELECT COUNT(error_id) AS x FROM $tbl WHERE `schema` NOT IN ($schema) ", $db, false);
		while($c = mysqli_fetch_assoc($rows)){
			if($c['x']>0){
				echo "you said you wanted to upload errors in schema $schema but you really uploaded errors in other schemas. The procedure stops here. Don't try that again!\n";
				logout();
				mysqli_free_result($rows);

				// now rollback all the user has done so far
				// otherwise he might come back and toggle2 the tables
				query("TRUNCATE {$error_view_name}_shadow", $db1);
				query("
					INSERT INTO {$error_view_name}_shadow
					SELECT * FROM $error_view_name
				", $db1);
				query("
					RENAME TABLE {$error_view_name}_shadow
					TO {$error_view_name}_old;
				", $db1);
			}
		}
		mysqli_free_result($rows);
	}

	echo "done.\n";
}

?>