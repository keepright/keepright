<?php

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

//$db = $_GET['db'];
$db = '*';

$ch = $_GET['ch'];
if (!$ch) $ch=0;
$st = $_GET['st'];
$lat = 1e7*$_GET['lat'];
$lon = 1e7*$_GET['lon'];
$sq = 1e7*$_GET['sq'];
if (!$st) $st='open';


$sql='SELECT error_type, state, object_type, object_id, description, first_occurrence, last_checked, lat/1e7 as la, lon/1e7 as lo, t.error_name
FROM ' . $error_view_name . ' INNER JOIN ' . $error_types_name . ' t USING (error_type)
WHERE TRUE ';

if ($db<>'*') $sql .=' AND db_name="' . addslashes($db) . '"';
if ($ch<>'0') $sql .=' AND 10*floor(error_type/10) IN (' . addslashes($ch) . ')';


switch ($st) {
	case 'open': $sql.=' AND state IN ("new", "reopened")'; break;
	case 'cleared': $sql.=' AND state = "cleared"'; break;
}

$sql .= " ORDER BY POWER(lat-$lat,2)+POWER(lon-$lon,2)";
//$sql .= " ORDER BY RAND()";
$sql .= " LIMIT 100";

$result=mysqli_query($db1, $sql);
//echo "$sql\n";

echo "lat\tlon\ttitle\tdescription\ticon\ticonSize\ticonOffset\n";

while ($row = mysqli_fetch_assoc($result)) {

	echo $row['la'] . "\t" .
	  $row['lo'] . "\t" .
	  $row['error_name'] . ', ' .
	  $row['object_type'] . ' ' .
	  $row['object_id'] . "\t" .
	  $row['description'] . "<br>edit in <a href='http://localhost:8111/load_and_zoom?left=" . ($row['lo']-0.01) . "&right=" . ($row['lo']+0.01) . "&top=" . ($row['la']+0.01) . "&bottom=" . ($row['la']-0.01) . "&select=" . $row['object_type'] . $row['object_id'] . "' target='iframeForJOSM'>[JOSM]</a> <a href='http://openstreetmap.org/edit?lat=" . $row['la'] . "&lon=" . $row['lo'] . "&zoom=18' target='_blank'>Potlatch</a>" .
	  "\timg/zap" . $row['error_type'] . ".png\t24,24\t1,-24\n";
}

mysqli_free_result($result);
mysqli_close($db1);

?>