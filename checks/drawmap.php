#!/usr/bin/php

<?php

//This script takes the schema definitions and generates a svg drawing of them.



require("../config/schemas.php");

$fh = fopen("map.svg", "w");

$head = <<<ST
<?xml version="1.0" encoding="ISO-8859-1" standalone="no" ?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 20010904//EN"
  "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">
<svg width="2032" height="1575" xmlns="http://www.w3.org/2000/svg"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <title>World Schema Files</title>
  <style>
		rect {
			stroke:black;
			fill:none;
			}
		text {
                        font-size:8px;
			fill:black;
			text-anchor:middle;
			}
	</style>
<image x="0" y="0" width="2032px" height="1575px"
    xlink:href="map.jpg"/>
ST;

fwrite($fh,$head); 


foreach ($schemas as $k => $v) {
	$lr = deg2rad($schemas[$k]['left']);
	$rr = deg2rad($schemas[$k]['right']);
	$tr = deg2rad(min(80,$schemas[$k]['top']));
	$br = deg2rad(max(-80,$schemas[$k]['bottom']));

  $tm = .5 * log((1+sin($tr))/(1-sin($tr)));
	$bm = .5 * log((1+sin($br))/(1-sin($br)));
	
	$x = $lr*323.57+1016;
	$y = 787.5-$tm*323.57;
	$w = ($rr-$lr)*323.57;
	$h = ($tm-$bm)*323.57;

	$tx = $x + $w/2;
	$ty = $y + $h/2+3;

	$str = "<g><rect x=\"$x\" y=\"$y\" width=\"$w\" height=\"$h\" />\n";
	$str .= "<text x=\"$tx\" y=\"$ty\">$k</text>\n";
	$str .= "</g>\n";
	fwrite($fh,$str);
	}

fwrite($fh,"</svg>");

fclose($fh);

?>
