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
* update updated.inc
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

// enforce people to change their password
if ($UPDATE_TABLES_PASSWD=="shhh!" || strlen($UPDATE_TABLES_PASSWD)==0) {
	echo "password not yet configured";
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
if ($_SESSION['authorized'] !== true && !empty($_GET['response']))  {

	// check authenticity of response
	if ($_GET['response'] === md5($_SESSION['challenge'] . $UPDATE_TABLES_PASSWD)) {
		$_SESSION['authorized']=true;
		echo "OK welcome!";
	} else {
		echo "invalid response\n";
		$_SESSION['challenge'] = md5(rand(1e5,1e12));	// make a new challenge. People should not be able to have as many tries as they want.
		echo $_SESSION['challenge'] . "\n";
		echo htmlspecialchars(session_id()) . "\n";
	}
}


// handle commands for logged in users
if ($_SESSION['authorized']===true) {
	$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

	switch ($_GET['cmd']) {

		case 'toggle_tables1':
			toggle_tables1($db1);
		break;
		case 'load_dump':
			load_dump($db1, $_GET['filename'], $_GET['destination']);
		break;
		case 'toggle_tables2':
			toggle_tables2($db1);
		break;
		case 'empty_error_types_table':
			empty_error_types_table($db1);
		break;
		case 'reopen_errors':
			reopen_errors($db1, $_GET['date']);
		break;
		case 'set_updated_date':
			set_updated_date($_GET['date']);
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






// ensure there is an error_view_osmXX_shadow table for inserting records
function toggle_tables1($db1){
	global $error_types_name, $error_view_name, $comments_name, $comments_historic_name;

	query("
		CREATE TABLE IF NOT EXISTS $comments_name (
		error_id int(11) NOT NULL,
		state enum('ignore_temporarily','ignore') default NULL,
		`comment` text,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
		ip varchar(255) default NULL,
		user_agent varchar(255) default NULL,
		KEY error_id (error_id)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);
	query("
		CREATE TABLE IF NOT EXISTS $comments_historic_name (
		error_id int(11) NOT NULL,
		state enum('ignore_temporarily','ignore') default NULL,
		`comment` text,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP,
		ip varchar(255) default NULL,
		user_agent varchar(255) default NULL,
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
		KEY error_id (error_id),
		KEY lat (lat),
		KEY lon (lon),
		KEY error_type (error_type),
		KEY state (state)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);
	query("
		CREATE TABLE IF NOT EXISTS $error_view_name (
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
		KEY error_id (error_id),
		KEY lat (lat),
		KEY lon (lon),
		KEY error_type (error_type),
		KEY state (state)
		) ENGINE=MyISAM DEFAULT CHARSET=utf8;
	", $db1, false);
	query("
		RENAME TABLE {$error_view_name}_old TO {$error_view_name}_shadow
	", $db1, false);
	query("
		TRUNCATE {$error_view_name}_shadow;
	", $db1, false);
	query("
		ALTER TABLE {$error_view_name}_shadow DISABLE KEYS;
	", $db1, false);

	echo "done.\n";
}


// load a dump file from the local webspace
// dump file may be .bz2 compressed or plain text
// file format has to be valid SQL so that each line of dump file
// can be enclosed with brackets and put inside an INSERT statement
// like this:
// INSERT INTO table(...) VALUES (<line1>), (<line2>), ...
// NULL values can be represented by "\N"
function load_dump($db1, $filename, $destination) {
	global $error_types_name, $error_view_name;

	if ($destination == "error_types") {
		$bi=new BufferedInserter('INSERT INTO ' . $error_types_name . ' (error_type, error_name, error_description)', $db1, 300);
	} else {
		$bi=new BufferedInserter('INSERT INTO ' . $error_view_name . '_shadow (error_id, db_name, error_type, error_name, object_type, object_id, state, description, first_occurrence, last_checked, lat, lon)', $db1, 300);
	}

	if (substr($filename, -4) == ".bz2") {
		echo "loading bz2 - dump into $destination\n";
		$handle = bzopen($filename, 'r') or die("Couldn't open $filename for reading");

		$counter=0;
		while (!gzeof($handle)) {
			$buffer=trim(gzgets($handle, 40960));
		//	echo $buffer;
			if(strlen($buffer)>1) $bi->insert( str_replace('\N', 'NULL', $buffer) );
			if (!($counter++ % 1000)) echo "$counter ";
		}
		$bi->flush_buffer();
		gzclose($handle);

	} else {
		echo "loading txt - dump into $destination\n";
		$handle = fopen($filename, 'r') or die("Couldn't open $filename for reading");

		$counter=0;
		while (!feof($handle)) {
			$buffer=trim(fgets($handle, 40960));
		//	echo $buffer;
			if(strlen($buffer)>1) $bi->insert( str_replace('\N', 'NULL', $buffer) );
			if (!($counter++ % 1000)) echo "$counter ";
		}
		$bi->flush_buffer();
		fclose($handle);
	}
	echo "done.\n";
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

// write date into updated_osmXX file
function set_updated_date($date) {
	global $updated_file_name;

	if (!$handle = fopen($updated_file_name, 'w')) {
		echo "Cannot open file ($updated_file_name)";
		exit;
	}

	if (fwrite($handle, $date) === FALSE) {
		echo "Cannot write to file ($updated_file_name)";
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

	query("
		UPDATE $comments_name c inner join $error_view_name ev using (error_id)
		SET c.state=null,
		c.comment=CONCAT(\"[error still open, $date] \", c.comment)
		WHERE c.state='ignore_temporarily' AND
		ev.state<>'cleared' AND
		c.timestamp<\"$date\"
	", $db1);

	echo "done.\n";
}

?>
