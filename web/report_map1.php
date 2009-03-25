<?php


require('Datagrid.php');
require('webconfig.inc.php');
require('helpers.inc.php');
$starttime=microtime(true);

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
$db2=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

//$db = htmlentities($_GET['db']);
$db = '*';

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


// Mödling:
//http://www.openstreetmap.org/?lat=48.0981&lon=16.28478&zoom=16&layers=B00FTF




$path_parts = pathinfo($_SERVER['SCRIPT_NAME']);
$path = $path_parts['dirname'] . ($path_parts['dirname'] == '/' ? '' : '/');

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html><head><title>keep right!</title>
<script type="text/javascript" src="<?php echo $path; ?>OpenLayers.js"></script>
<!-- <script type="text/javascript" src="http://www.openlayers.org/api/OpenLayers.js"></script> -->

<script type="text/javascript" src="<?php echo $path; ?>OpenStreetMap.js"></script>
<script type="text/javascript" src="<?php echo $path; ?>GPX.js"></script>

<script type="text/javascript">
	// Start position for the map (hardcoded here for simplicity,
	// but maybe you want to get from URL params)
	var lat=<?php echo $lat/1e7 . "\n"; ?>
	var lon=<?php echo $lon/1e7 . "\n"; ?>
	var zoom=<?php echo $zoom . "\n"; ?>
	var map; //complex object of type OpenLayers.Map

	//Initialise the 'map' object
	function init() {
		map = new OpenLayers.Map ("map", {
			controls:[
				new OpenLayers.Control.Navigation(),
				new OpenLayers.Control.PanZoomBar(),
				new OpenLayers.Control.LayerSwitcher(),
				new OpenLayers.Control.Attribution()],
			maxExtent: new OpenLayers.Bounds(-20037508.34,-20037508.34,20037508.34,20037508.34),
						maxResolution: 156543.0399,
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

		/*
		// Add the Layer with GPX Tracks
		var lgpx = new OpenLayers.Layer.GPX("Errors on Ways", "<?php 
			echo mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $path . 'tracks.php'); 
		?>", "#FF0000");
		map.addLayer(lgpx);
		*/

		// add point markers - layer
		var pois = new OpenLayers.Layer.Text("Errors on Nodes", { location:"<?php echo mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $path . 'points1.php'); ?>", projection: new OpenLayers.Projection("EPSG:4326")} );
		map.addLayer(pois);

		// move map center to lat/lon
		var lonLat = new OpenLayers.LonLat(lon, lat).transform(new OpenLayers.Projection("EPSG:4326"), map.getProjectionObject());
		map.setCenter(lonLat, zoom);

		map.addControl(new OpenLayers.Control.Permalink() )

		// register event that records new lon/lat coordinates in form fields after panning
		map.events.register("moveend", map, function() { 
			var pos = this.getCenter().clone();
			var lonlat = pos.transform(this.getProjectionObject(), new OpenLayers.Projection("EPSG:4326"));
			document.myform.lat.value=lonlat.lat
			document.myform.lon.value=lonlat.lon
			document.myform.zoom.value=this.getZoom();

			var editierlink = document.getElementById('editierlink');
			editierlink.href="http://openstreetmap.org/edit?lat=" + lonlat.lat + "&lon=" + lonlat.lon + "&zoom=" + this.getZoom();

		});
	}
</script>
</head>
<body onload="init();">

<?php

echo '<form name="myform" method="get" action="' . $_SERVER['PHP_SELF'] . '">';
/*echo '<select name="db"><option value="*">all databases</option>';
$result=query("SELECT db_name FROM error_view GROUP BY db_name", $db1, false);
while ($row = db_fetch_array($result)) {
	if (!$db) $db=$row['db_name'];	// set default
	//echo mklink($row['db_name'], $ch, $st, $row['db_name']);
	echo '<option value="' . $row['db_name'] . '">' . $row['db_name'] . '</option>';
}
echo "</select><br>\n";
db_free_result($result);*/

echo '<div style="background-color:#f0fff0; font-size:0.7em; white-space:nowrap; float:left; left:0.5em; top:0.5em; width:20%">
<a href="/"><img border=0 src="keepright.png" alt="keep-right logo"></a><br><br>';


//echo '<input type="checkbox" name="ch" value="*"/>all<br>';



$result=mysqli_query($db1, "
	SELECT error_type, error_name
	FROM $error_types_name
	ORDER BY error_type
");
while ($row = mysqli_fetch_array($result)) {
	echo '<img border=0 height=12 src="img/zap' . $row['error_type'] . '.png" alt="error marker ' . $row['error_type'] . '">';
	echo '<input type="checkbox" id="ch' . $row['error_type'] . '" name="ch' . $row['error_type'] . '" value="' . $row['error_type'] . '"';
	if ($ch==='0' || $_GET['ch' . $row['error_type']]) echo ' checked="checked"';
	echo '><label for="ch' . $row['error_type'] . '">' . $row['error_name'] . "</label><br>\n";
}
echo "<br>\n";
mysqli_free_result($result);



/*echo "<select name='state'>
	<option " . (($st=='all')?'selected':'') . " value='all'>all</option>
	<option " . (($st=='open')?'selected':'') . " value='open'>open errors</option>
	<option " . (($st=='cleared')?'selected':'') . " value='cleared'>cleared errors</option>
</select><br>";*/
echo "<input size=5 type='hidden' name='lat' value='" . $lat/1e7 . "'>
<input size=5 type='hidden' name='lon' value='" . $lon/1e7 . "'>
<input size=3 type='hidden' name='zoom' value='$zoom'>
<input type='submit' name='requery' value='requery'>

<a name='editierlink' id='editierlink' target='_blank' href='http://openstreetmap.org/edit?lat=" . $lat/1e7. "&lon=" . $lon/1e7 . "&zoom=$zoom'>Edit in Potlatch</a>

<br><br>Please press requery after panning <br>the map! You will see up to 100 <br>error markers starting in the <br>center of the map. Please allow a <br>few seconds for the error markers <br>to appear. Planet file downloaded <br>at " . trim(file_get_contents('updated.inc')) . ".


</div></form>

";


// print out calling parameters
//echo "<br>db:$db / check:$ch / state:$st / lat:$lat / lon:$lon / zoom level:$zoom'<br>";

// print out the link pointing to the points table
//echo "<a href='" . mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $path . 'points.php') . "'>points</a> ";

// print out the link pointing to the way tracks file
//echo "<a href='" . mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $path . 'tracks.php') . "'>tracks</a>";




// the map goes in here:
echo '<div style="margin-left:21%; top:0;" id="map"></div>';

// this is a hidden iframe into which the JOSM-Link is called (remote control plugin)
echo '<iframe style="display:none" id="iframeForJOSM" name="iframeForJOSM"></iframe>';



echo '</body></html>';
mysqli_close($db1);
mysqli_close($db2);




function mklink($db, $ch, $st, $label, $lat, $lon, $zoom, $filename="") {
	return '<a href="' . mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $filename) . '">' . $label . '</a> ';
}

function mkurl($db, $ch, $st, $label, $lat, $lon, $zoom, $filename="") {
	return (strlen($filename)>0 ? $filename : $_SERVER['PHP_SELF']) . '?db=' . $db . '&ch=' . $ch .  '&st=' . $st .  '&lat=' . $lat/1e7 .  '&lon=' . $lon/1e7 .  '&zoom=' . $zoom;
}

?>
