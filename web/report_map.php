<?php

require('webconfig.inc.php');
require('helpers.inc.php');

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_query($db1, "SET SESSION wait_timeout=60");



$st = htmlentities($_GET['st']);		// comma separated list of error type numbers
$lat = 1e7*htmlentities($_GET['lat']);		// center of view
$lon = 1e7*htmlentities($_GET['lon']);
$zoom = 1*htmlentities($_GET['zoom']);
$highlight_error_id=1*htmlentities($_GET['error']);

// flags for display of ignored/temp.ignored errors. Default is "on"
if (!isset($_GET['show_ign'])) $_GET['show_ign']='1';
if (!isset($_GET['show_tmpign'])) $_GET['show_tmpign']='1';

$show_ign=$_GET['show_ign']<>'0';
$show_tmpign=$_GET['show_tmpign']<>'0';


if ($highlight_error_id<>0) {
	// find lat/lon if URL specifies an error id to highlight
	$lat=0;
	$lon=0;

	$result=mysqli_query($db1, "
		SELECT lat, lon
		FROM $error_view_name
		WHERE error_id=" . addslashes($highlight_error_id)
	);

	while ($row = mysqli_fetch_array($result)) {
		$lat=$row['lat'];
		$lon=$row['lon'];
		$zoom=17;
	}
	mysqli_free_result($result);

}

// default settings: center of Vienna:
if ($lat==0) $lat=482080810;
if ($lon==0) $lon=163722146;
if ($zoom==0) $zoom=14;


// cat all checkboxes together: ch20=20&ch70=70 leads to ch=20,70
$ch='0';
for ($i=10;$i<300;$i+=10)
	if (is_numeric($_GET["ch$i"])) $ch .= ',' . $i;
//echo "ch=$ch<br>";
if (!$st) $st='open';



?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>keep right!</title>

<script type="text/javascript" src="http://www.openlayers.org/api/OpenLayers.js"></script>

<script type="text/javascript" src="<?php echo $path; ?>myPermalink.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>myTextFormat.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>myText.js"></script>

<script type="text/javascript" src="http://www.openstreetmap.org/openlayers/OpenStreetMap.js"></script>

<link rel="stylesheet" type="text/css" href="<?php echo $path; ?>style.css">
<script type="text/javascript" src="<?php echo $path; ?>outline.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>tristate-0.9.2.js"></script>


<script type="text/javascript">
	var lat=<?php echo $lat/1e7; ?>;
	var lon=<?php echo $lon/1e7; ?>;
	var zoom=<?php echo $zoom; ?>;
	var pois=null;
	var map=null;
	var plnk=null;

<?php 	//Initialise the 'map' object ?>
	function init() {
		map = new OpenLayers.Map ("map", {
			controls:[
				new OpenLayers.Control.Navigation(),
				new OpenLayers.Control.PanZoomBar(),
				new OpenLayers.Control.LayerSwitcher(),
				new OpenLayers.Control.Attribution()],

			maxExtent: new OpenLayers.Bounds(-20037508,-20037508,20037508,20037508),
			maxResolution: 156543,

			numZoomLevels: 20,
			units: 'm',
			projection: new OpenLayers.Projection("EPSG:900913"),
			displayProjection: new OpenLayers.Projection("EPSG:4326")
		} );

<?php		// add the mapnik layer ?>
		var layerMapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
		map.addLayer(layerMapnik);

<?php		// add the osmarender layer ?>
		var layerTilesAtHome = new OpenLayers.Layer.OSM.Osmarender("Osmarender");
		map.addLayer(layerTilesAtHome);

<?php		// add the open cycle map layer ?>
		var layerCycle = new OpenLayers.Layer.OSM.CycleMap("OSM Cycle Map");
		map.addLayer(layerCycle);

<?php		// add point markers layer. This is not the standard text layer but a derived version! ?>
		pois = new OpenLayers.Layer.myText("Errors on Nodes", { location:"<?php echo mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $path . 'points.php'); ?>", projection: new OpenLayers.Projection("EPSG:4326")} );
		map.addLayer(pois);


<?php		// move map center to lat/lon ?>
		var lonLat = new OpenLayers.LonLat(lon, lat).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject());
		map.setCenter(lonLat, zoom);


		plnk = new OpenLayers.Control.myPermalink();
		plnk.displayClass="olControlPermalink";
		map.addControl(plnk);


<?php		// register event that records new lon/lat coordinates in form fields after panning ?>
		map.events.register("moveend", map, function() {
			var pos = this.getCenter().clone();
			var lonlat = pos.transform(this.getProjectionObject(), new OpenLayers.Projection("EPSG:4326"));
			document.myform.lat.value=lonlat.lat
			document.myform.lon.value=lonlat.lon
			document.myform.zoom.value=this.getZoom();

			var editierlink = document.getElementById('editierlink');
			editierlink.href="http://www.openstreetmap.org/edit?lat=" + lonlat.lat + "&lon=" + lonlat.lon + "&zoom=" + this.getZoom();

			pois.loadText();		//reload markers after panning
		});
	}

<?php 	//Initialise the 'map' object ?>
	function saveComment(error_id, error_type) {
		var myfrm = document['errfrm_'+error_id];
		repaintIcon(error_id, myfrm.st, error_type);
		myfrm.submit();
		closeBubble(error_id);
	}

	function repaintIcon(error_id, state, error_type) {
<?php		// state is a reference to the option group inside the bubble's form;
		// state[0].checked==true means state==none
		// state[1].checked==true means state==ignore temporarily
		// state[2].checked==true means state==ignore
?>

		var feature_id = pois.error_ids[error_id];
		var i=0;
		var len=pois.features.length;
		var feature=null;
<?php		// find feature's id in list of features ?>
		while (i<len && feature==null) {
			if (pois.features[i].id == feature_id) feature=pois.features[i];
			i++;
		}

		if (state[0].checked) feature.marker.icon.setUrl("img/zap" + error_type + ".png")
		else if (state[1].checked) feature.marker.icon.setUrl("img/zapangel.png")
		else if (state[2].checked) feature.marker.icon.setUrl("img/zapdevil.png");
	}

<?php	// called as event handler on the cancel button ?>
	function closeBubble(error_id) {
		var feature_id = pois.error_ids[error_id];

		var i=0;
		var len=pois.features.length;
		var feature=null;
<?php		// find feature's id in list of features ?>
		while (i<len && feature==null) {
			if (pois.features[i].id == feature_id) feature=pois.features[i];
			i++;
		}
<?php		// call event handler as if one had clicked the icon ?>
		feature.marker.events.triggerEvent("mousedown");
	}

<?php	// check/uncheck all checkboxes for error type selection ?>
	function set_checkboxes(new_value) {
		for (var i = 0; i < document.myform.elements.length; ++i) {
			var el=document.myform.elements[i];
			if (el.type == "checkbox" && el.name.match(/ch[0-9]+/) != null) {
				el.checked=new_value;
			}
		}
		plnk.updateLink();
	}


<?php	// reload the error types and the permalink, which includes the error type selection ?>
	function checkbox_click() {
		pois.loadText();
		plnk.updateLink();
	}


<?php	// build the list of error type checkbox states for use in URLs
	// echo the error_type number for every active checkbox, separated with ','
 ?>
function getURL_checkboxes() {
	loc="ch=0"
	// append error types for any checked checkbox that is called "ch[0-9]+"
	for (var i = 0; i < document.myform.elements.length; ++i) {
		var el=document.myform.elements[i];
		if (el.type == "checkbox" && el.name.match(/ch[0-9]+/) != null) {
			if (el.checked)
				loc+="," + el.name.substr(2);
		}
	}
	return loc;
}

</script>


</head>
<body onload="init(); outlineInit();">

<form name="myform" method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>">
<div style="background-color:#f0fff0; font-size:0.7em; position:absolute; left:0em; width:99%; overflow:hidden; z-index:0;">

<a href="<?php echo $path; ?>"><img border=0 src="keepright.png" height="80px" alt="keep-right logo"></a><br>




<?php
// echo checkboxes for error types

$subgroup_counter=0;
$error_types=array();
$subtypes = array();

$result=mysqli_query($db1, "
	SELECT error_type, error_name
	FROM $error_types_name
	ORDER BY error_type
");

while ($row = mysqli_fetch_array($result)) {
	$et = $row['error_type'];
	$main_type=10*floor($et/10);

	if ($et == $main_type) {	// not a subtype of an error
		$error_types[$main_type]=$row['error_name'];

	} else {			// subtype of an error
		$subtypes[$main_type][$et]=$row['error_name'];
	}
}
mysqli_free_result($result);


echo "<ul class='outline'>\n";
foreach ($error_types as $et=>$en) {

	echo "<li>";
	$has_subtypes = is_array($subtypes[$et]);
	if ($has_subtypes) $subgroup_counter++;

	mkcheckbox($et, $en, $ch, !$has_subtypes, $subgroup_counter);

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
<input type='hidden' name='highlight_error_id' value='" . $highlight_error_id . "'>
<input type='hidden' name='db' value='" . $db . "'>
<input type='hidden' name='lat' value='" . $lat/1e7 . "'>
<input type='hidden' name='lon' value='" . $lon/1e7 . "'>
<input type='hidden' name='zoom' value='$zoom'>

<!-- <input type='checkbox' id='autopan' name='autopan' value='autopan'><label for='autopan'>auto-center bubbles</label><br> -->

<input type='button' value='all' onClick='javascript:set_checkboxes(true); pois.loadText();'>
<input type='button' value='none' onClick='javascript:set_checkboxes(false); pois.loadText();'><br>

<input type='checkbox' id='show_ign' name='show_ign' value='1' onclick='javascript:checkbox_click();' " . ($show_ign ? 'checked="checked"' : '') . "><label for='show_ign'>show ignored errors</label><br>

<input type='checkbox' id='show_tmpign' name='show_tmpign' value='1' onclick='javascript:checkbox_click();' " . ($show_tmpign ? 'checked="checked"' : '') . "><label for='show_tmpign'>show temp. ignored errors</label><br>

<a name='editierlink' id='editierlink' target='_blank' href='http://www.openstreetmap.org/edit?lat=" . $lat/1e7. "&lon=" . $lon/1e7 . "&zoom=$zoom'>Edit in Potlatch</a>

<div style='overflow:auto; width:20%'>
You will see up to 100 error markers starting in the center of the map. Please allow a few seconds for the error markers to appear after panning. <br>Site updated at <b>" . get_updated_date() . "</b>. Planet file downloaded at <b>" . get_planetfile_date() . "</b>
</div>

</div></form>
";


// print out calling parameters
//echo "<br>db:$db / check:$ch / state:$st / lat:$lat / lon:$lon / zoom level:$zoom'<br>";

// print out the link pointing to the points table
//echo "<a href='" . mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $path . 'points.php') . "'>points</a> ";


// the map goes in here:
echo '<div style="position:absolute; left:20%; top:0; width:79%; height:99%;" id="map"></div>' . "\n";


// this is a hidden iframe into which the JOSM-Link is called (remote control plugin)
// it is also used as target for the comment-update forms
echo '<iframe style="display:none" id="hiddenIframe" name="hiddenIframe"></iframe>';

// this is used inside myForm.js for building the form target to comment.php
echo '<div style="display:none" id="dbname" name="dbname">' . $db . '</div>';

echo "\n</body></html>";
mysqli_close($db1);




function mklink($db, $ch, $st, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $filename="") {
	return '<a href="' . mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $filename) . '">' . $label . '</a> ';
}

function mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $show_ign, $show_tmpign, $filename="") {
	return (strlen($filename)>0 ? $filename : $_SERVER['PHP_SELF']) . '?db=' . $db . '&ch=' . $ch .  '&st=' . $st .  '&lat=' . $lat/1e7 .  '&lon=' . $lon/1e7 .  '&zoom=' . $zoom . '&show_ign=' . $show_ign .  '&show_tmpign=' . $show_tmpign;
}



// draws a checkbox with icon and label for a given error type and error name
// checks the checkbox if applicable
function mkcheckbox($et, $en, $ch, $draw_checkbox=true, $subgroup_counter=0) {
	echo "\n\t<img border=0 height=12 src='img/zap$et.png' alt='error marker $et'>\n\t";

	if ($draw_checkbox) {
		echo "<input type='checkbox' id='ch$et' name='ch$et' value='1' onclick='javascript:checkbox_click();'";

		if ($ch==='0' || $_GET['ch' . $et]) echo ' checked="checked"';

		echo ">\n\t<label for='ch$et'>$en</label>\n";

	} else {
		echo "<span id='tristateBox$subgroup_counter' style='cursor: default;'>&nbsp; $en</span>\n";
	}
}

?>
