<?php

require('webconfig.inc.php');
require('helpers.inc.php');
$starttime=microtime(true);

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
$db2=mysqli_connect($db_host, $db_user, $db_pass, $db_name);



$st = htmlentities($_GET['st']);
$lat = 1e7*htmlentities($_GET['lat']);
$lon = 1e7*htmlentities($_GET['lon']);
$zoom = 1*htmlentities($_GET['zoom']);

if ($lat==0) $lat=482080810;
if ($lon==0) $lon=163722146;
if ($zoom==0) $zoom=14;


// cat all checkboxes together: ch20=20&ch70=70 leads to ch=20,70
$ch='0';
for ($i=10;$i<300;$i+=10)
	if (is_numeric($_GET["ch$i"])) $ch .= ',' . $i;
//echo "ch=$ch<br>";
if (!$st) $st='open';



$path_parts = pathinfo($_SERVER['SCRIPT_NAME']);
$path = $path_parts['dirname'] . ($path_parts['dirname'] == '/' ? '' : '/');

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>keep right!</title>

<script type="text/javascript" src="http://www.openlayers.org/api/OpenLayers.js"></script>
<!-- <script type="text/javascript" src="<?php echo $path; ?>OpenLayers.js"></script> -->

<script type="text/javascript" src="<?php echo $path; ?>myTextFormat.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>myText.js"></script>
<script type="text/javascript" src="http://openstreetmap.org/openlayers/OpenStreetMap.js"></script>

<script type="text/javascript">
	var lat=<?php echo $lat/1e7; ?>;
	var lon=<?php echo $lon/1e7; ?>;
	var zoom=<?php echo $zoom; ?>;
	var pois=null;
	var map=null;

	//Initialise the 'map' object
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

		// add the mapnik layer
		var layerMapnik = new OpenLayers.Layer.OSM.Mapnik("Mapnik");
		map.addLayer(layerMapnik);

		// add the osmarender layer
		var layerTilesAtHome = new OpenLayers.Layer.OSM.Osmarender("Osmarender");
		map.addLayer(layerTilesAtHome);


		// add point markers layer. This is not the standard text layer but a derived version!
		pois = new OpenLayers.Layer.myText("Errors on Nodes", { location:"<?php echo mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $path . 'points.php'); ?>", projection: new OpenLayers.Projection("EPSG:4326")} );
		map.addLayer(pois);


		// move map center to lat/lon
		var lonLat = new OpenLayers.LonLat(lon, lat).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject());
		map.setCenter(lonLat, zoom);

		map.addControl(new OpenLayers.Control.Permalink());

		// register event that records new lon/lat coordinates in form fields after panning
		map.events.register("moveend", map, function() {
			var pos = this.getCenter().clone();
			var lonlat = pos.transform(this.getProjectionObject(), new OpenLayers.Projection("EPSG:4326"));
			document.myform.lat.value=lonlat.lat
			document.myform.lon.value=lonlat.lon
			document.myform.zoom.value=this.getZoom();

			var editierlink = document.getElementById('editierlink');
			editierlink.href="http://openstreetmap.org/edit?lat=" + lonlat.lat + "&lon=" + lonlat.lon + "&zoom=" + this.getZoom();

			pois.loadText();		//reload markers after panning
		});
	}

	function saveComment(error_id, error_type) {
		var myfrm = document['errfrm_'+error_id];
		repaintIcon(error_id, myfrm.st, error_type);
		myfrm.submit();
		closeBubble(error_id);
	}

	function repaintIcon(error_id, state, error_type) {
		// state is a reference to the option group inside the bubble's form;
		// state[0].checked==true means state==none
		// state[1].checked==true means state==ignore temporarily
		// state[2].checked==true means state==ignore


		var feature_id = pois.error_ids[error_id];

		var i=0;
		var len=pois.features.length;
		var feature=null;
		// find feature's id in list of features
		while (i<len && feature==null) {
			if (pois.features[i].id == feature_id) feature=pois.features[i];
			i++;
		}

		if (state[0].checked) feature.marker.icon.setUrl("img/zap" + error_type + ".png")
		else if (state[1].checked) feature.marker.icon.setUrl("img/zapangel.png")
		else if (state[2].checked) feature.marker.icon.setUrl("img/zapdevil.png");
	}

	function closeBubble(error_id) {
		var feature_id = pois.error_ids[error_id];

		var i=0;
		var len=pois.features.length;
		var feature=null;
		// find feature's id in list of features
		while (i<len && feature==null) {
			if (pois.features[i].id == feature_id) feature=pois.features[i];
			i++;
		}
		// call event handler as if one had clicked the icon
		feature.marker.events.triggerEvent("mousedown");
	}

	function set_checkboxes(new_value) {
		for (var i = 0; i < document.myform.elements.length; ++i) {
			var el=document.myform.elements[i];
			if (el.type == "checkbox" && el.name.match(/ch[0-9]+/) != null) {
				el.checked=new_value;
			}
		}
	}


</script>

<style type="text/css">
<!--
.p1 { margin:0px; margin-right:13px; border-style:solid; border-width:thin; border-color:#800000; background-color:#FF8080; }
.p2 { margin:5px; padding-top:3px; padding-bottom:3px;}
.p3 { background-color:#FFFFD0; }
-->
</style>
</head>
<body onload="init();">

<?php

echo '<form name="myform" method="get" action="' . $_SERVER['PHP_SELF'] . '">' . "\n";

echo '<div style="background-color:#f0fff0; font-size:0.7em; position:absolute; left:0em; width:99%; overflow:hidden; z-index:0;">

<a href="/"><img border=0 src="keepright.png" height="80px" alt="keep-right logo"></a><br><br>';

// echo checkboxes for error types

//echo '<input type="checkbox" name="ch" value="*"/>all<br>';

$result=mysqli_query($db1, "
	SELECT error_type, error_name
	FROM $error_types_name
	ORDER BY error_type
");
while ($row = mysqli_fetch_array($result)) {
	echo '<img border=0 height=12 src="img/zap' . $row['error_type'] . '.png" alt="error marker ' . $row['error_type'] . '">';

	echo '<input type="checkbox" id="ch' . $row['error_type'] . '" name="ch' . $row['error_type'] . '" value="' . $row['error_type'] . '" onclick="pois.loadText();"';

	if ($ch==='0' || $_GET['ch' . $row['error_type']]) echo ' checked="checked"';

	echo '><label for="ch' . $row['error_type'] . '">' . $row['error_name'] . "</label><br>\n";
}
echo "<br>\n";
mysqli_free_result($result);



echo "
<input type='hidden' name='db' value='" . $db . "'>
<input type='hidden' name='lat' value='" . $lat/1e7 . "'>
<input type='hidden' name='lon' value='" . $lon/1e7 . "'>
<input type='hidden' name='zoom' value='$zoom'>

<!-- <input type='checkbox' id='autopan' name='autopan' value='autopan'><label for='autopan'>auto-center bubbles</label><br> -->

<input type='button' value='all' onClick='javascript:set_checkboxes(true); pois.loadText();'>
<input type='button' value='none' onClick='javascript:set_checkboxes(false); pois.loadText();'><br>


<a name='editierlink' id='editierlink' target='_blank' href='http://openstreetmap.org/edit?lat=" . $lat/1e7. "&lon=" . $lon/1e7 . "&zoom=$zoom'>Edit in Potlatch</a>

<div style='overflow:auto; width:20%'>
You will see up to 100 error markers starting in the center of the map. Please allow a few seconds for the error markers to appear after panning. <br>Planet file downloaded at <b>" . trim(file_get_contents('updated.inc')) . "</b>
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
mysqli_close($db2);




function mklink($db, $ch, $st, $label, $lat, $lon, $zoom, $filename="") {
	return '<a href="' . mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $filename) . '">' . $label . '</a> ';
}

function mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $filename="") {
	return (strlen($filename)>0 ? $filename : $_SERVER['PHP_SELF']) . '?db=' . $db . '&ch=' . $ch .  '&st=' . $st .  '&lat=' . $lat/1e7 .  '&lon=' . $lon/1e7 .  '&zoom=' . $zoom;
}

?>
