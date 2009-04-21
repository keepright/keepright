<?php

require('Datagrid.php');
require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
$db2=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

//$db = htmlentities($_GET['db']);
//$db = '*';

$ch = htmlentities($_GET['ch']);
$st = htmlentities($_GET['st']);
$lat = 1e7*htmlentities($_GET['lat']);
$lon = 1e7*htmlentities($_GET['lon']);
$sq = 1e7*htmlentities($_GET['sq']);

if ($lat==0) $lat=482080810;
if ($lon==0) $lon=163722146;
if ($sq==0) $sq=0.2*1e7;
if (!$ch) $ch="*";

// Mödling:
//http://www.openstreetmap.org/?lat=48.0981&lon=16.28478&zoom=16&layers=B00FTF


if (!$st) $st='open';
echo '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>keep right!</title>
	<style type="text/css">
	<!--
		td,th {
			border-top-width:1px;
			border-top-style:solid;
			border-top-color:#d0d0d0;
			padding:0.2em;
		}
		td.mouseover {
			background-color:#ffffd0;
		}
	-->
	</style>
	</head><body>
';

echo '<form method="get" action="' . $_SERVER['PHP_SELF'] . '">';
/*echo '<select name="db"><option value="*">all databases</option>';
$result=query("SELECT db_name FROM error_view GROUP BY db_name", $db1, false);
while ($row = mysql_fetch_array($result, MYSQL_ASSOC)) {
	if (!$db) $db=$row['db_name'];	// set default
	//echo mklink($row['db_name'], $ch, $st, $row['db_name']);
	echo '<option value="' . $row['db_name'] . '">' . $row['db_name'] . '</option>';
}
echo "</select><br>\n";
mysql_free_result($result);*/

echo '<table style="padding:0.5em; background-color:#f0fff0" width="65%"><tr><td style="border-style:none"><a href="/"><img border=0 src="keepright.png" alt="keep-right logo"></a></td><td style="border-style:none">';
echo '<select name="ch"><option value="*">all checks</option>';
 
 
$result=mysqli_query($db1, "SELECT error_type, error_name FROM $error_types_name ORDER BY error_type");
while ($row = mysqli_fetch_array($result, MYSQL_ASSOC)) {
	if (!$ch) $ch=$row['error_type'];	// set default
	echo '<option value="' . $row['error_type'] . '"' . (($row['error_type']>=$ch && $row['error_type']<$ch+10) ? ' selected' : '') . '>' . $row['error_name'] . '</option>';
}
echo "</select><br>\n";
if (!is_null($result)) mysqli_free_result($result);


echo "<select name='st'>
	<option " . (($st=='all')?'selected':'') . " value='all'>all</option>
	<option " . (($st=='open')?'selected':'') . " value='open'>open errors</option>
	<option " . (($st=='cleared')?'selected':'') . " value='cleared'>cleared errors</option>
</select></td><td style='border-style:none' align='right'>
latitude:<input size=6 type='text' name='lat' value='" . $lat/1e7 . "'>&deg;<br>
longitude:<input size=6 type='text' name='lon' value='" . $lon/1e7 . "'>&deg;<br>
square size:<input size=6 type='text' name='sq' value='" . $sq/1e7 . "'>&deg;
</td></tr>
<tr><td colspan=2 style='border-style:none'>Planet file downloaded at " . trim(file_get_contents('updated.inc')) . ".&nbsp;&nbsp;<input type='submit' name='requery' value='requery'></td>";


//echo "<br>db:$db / check:$ch / state:$st / lat:$lat / lon:$lon / sq:$sq'<br>";



$select='SELECT state, object_id, description, first_occurrence, last_checked, 
	CONCAT("<a target=\'_blank\' href=\'report_map.php?lat=", lat/1e7, "&lon=", lon/1e7, "&zoom=15\'>map</a>") AS lnk, 
	CONCAT("<a target=\'_blank\' href=\'http://www.openstreetmap.org/api/0.6/", object_type, "/", object_id, "\'>api</a>") AS api';
$from ="FROM $error_view_name 
WHERE TRUE ";

if ($db<>'*') $from .=" AND db_name='" . addslashes($db) . "'";
if ($ch<>'*') $from .=' AND error_type>="' . addslashes($ch) . '" AND error_type<10+"' . addslashes($ch) . '" ';

switch ($st) {
	case 'open': $from.=" AND state IN ('new', 'reopened')"; break;
	case 'cleared': $from.=" AND state = 'cleared'"; break;
}

if ($lat<>0 && $lon<>0)
	$from.=' AND lat >= ' . ($lat-$sq/2) . ' AND lat <= ' . ($lat+$sq/2) . ' AND 
		lon >= ' . ($lon-$sq/2) . ' AND lon <= ' . ($lon+$sq/2);

$from .= ' ORDER BY error_type ASC';
//echo "$select $from<br>";

$grid = Datagrid::Create(
	array(
		'hostname' => $db_host,
		'username' => $db_user,
		'password' => $db_pass,
		'database' => $db_name
	), $select, $from, 15);


$grid->allowSorting=true;
$grid->showHeaders=true;
$grid->NoSpecialChars('lnk', 'api');
$grid->SetDisplayNames(
	array(
		'object_id' => 'object id',
		'first_occurrence' => 'first found at',
		'last_checked' => 'check date'
		)
);

echo "<td style='border-style:none' align='right'>" . $grid->GetRowCount() . "&nbsp;errors&nbsp;found.";
echo "</td></tr></table>
<input type='hidden' name='db' value='$db'>
</form>";
if ($grid->GetRowCount())
	$grid->Display();
else
	echo "<br>no errors found.";

echo "</body></html>";
mysqli_close($db1);
mysqli_close($db2);




function mklink($db, $ch, $st, $label) {
	return '<a href="' . $_SERVER['PHP_SELF'] . '?db=' . $db . '&ch=' . $ch .  '&st=' . $st . '">' . $label . '</a> ';
}

?> 
