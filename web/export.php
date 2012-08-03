<?php
/*

export errors in various formats

URL parameters
export.php?format=rss&left=48.20&top=16.3&right=48.30&bottom=16.35&ch=0,30,40,50,90

format...defines data format (rss, gpx)
left/top/right/bottom...borders of view
ch...comma separated list of error type numbers
*/

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

$ch = $_GET['ch'];
if (!$ch) $ch=0;

$left = 1e7*$_GET['left'];
$top = 1e7*$_GET['top'];
$right = 1e7*$_GET['right'];
$bottom = 1e7*$_GET['bottom'];


// select all error types where sub-types exist
list($subtyped_errors, $nonsubtyped_errors, $subtyped_array, $subtyped_names_array) = get_subtyped_error_types($db1, $ch);


// non-subtyped errors selected including the complete 10-window (always include
// (exclude) all ten errors together. subtyped errors selected individually.
$where="(10*floor(error_type/10) IN ($nonsubtyped_errors) OR error_type IN ($subtyped_errors))";
$where.=' AND lat BETWEEN ' . min($top, $bottom) . ' AND ' . max($top, $bottom);
$where.=' AND lon BETWEEN ' . min($left, $right) . ' AND ' . max($left, $right);

// lookup the schemas that have to be queried for the given coordinates
$error_view = error_view_subquery($db1, ($top+$bottom)/2, ($left+$right)/2, $where);

if ($error_view=='') {
	echo "no errors found";
	exit;
}

// build SQL for fetching errors
$sql="SELECT e.`schema`, e.error_id, e.error_type, COALESCE(c.state, e.state) as state, e.object_type, e.object_id,
replace(replace(replace(replace(replace(e.msgid, '$1', COALESCE(e.txt1, '')), '$2', COALESCE(e.txt2, '')), '$3', COALESCE(e.txt3, '')), '$4', COALESCE(e.txt4, '')), '$5', COALESCE(e.txt5, '')) as description,
e.lat/1e7 as la, e.lon/1e7 as lo, e.error_name, c.comment
FROM ($error_view) e LEFT JOIN $comments_name c ON (e.error_id=c.error_id)
WHERE TRUE ";

if ($_GET['format'] == 'rss') {
	// show only errors that came up during last week
	$sql.="AND first_occurrence >= CURDATE( ) - INTERVAL 3 WEEK ";
}

$sql.=' AND (c.state IS NULL OR (c.state<>"ignore" AND c.state<>"ignore_temporarily"))';
$sql .= ' LIMIT 100';

$result=mysqli_query($db1, $sql);
//echo "$sql\n";



if ($_GET['format'] == 'rss') {


	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
	<rss version=\"2.0\">
	\t<channel>
	\t\t<title>KeepRight! Newsfeed - Hunting errors in OpenStreetMap</title>
	\t\t<description>In this newsfeed you can find all errors that came up during the last three weeks.</description>
	\t\t<link>${baseURL}report_map.php</link>
	\t\t<image>
	\t\t\t<url>${baseURL}keepright.png</url>
	\t\t</image>\n\n";

	while ($row = mysqli_fetch_assoc($result)) {

		// prepend the main error type on a subtyped error
		if (in_array(10*floor($row['error_type']/10), $subtyped_array))
			$title=$subtyped_names_array[10*floor($row['error_type']/10)] . ', ';
		else
			$title='';

		echo "\t\t<item>\n";
		echo "\t\t\t<title>" . $title . $row['error_name'] . " on " . $row['object_type'] . " #" . $row['object_id'] . "</title>\n";
		echo "\t\t\t<description>" . $row['description'] . "</description>\n";
		echo "\t\t\t<link>${baseURL}report_map.php?schema=" . $row['schema'] . "&amp;error=" . $row['error_id'] . "</link>\n";
		echo "\t\t\t<guid>${baseURL}report_map.php?schema=" . $row['schema'] . "&amp;error=" . $row['error_id'] . "</guid>\n";

		echo "\t\t\t<pubDate>" . date(DATE_RFC822, strtotime(get_updated_date($row['schema']))) . "</pubDate>\n";
		echo "\t\t</item>\n";

	}

	echo "\t</channel>
	</rss>";



} elseif ($_GET['format'] == 'gpx') {


	header('Content-type: application/gpx+xml');
	header('Content-Disposition: attachment; filename="points.gpx"');


	echo "<?xml version=\"1.0\" encoding=\"UTF-8\" standalone=\"no\" ?>\n";
	echo "<gpx xmlns=\"http://www.topografix.com/GPX/1/1\" creator=\"keepright\" version=\"1.1\" xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"http://www.topografix.com/GPX/1/1 http://www.topografix.com/GPX/1/1/gpx.xsd\">\n";


	while ($row = mysqli_fetch_assoc($result)) {

		// prepend the main error type on a subtyped error
		if (in_array(10*floor($row['error_type']/10), $subtyped_array))
			$title=$subtyped_names_array[10*floor($row['error_type']/10)] . ', ';
		else
			$title='';

		echo "\t<wpt lon=\"" . $row['lo'] . "\" lat=\"" . $row['la'] . "\">";
		echo "<name><![CDATA[" . $title . $row['error_name'] . "]]></name>";
		echo "<desc><![CDATA[" . $row['description'] . "]]></desc>";
		echo "<extensions><schema>" . $row['schema'] . "</schema><id>" . $row['error_id'] . "</id></extensions></wpt>\n";
	}

	echo "</gpx>";


} else {

	echo "invalid format parameter. Allowed values: rss, gpx";

}


mysqli_free_result($result);
mysqli_close($db1);
?>