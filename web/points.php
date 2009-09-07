<?php
/*
this script will be called by the myText layer that is setup
in report_map.php.
It will output a tab-separated table of errors found near the
spot given by lat/lon coordinates

This table will be interpreted by the OpenLayers layer with
client-side JavaScript and rendered in the browser
see myTextFormat.js and myText.js

URL parameters
points.php?lat=48.208081&lon=16.3722146&db=osm_EU&ch=0,30,40,50,60,70,90&show_ign=1&show_tmpign=0

lat/lon...center of view
db...source table of errors := "error_view_" . $db
ch...comma separated list of error type numbers
show_ign...if set enables display of permanently ignored (false positive) errors
show_tmpign...if set enables display of temporarily ignored errors

*/

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_query($db1, "SET SESSION wait_timeout=60");


$ch = $_GET['ch'];
if (!$ch) $ch=0;
$st = $_GET['st'];
$lat = 1e7*$_GET['lat'];
$lon = 1e7*$_GET['lon'];

$show_ign=isset($_GET['show_ign']) && $_GET['show_ign']<>'0';
$show_tmpign=isset($_GET['show_tmpign']) && $_GET['show_tmpign']<>'0';
if (!$st) $st='open';


// select all error types where sub-types exist
$subtyped_errors = array();
$result=mysqli_query($db1, "
	SELECT 10*floor(et1.error_type/10) AS error_type
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
}
mysqli_free_result($result);




// build SQL for fetching errors
$sql='SELECT e.error_id, e.error_type, COALESCE(c.state, e.state) as state, e.object_type, e.object_id, e.description, e.lat/1e7 as la, e.lon/1e7 as lo, e.error_name, c.comment
FROM ' . $error_view_name . ' e LEFT JOIN ' . $comments_name . ' c ON (e.error_id=c.error_id)
WHERE';



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
$sql .=" (10*floor(error_type/10) IN ($nonsubtyped) OR error_type IN ($subtyped))";


//$sql .=' AND lat >= ' . ($lat-1e6) . ' AND lat <= ' . ($lat+1e6);	// this is an additional restriction for errors around the map center +/- 0.1 degree that helps the database because it needn't calculate that much distance values
//$sql .=' AND lon >= ' . ($lon-1e6) . ' AND lon <= ' . ($lon+1e6);

switch ($st) {
	case 'open': $sql.=' AND e.state IN ("new", "reopened")'; break;
	case 'cleared': $sql.=' AND e.state = "cleared"'; break;
}

if (!$show_ign) $sql.=' AND (c.state IS NULL OR c.state<>"ignore")';
if (!$show_tmpign) $sql.=' AND (c.state IS NULL OR c.state<>"ignore_temporarily")';



$sql .= " ORDER BY POWER(lat-$lat,2)+POWER(lon-$lon,2)";
//$sql .= " ORDER BY RAND()";
$sql .= " LIMIT 100";

$result=mysqli_query($db1, $sql);
//echo "$sql\n";

echo "lat\tlon\terror_name\terror_type\tobject_type\tobject_id\terror_id\tdescription\tcomment\tstate\ticon\ticonSize\ticonOffset\n";

while ($row = mysqli_fetch_assoc($result)) {

	switch($row['state']) {
		case 'ignore_temporarily':
			$filenr='angel';
			break;
		case 'ignore':
			$filenr='devil';
			break;
		default:
			$filenr=$row['error_type'];
	}

	echo $row['la'] . "\t" .
		$row['lo'] . "\t" .
		$row['error_name'] . "\t" .
		$row['error_type'] . "\t" .
		$row['object_type'] . "\t" . 
		$row['object_id'] . "\t" . 
		$row['error_id'] . "\t" . 
		strtr($row['description'], "\t", " ") . "\t" .
		strtr($row['comment'], array("\t"=>" ", "\r\n"=>"<br>", "\n"=>"<br>")) . "\t" .
		strtr($row['state'], array("\t"=>" ", 'ignore_temporarily'=>'ignore_t')) .
		"\timg/zap" . $filenr . ".png".
		"\t24,24\t1,-24\n";
}

mysqli_free_result($result);
mysqli_close($db1);

?>