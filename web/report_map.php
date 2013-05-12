<?php

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_query($db1, "SET SESSION wait_timeout=60");


// first of all take parameters from cookie, if exist
if ($cookie) {
	$lon=1e7*$cookie[0];
	$lat=1e7*$cookie[1];
	$zoom=$cookie[2];
}

// second: evaluate URL parameters, overwriting cookie-defaults
if (isset($_GET['lat'])) $lat = 1e7*htmlentities($_GET['lat']);		// center of view
if (isset($_GET['lon'])) $lon = 1e7*htmlentities($_GET['lon']);
if (isset($_GET['zoom'])) $zoom = 1*htmlentities($_GET['zoom']);
$highlight_error_id=1*addslashes(htmlentities($_GET['error']));	// error_id and schema name of a specific error the user wants to see
$highlight_schema=1*addslashes(htmlentities($_GET['schema']));

// flags for display of ignored/temp.ignored errors. Default is "on"
if (!isset($_GET['show_ign'])) $_GET['show_ign']='1';
if (!isset($_GET['show_tmpign'])) $_GET['show_tmpign']='1';

$show_ign=$_GET['show_ign']<>'0';
$show_tmpign=$_GET['show_tmpign']<>'0';


// third: requests for a specific error overwrite lat/lon from URL
if ($highlight_error_id<>0) {
	// find lat/lon if URL specifies an error id to highlight
	$lat=0;
	$lon=0;

	$result=mysqli_query($db1, "
		SELECT lat, lon
		FROM error_view_" . $highlight_schema . "
		WHERE error_id=" . $highlight_error_id ."
		LIMIT 1"
	);

	while ($row = mysqli_fetch_array($result)) {
		$lat=$row['lat'];
		$lon=$row['lon'];
		$zoom=17;
	}
	mysqli_free_result($result);
}


// fourth: if none of the above exists, use default settings: center of Vienna:
if ($lat==0) $lat=482080810;
if ($lon==0) $lon=163722146;
if ($zoom==0) $zoom=14;


$ch=$_GET["ch"];				// comma separated list of error types to display
if (!isset($ch)) $ch='0';
$checks_selected = split(',', $ch);
if ($cookie) $checks_to_hide = split(',', $cookie[3]); else $checks_to_hide=array();
//echo "ch=$ch<br>";
//print_r($checks_selected);
//print_r($checks_to_hide);
?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>keep right!</title>

<!-- <link rel="alternate" type="application/rss+xml"
   title="RSS" href="export.php" /> -->

<script type="text/javascript" src="http://www.openlayers.org/api/OpenLayers.js"></script>
<script type="text/javascript" src="keepright-min.js"></script>
<script type="text/javascript" src="http://www.openstreetmap.org/openlayers/OpenStreetMap.js"></script>
<link rel="stylesheet" type="text/css" href="style.css">

<script type="text/javascript">
	var lat=<?php echo $lat/1e7; ?>;
	var lon=<?php echo $lon/1e7; ?>;
	var zoom=<?php echo $zoom; ?>;
	var poisURL="<?php echo mkurl($ch, $st, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $path . 'points.php'); ?>";
	var pois=null;
	var map=null;
	var plnk=null;

<?php 	// these text snippets will be used in myText.js, where the bubbles are created ?>
	var txt4="<?php echo T_gettext('edit in'); ?>";
	var txt5="<?php echo T_gettext('JOSM'); ?>";
	var txt6="<?php echo T_gettext('JOSM must be running and JOSM\'s remote control plugin must be enabled for this to work!'); ?>";
	var txt7="<?php echo T_gettext('Potlatch'); ?>";
	var txt8="<?php echo T_gettext('keep this error open'); ?>";
	var txt9="<?php echo T_gettext('ignore temporarily (error corrected)'); ?>";
	var txt10="<?php echo T_gettext('ignore (false-positive)'); ?>";
	var txt11="<?php echo T_gettext('save'); ?>";
	var txt12="<?php echo T_gettext('cancel'); ?>";
	var txt13="<?php echo T_gettext('please click on the icon to fixate the bubble'); ?>";
	var txt14="<?php echo T_gettext('link to here: error #'); ?>";
	var txt15="<?php echo T_gettext('last edit of this'); ?>";

</script>
</head>

<body onload="init(); outlineInit(); ">

<form name="myform" method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<div style="background-color:#f0fff0; font-size:0.7em; position:absolute; left:0em; width:99%; overflow:hidden; z-index:0;">

<a href="<?php echo $path; ?>"><img border=0 src="keepright.png" height="80px" alt="keep-right logo"></a>&nbsp;
<?php

language_selector();

//echo "<pre>"; print_r($cookie); echo "</pre>";

// echo checkboxes for error types
$subgroup_counter=0;
$error_types=array();
$subtypes = array();

$result=mysqli_query($db1, "
	SELECT error_type, error_name, error_class, hidden
	FROM $error_types_name
	ORDER BY error_class, error_type
");

while ($row = mysqli_fetch_array($result)) {
	$et = $row['error_type'];
	$main_type=10*floor($et/10);

	if ($row['hidden']==0 || in_array($et, $checks_selected)) {	// display non-hidden errors and hidden errors that are explicitly selected

		if ($et == $main_type) {	// not a subtype of an error
			$error_types[$main_type]=array($row['error_class'], $row['error_name']);

		} else {			// subtype of an error
			$subtypes[$main_type][$et]=$row['error_name'];
		}
	}
}
mysqli_free_result($result);


$class='';
echo "<ul class='outline'>\n";
foreach ($error_types as $et=>$e) {

	if ($class!=$e[0]) {
		echo "<li><i>" . T_gettext($e[0] . 's') ."</i></li>";
		$class=$e[0];
	}

	echo "<li>";
	$has_subtypes = is_array($subtypes[$et]);
	if ($has_subtypes) $subgroup_counter++;

	mkcheckbox($et, $e[1], $ch, !$has_subtypes, $subgroup_counter, $class);

	if ($has_subtypes) {
		echo "<ul><div id='subgroup$subgroup_counter'>";
		foreach ($subtypes[$et] as $st=>$sn) {
			echo "<li>";
			mkcheckbox($st, $sn, $ch);
			echo "</li>";
		}
		echo '</div></ul>';
	}

	echo "</li>\n";
}
echo "</ul>\n";


echo "<script type='text/javascript'>\n";
for ($i=1;$i<=$subgroup_counter;$i++)
	echo "\tinitTriStateCheckBox('tristateBox$i', 'subgroup$i', false, function() { checkbox_click(); } );\n";
echo "</script>\n";




echo "
<input type='hidden' name='number_of_tristate_checkboxes' value='" . $subgroup_counter . "'>
<input type='hidden' name='highlight_error_id' value='" . $highlight_error_id . "'>
<input type='hidden' name='highlight_schema' value='" . $highlight_schema . "'>
<input type='hidden' name='lat' value='" . $lat/1e7 . "'>
<input type='hidden' name='lon' value='" . $lon/1e7 . "'>
<input type='hidden' name='zoom' value='$zoom'>

<input type='button' value='" . T_gettext('all') . "' onClick='javascript:set_checkboxes(true); pois.loadText();'>
<input type='button' value='" . T_gettext('none') . "' onClick='javascript:set_checkboxes(false); pois.loadText();'><br>

<input type='checkbox' id='show_ign' name='show_ign' value='1' onclick='javascript:checkbox_click();' " . ($show_ign ? 'checked="checked"' : '') . "><label for='show_ign'>" . T_gettext('show ignored errors') . "</label><br>

<input type='checkbox' id='show_tmpign' name='show_tmpign' value='1' onclick='javascript:checkbox_click();' " . ($show_tmpign ? 'checked="checked"' : '') . "><label for='show_tmpign'>" . T_gettext('show temp. ignored errors') . "</label><br>

<a name='editierlink' id='editierlink' target='_blank' href='http://www.openstreetmap.org/edit?lat=" . $lat/1e7. "&lon=" . $lon/1e7 . "&zoom=$zoom'>" . T_gettext('Edit in Potlatch') . "</a>
<a id='rsslink' href='export.php'>RSS</a> <a id='gpxlink' href='export.php'>GPX</a>


<div style='overflow:auto; width:20%'>";
printf(T_gettext('You will see up to %d error markers starting in the center of the map. Please allow a few seconds for the error markers to appear after panning.'), 100) . '<br>';

printf(T_gettext('Site updated at %s'), get_updated_date(find_schema($db1, $lat, $lon)));

echo "<br></div>
</div></form>
";


// print out calling parameters
//echo "<br>check:$ch / lat:$lat / lon:$lon / zoom level:$zoom'<br>";

// print out the link pointing to the points table
//echo "<a href='" . mkurl($ch, $label, $lat, $lon, $zoom, $path . 'points.php') . "'>points</a> ";


// the map goes in here:
echo '<div style="position:absolute; left:20%; top:0; width:79%; height:99%;" id="map"></div>';


// this is a hidden iframe into which the JOSM-Link is called (remote control plugin)
// it is also used as target for the comment-update forms
echo '<iframe style="display:none" id="hiddenIframe" name="hiddenIframe"></iframe>';

echo "\n</body></html>";
mysqli_close($db1);




function mklink($ch, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $filename="") {
	return '<a href="' . mkurl($ch, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $filename) . '">' . $label . '</a> ';
}

function mkurl($ch, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $filename="") {
	return (strlen($filename)>0 ? $filename : $_SERVER['PHP_SELF']) . '?ch=' . $ch . '&lat=' . $lat/1e7 . '&lon=' . $lon/1e7 . '&zoom=' . $zoom . '&show_ign=' . $show_ign . '&show_tmpign=' . $show_tmpign;
}



// draws a checkbox with icon and label for a given error type and error name
// checks the checkbox if applicable
function mkcheckbox($et, $en, $ch, $draw_checkbox=true, $subgroup_counter=0, $class='error') {
	global $checks_selected, $checks_to_hide;
	echo "\n\t<img border=0 height=12 src='img/zap" . 10*floor($et/10) . ".png' alt='error marker $et'>\n\t"; 			// use icon 190 for types 191-199

	if ($draw_checkbox) {
		echo "<input type='checkbox' id='ch$et' name='ch$et' value='1' onclick='javascript:checkbox_click();'";

		if (($ch==='0' && $class==='error' && !in_array($et, $checks_to_hide)) || in_array($et, $checks_selected)) echo ' checked="checked"';

		echo ">\n\t<label for='ch$et'>" . T_gettext($en) . "</label>\n";

	} else {
		echo "<span id='tristateBox$subgroup_counter' style='cursor: default;'>&nbsp; " . T_gettext($en) . "</span>\n";
	}
}

?>