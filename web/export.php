<?php
/*

export errors in various formats

URL parameters
export.php?format=rss&left=48.20&top=16.3&right=48.30&bottom=16.35&db=osm_EU&ch=0,30,40,50,90


format...defines data format (rss, gpx)
left/top/right/bottom...borders of view
db...source table of errors := "error_view_" . $db
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
$subtyped_errors = array();
$subtyped_error_names = array();
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
	$subtyped_errors[] = $row['error_type'];
	$subtyped_error_names[$row['error_type']] = $row['error_name'];
}
mysqli_free_result($result);



// build SQL for fetching errors
$sql='SELECT e.`schema`, e.error_id, e.error_type, COALESCE(c.state, e.state) as state, e.object_type, e.object_id, e.description, e.lat/1e7 as la, e.lon/1e7 as lo, e.error_name, c.comment
FROM ' . $error_view_name . ' e LEFT JOIN ' . $comments_name . ' c ON (e.error_id=c.error_id)';
$sql.=" WHERE TRUE ";

if ($_GET['format'] == 'rss') {
	// show only errors that came up during last week
	$sql.="AND first_occurrence >= CURDATE( ) - INTERVAL 3 WEEK ";
}

// add criteria for selecting error types
$error_types=explode(',', addslashes($ch));
$nonsubtyped='0';
$subtyped='0';
//print_r($subtyped_errors);
// split list of error types into subtyped an non-subtyped ones
foreach ($error_types as $error_type) {

	if (is_numeric($error_type)) {
		if (in_array(10*floor($error_type/10), $subtyped_errors))
			$subtyped.=", $error_type";
		else
			$nonsubtyped.=", $error_type";
	}
}

// non-subtyped errors selected including the complete 10-window (always include
// (exclude) all ten errors together. subtyped errors selected individually.
$sql.=" AND (10*floor(e.error_type/10) IN ($nonsubtyped) OR e.error_type IN ($subtyped))";
$sql.=' AND e.lat BETWEEN ' . min($top, $bottom) . ' AND ' . max($top, $bottom);
$sql.=' AND e.lon BETWEEN ' . min($left, $right) . ' AND ' . max($left, $right);
$sql.=' AND e.state IN ("new", "reopened")';
$sql.=' AND (c.state IS NULL OR (c.state<>"ignore" AND c.state<>"ignore_temporarily"))';

$sql .= " LIMIT 100";

$result=mysqli_query($db1, $sql);
//echo "$sql\n";



if ($_GET['format'] == 'rss') {


	echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
	<rss version=\"2.0\">
	\t<channel>
	\t\t<title>KeepRight! The newest, the hottest, the most shocking errors you can find in OpenStreetMap!</title>
	\t\t<description>In this newsfeed you can find all errors that came up during the last three weeks.</description>
	\t\t<link>${baseURL}report_map.php</link>
	\t\t<image>
	\t\t\t<url>${baseURL}keepright.png</url>
	\t\t</image>\n\n";

	while ($row = mysqli_fetch_assoc($result)) {

		// prepend the main error type on a subtyped error
		if (in_array(10*floor($row['error_type']/10), $subtyped_errors))
			$title=$subtyped_error_names[10*floor($row['error_type']/10)] . ', ';
		else
			$title='';

		echo "\t\t<item>\n";
		echo "\t\t\t<title>" . $title . $row['error_name'] . " on " . $row['object_type'] . " #" . $row['object_id'] . "</title>\n";
		echo "\t\t\t<description>" . $row['description'] . "</description>\n";
		echo "\t\t\t<link>${baseURL}report_map.php?schema=" . $row['schema'] . "&amp;error=" . $row['error_id'] . "</link>\n";
		echo "\t\t\t<guid>${baseURL}report_map.php?schema=" . $row['schema'] . "&amp;error=" . $row['error_id'] . "</guid>\n";

		echo "\t\t\t<pubDate>" . date(DATE_RFC822, strtotime(get_updated_date())) . "</pubDate>\n";
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
		if (in_array(10*floor($row['error_type']/10), $subtyped_errors))
			$title=$subtyped_error_names[10*floor($row['error_type']/10)] . ', ';
		else
			$title='';

		echo "\t<wpt lon=\"" . $row['lo'] . "\" lat=\"" . $row['la'] . "\"> <desc><![CDATA[" . $title . $row['error_name'] . ': ' . $row['description'] . "]]></desc><extensions><id>" . $row['error_id'] . "</id></extensions></wpt>\n";
	}

	echo "</gpx>";


} else {

	echo "invalid format parameter. Allowed values: rss, gpx";

}


mysqli_free_result($result);
mysqli_close($db1);
?>