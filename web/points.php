<?php
/*
this script will be called by the myText layer that is setup
in report_map.php.
It will output a tab-separated table of errors found near the
spot given by lat/lon coordinates

This table will be interpreted by the OpenLayers layer with
client-side JavaScript and rendered in the browser

*/
require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);


$ch = $_GET['ch'];
if (!$ch) $ch=0;
$st = $_GET['st'];
$lat = 1e7*$_GET['lat'];
$lon = 1e7*$_GET['lon'];
$sq = 1e7*$_GET['sq'];
if (!$st) $st='open';


$sql='SELECT e.error_id, e.error_type, COALESCE(c.state, e.state) as state, e.object_type, e.object_id, e.description, e.lat/1e7 as la, e.lon/1e7 as lo, e.error_name, c.comment
FROM ' . $error_view_name . ' e LEFT JOIN ' . $comments_name . ' c ON (e.error_id=c.error_id)
WHERE TRUE ';

//if ($db<>'*') $sql .=' AND db_name="' . addslashes($db) . '"';
/*if ($ch<>'0')*/ $sql .=' AND 10*floor(error_type/10) IN (' . addslashes($ch) . ')';

$sql .=' AND lat >= ' . ($lat-1e6) . ' AND lat <= ' . ($lat+1e6);	// this is an additional restriction for errors around the map center +/- 0.1 degree that helps the database because it needn't calculate that much distance values
$sql .=' AND lon >= ' . ($lon-1e6) . ' AND lon <= ' . ($lon+1e6);

switch ($st) {
	case 'open': $sql.=' AND e.state IN ("new", "reopened")'; break;
	case 'cleared': $sql.=' AND state = "cleared"'; break;
}

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
			$filenr=10*floor($row['error_type']/10);
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