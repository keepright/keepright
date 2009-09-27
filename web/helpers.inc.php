<?php



// execute $sql using database link $link
// echo debug messages if $debug is set
function query($sql, $link, $debug=true) {
	if ($debug) {
		echo "\n\n" . rtrim(preg_replace('/(\s)\s+/', '$1', $sql)) . "\n";
		$starttime=microtime(true);
	}
	//$result=mysql_unbuffered_query($sql, $link);
	$result=mysqli_query($link, $sql, MYSQLI_USE_RESULT);
	if (!$result) {
		$message  = 'Invalid query: ' . mysqli_errno($link) . ": " . mysqli_error($link) . "\n";
		$message .= 'Whole query: ' . $sql . "\n";
		$message .= 'Query result: ' . $result . "\n";
		echo($message);
	}
	if ($debug) echo format_time(microtime(true)-$starttime) ."\n";
	return $result;
}


// gets a time value in seconds and writes it in s, min, h
// according to its amount
function format_time($t) {
	if ($t<60) {
		return sprintf("%01.2fs", $t);						// seconds
	} elseif ($t<3600) {
		return sprintf("%01.0fm %01.0fs", floor($t/60), $t % 60);		// minutes
	} else
		return sprintf("%01.0fh %01.0fm", floor($t/3600), ($t % 3600)/60);	// hours
}


// return the date of last site update (depending on db parameter)
function get_updated_date() {
	global $updated_file_name;

	return content($updated_file_name);
}

// return the date of planet file used for the last update (depending on db parameter)
function get_planetfile_date() {
	global $planetfile_date_file_name;

	return content($planetfile_date_file_name);
}


// return content of file if file exists
function content($filename) {
	if (file_exists($filename))
		return trim(file_get_contents($filename));
	else
		return '';
}



// load a dump file from the local webspace
// dump file may be plain text or .bz2 compressed
// file format has to be tab-separated plain text
// just the way you receive from SELECT INTO OUTFILE
function load_dump($db1, $filename, $destination) {
	global $db_host, $db_user, $db_pass, $db_name, $error_types_name, $error_view_name;

	switch ($destination) {
		case "error_types": $tbl=$error_types_name; break;
		case "error_view": $tbl=$error_view_name . '_shadow'; break;
		default: die('invalid load dump destination: ' . $destination);
	}
	echo "loading dump into $destination<br>\n";

	$fifodir=ini_get('upload_tmp_dir');
	if (strlen($fifodir)==0) $fifodir=sys_get_temp_dir();

	$fifoname=tempnam($fifodir, 'keepright');
	echo "creating fifo file $fifoname<br>\n";
	unlink($fifoname);

	// create a fifo, unzip contents of the dump into fifo
	// and make mysql read from there to do a LOAD DATA INFILE

	posix_mkfifo($fifoname, 0666) or die("Couldn't create fifo.");
	echo "reading dump file $filename<br>\n";

	// remember: $filename is shellescaped and has apos around it!
	if (substr(trim($filename), -5, 4)=='.bz2') {
		$CAT='bzcat';
	} else {
		$CAT='cat';
	}

	system("($CAT $filename > $fifoname) >/dev/null &");	// must run in the background


	system("mysql -h$db_host -u$db_user -p$db_pass -e \"LOAD DATA LOCAL INFILE '$fifoname' INTO TABLE $tbl\" $db_name");

	unlink($fifoname);
	echo "done.\n";
}


?>