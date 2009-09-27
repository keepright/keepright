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
* update planetfile_date_osm_XX file (date of planet file used during update)
* create log entry
* re-open temporarily ignored errors
*/

ini_set('session.use_cookies', false);	// never use cookies
ini_set('session.gc_maxlifetime', 1800);// 30 minutes as max. session lifetime

require('webconfig.inc.php');
require('helpers.inc.php');
require('BufferedInserter_MySQL.php');
//echo "db_name is $db_name <br>";

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
		echo "OK welcome!";
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

/*
	if (isset($_GET['cmd']) && !permissions($USERS[$_SESSION['username']], $db, $schema)) {
		die("you are not authorized to access $db.$schema<br>");
	}
*/
	$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

	switch ($_GET['cmd']) {

		case 'toggle_tables1':
			toggle_tables1($db1, $schema);
		break;
		case 'load_dump':
			load_dump($db1, escapeshellarg($_GET['filename']), addslashes($_GET['destination']));
		break;
		case 'toggle_tables2':
			toggle_tables2($db1);
		break;
		case 'empty_error_types_table':
			empty_error_types_table($db1);
		break;
		case 'reopen_errors':
			reopen_errors($db1, addslashes($_GET['date']));
		break;
		case 'set_updated_date':
			write_file($updated_file_name, addslashes($_GET['date']));
		break;
		case 'set_planetfile_date':
			write_file($planetfile_date_file_name, addslashes($_GET['date']));
		break;
		case 'logout':
			// Unset all of the session variables and destroy the session.
			$_SESSION = array();
			session_destroy();
			echo "session closed.";
		break;
	}

	mysqli_close($db1);
}


// check if a given db and schema name are found in the users permissions array
// which is configured in $USERS in webconfig.inc.php
function permissions($user, $db, $schema){



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
	global $error_types_name, $error_view_name, $comments_name, $comments_historic_name;

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
		CREATE TABLE IF NOT EXISTS {$error_view_name}_old (
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
	add_column_if_not_exists($db1, "{$error_view_name}_old", 'schema', "varchar(6) NOT NULL DEFAULT '' FIRST");
	add_column_if_not_exists($db1, $error_view_name, 'schema', "varchar(6) NOT NULL DEFAULT '' FIRST");

	add_index_if_not_exists($db1, $comments_name, '`schema`', '`schema`');
	add_index_if_not_exists($db1, $comments_historic_name, '`schema`', '`schema`');
	add_index_if_not_exists($db1, "{$error_view_name}_old", '`schema`', '`schema`');
	add_index_if_not_exists($db1, $error_view_name, '`schema`', '`schema`');


	query("RENAME TABLE {$error_view_name}_old TO {$error_view_name}_shadow", $db1, false);
	query("ALTER TABLE {$error_view_name}_shadow DISABLE KEYS", $db1, false);
	query("TRUNCATE {$error_view_name}_shadow", $db1, false);

	if ($schema!='') {
		query("
			INSERT INTO {$error_view_name}_shadow
			SELECT * FROM $error_view_name
			WHERE `schema` NOT LIKE '" . $schema . "'
		", $db1, false);
	}
	// now we have a shadow table containing all records _except_
	// the ones we don't want to update

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
		query("CREATE $attrib INDEX $keyname ON `$table` ($column)", $db, false);
	}
}

// drop an index if exists
function drop_index_if_exists($db, $table, $keyname) {

	if(index_exists($db, $table, $keyname)){
		query("DROP INDEX $keyname ON `$table`", $db, false);
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
function toggle_tables2($db1){
	global $error_view_name;
	query("
		ALTER TABLE {$error_view_name}_shadow ENABLE KEYS;
	", $db1, false);
	query("
		RENAME TABLE $error_view_name TO {$error_view_name}_old;
	", $db1, false);
	query("
		RENAME TABLE {$error_view_name}_shadow TO $error_view_name;
	", $db1, false);

	echo "done.\n";
}

function empty_error_types_table($db1){
	global $error_types_name;
	query("
		TRUNCATE $error_types_name
	", $db1, false);

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
// if they are older than the last download dump file
// and if the error is still open in the newest error_view
// $date HAS TO BE of the form YYYY-MM-DD
function reopen_errors($db1, $date) {
global $error_view_name, $comments_name;

	$sql="
		UPDATE $comments_name c inner join $error_view_name ev using (error_id)
		SET c.state=null,
		c.comment=CONCAT(\"[error still open, $date] \", c.comment)
		WHERE c.state='ignore_temporarily' AND
		ev.state<>'cleared' AND
		c.timestamp<\"$date\"
	";

	query($sql, $db1, false);

	echo "\ndone.\n";
}

?>
