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
function get_updated_date($schema) {
	return content("updated_$schema");
}


// return content of file if file exists
function content($filename) {
	if (file_exists($filename))
		return trim(file_get_contents($filename));
	else
		return '';
}


// select all error types where sub-types exist
// return the list of error types and their names
function get_subtyped_error_types($db1, $ch) {
	global $error_types_name;

	$subtyped_array = array();
	$subtyped_names_array = array();

	$result=mysqli_query($db1, "
		SELECT 10*floor(et1.error_type/10) AS error_type, error_name
		FROM $error_types_name et1
		WHERE EXISTS (
			SELECT error_type
			FROM $error_types_name et2
			WHERE et2.error_type BETWEEN et1.error_type+1 AND et1.error_type+9
		)
		AND et1.error_type MOD 10 = 0
	");
	while ($row = mysqli_fetch_assoc($result)) {
		$subtyped_array[] = $row['error_type'];
		$subtyped_names_array[$row['error_type']] = $row['error_name'];
	}
	mysqli_free_result($result);


	// add criteria for selecting error types
	$error_types=explode(',', addslashes($ch));
	$nonsubtyped='0';
	$subtyped='0';
	//print_r($subtyped_errors);
	// split list of error types into subtyped an non-subtyped ones
	foreach ($error_types as $error_type) {

		if (is_numeric($error_type)) {
			if (in_array(10*floor($error_type/10), $subtyped_array))
				$subtyped.=", $error_type";
			else
				$nonsubtyped.=", $error_type";
		}
	}

	return array($subtyped, $nonsubtyped, $subtyped_array, $subtyped_names_array);
}


// check out which schemas to query for given coordinates (lat/lon)
// and return a UNION query with an arbitrary WHERE part
function error_view_subquery($db1, $lat, $lon, $where='TRUE'){

	// lookup the schemas that have to be queried for the given coordinates
	$error_view='';
	$result=mysqli_query($db1, "
		SELECT `schema` AS s
		FROM `schemata`
		WHERE `left_padded`<=$lon/1e7 AND `right_padded`>=$lon/1e7
			AND `top_padded`>=$lat/1e7 AND `bottom_padded`<=$lat/1e7
	");
	while ($row = mysqli_fetch_assoc($result)) {

		$error_view.=' SELECT * FROM error_view_' . $row['s'] .
			" WHERE $where UNION ALL " ;

	}
	mysqli_free_result($result);
	return substr($error_view, 0, -11);
}



function find_schema($db1, $lat, $lon) {

	$schema='0';

	$result=mysqli_query($db1, "
		SELECT `schema` AS s
		FROM `schemata`
		WHERE `left`<=$lon/1e7 AND `right`>=$lon/1e7
			AND `top`>=$lat/1e7 AND `bottom`<=$lat/1e7
		LIMIT 1
	");
	while ($row = mysqli_fetch_assoc($result)) {

		$schema = $row['s'];

	}
	mysqli_free_result($result);
	return $schema;
}


// render a select list for user interface language selection
function language_selector() {
	global $locale;

	echo '<select name="lang" onchange="setLang(document.myform.lang.value); submit();">';

	$languages=array('da', 'de', 'es', 'en_US', 'fr', 'nb', 'nl', 'pt_BR');

	foreach ($languages as $lang) {
		echo "<option value='$lang' " . ($locale==$lang ? 'selected' : '') . ">$lang</option>";
	}
	echo '</select>';
}
?>