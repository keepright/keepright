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
points.php?lat=48.208081&lon=16.3722146&ch=0,30,40,50,60,70,90&show_ign=1&show_tmpign=0

lat/lon...center of view
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
list($subtyped, $nonsubtyped) = get_subtyped_error_types($db1, $ch);


// non-subtyped errors selected including the complete 10-window (always include
// (exclude) all ten errors together. subtyped errors selected individually.
$where="(10*floor(error_type/10) IN ($nonsubtyped) OR error_type IN ($subtyped))";

// this is an additional restriction for errors around the map center +/- 0.1 degree that helps the database because it needn't calculate that much distance values
$where.=' AND lat >= ' . ($lat-3e6) . ' AND lat <= ' . ($lat+3e6);
$where.=' AND lon >= ' . ($lon-3e6) . ' AND lon <= ' . ($lon+3e6);

// lookup the schemas that have to be queried for the given coordinates
$error_view = error_view_subquery($db1, $lat, $lon, $where);


echo "lat\tlon\terror_name\terror_type\tobject_type\tobject_type_EN\tobject_id\tobject_timestamp\tschema\terror_id\tdescription\tcomment\tstate\ticon\ticonSize\ticonOffset\n";

if ($error_view=='') {
	mysqli_close($db1);
	exit;
}

// build SQL for fetching errors
$sql="SELECT e.schema, e.error_id, e.error_type, COALESCE(c.state, e.state) as state, e.object_type, e.object_id, e.object_timestamp, e.lat/1e7 as la, e.lon/1e7 as lo, e.error_name, c.comment,
e.msgid, e.txt1, e.txt2, e.txt3, e.txt4, e.txt5";

$sql .= " FROM ($error_view) e LEFT JOIN $comments_name c USING (`schema`, error_id)
WHERE TRUE";

if (!$show_ign) $sql.=' AND (c.state IS NULL OR c.state<>"ignore")';
if (!$show_tmpign) $sql.=' AND (c.state IS NULL OR c.state<>"ignore_temporarily")';

$sql .= " ORDER BY POWER(lat-$lat,2)+POWER(lon-$lon,2)";
//$sql .= " ORDER BY RAND()";
$sql .= ' LIMIT 100';



$result=mysqli_query($db1, $sql);
//echo "$sql\n";

while ($row = mysqli_fetch_assoc($result)) {

	switch($row['state']) {
		case 'ignore_temporarily':
			$filenr='angel';
			break;
		case 'ignore':
			$filenr='devil';
			break;
		default:
			$filenr=10*floor($row['error_type']/10);	// use icon 190 for types 191-199
	}

	if ($locale == 'en') {
		$replacements = array('$1'=>$row['txt1'], '$2'=>$row['txt2'], '$3'=>$row['txt3'], '$4'=>$row['txt4'], '$5'=>$row['txt5']);
	} else {
		$replacements = array('$1'=>translate($row['txt1']), '$2'=>translate($row['txt2']), '$3'=>translate($row['txt3']), '$4'=>translate($row['txt4']), '$5'=>translate($row['txt5']));
	}

	// wrap node/way ids in hyperlinks
	switch($row['error_type']) {

		// a list of node ids, possibly interstratified with numbers of up to two digits
		// (eg. layer values)
		case 20:
		case 211:
		case 294:	$replacements['$1'] = preg_replace('/(\d{3,15})/',
			"<a target='_blank' href='http://www.openstreetmap.org/browse/node/$1'>$1</a>",
			$replacements['$1']);
		break;

		// a list of way ids, possibly interstratified with numbers of up to two digits
		// (eg. layer values)
		case 231:	$replacements['$1'] = preg_replace('/(\d{3,15})/',
			"<a target='_blank' href='http://www.openstreetmap.org/browse/way/$1'>$1</a>",
			$replacements['$1']);
		break;

		// single node id in txt1
		case 40:
		case 41:
		case 210:	$replacements['$1']=hyperlink('node', $replacements['$1']);
		break;

		// single way id in txt1
		case 50:
		case 370:	$replacements['$1']=hyperlink('way', $replacements['$1']);
		break;

		// single way id in txt3
		case 191:
		case 192:
		case 193:
		case 194:
		case 195:
		case 196:
		case 197:
		case 198:
		case 201:
		case 202:
		case 203:
		case 204:
		case 205:
		case 206:
		case 207:
		case 208:	$replacements['$3']=hyperlink('way', $replacements['$3']);
		break;

		// way id in txt1 and txt2
		case 401:	$replacements['$1']=hyperlink('way', $replacements['$1']);
				$replacements['$2']=hyperlink('way', $replacements['$2']);
		break;
	}


	if ($locale == 'en') {
		// english: just insert txt parts into msgid

		$description = strtr($row['msgid'], $replacements);
		$error_name = $row['error_name'];
		$object_type = $row['object_type'];
	} else {
		// other languages: translate message and insert txt parts

		$description = strtr(T_gettext($row['msgid']), $replacements);
		$error_name = T_gettext($row['error_name']);
		$object_type = T_gettext($row['object_type']);
	}

	echo $row['la'] . "\t" .
		$row['lo'] . "\t" .
		$error_name . "\t" .
		$row['error_type'] . "\t" .
		$object_type . "\t" .
		$row['object_type'] . "\t" .
		$row['object_id'] . "\t" .
		$row['object_timestamp'] . "\t" .
		$row['schema'] . "\t" .
		$row['error_id'] . "\t" .
		strtr($description, "\t", " ") . "\t" .
		strtr($row['comment'], array("\t"=>" ", "\r\n"=>"<br>", "\n"=>"<br>")) . "\t" .
		strtr($row['state'], array("\t"=>" ", 'ignore_temporarily'=>'ignore_t')) .
		"\timg/zap" . $filenr . ".png".
		"\t24,24\t1,-24\n";
}

mysqli_free_result($result);
mysqli_close($db1);


function hyperlink($object_type, $id) {
	return "<a target='_blank' href='http://www.openstreetmap.org/browse/$object_type/$id'>$id</a>";
}

function translate($txt) {
	$translatables = array('node', 'way', 'relation');

	if (in_array($txt, $translatables))
		return T_gettext($txt);
	else
		return $txt;
}

?>