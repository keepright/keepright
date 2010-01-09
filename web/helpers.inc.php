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

?>