#!/usr/bin/php

<?php

//This script takes the schema definitions and generates a svg drawing of them.



require("../config/schemas.php");

$fh = fopen("map.svg", "w");

$head = <<<ST
<?xml version="1.0" encoding="ISO-8859-1" standalone="no" ?>
<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 20010904//EN"
  "http://www.w3.org/TR/2001/REC-SVG-20010904/DTD/svg10.dtd">
<svg width="3145" height="2937" xmlns="http://www.w3.org/2000/svg"
  xmlns:xlink="http://www.w3.org/1999/xlink">
  <title>World Schema Files</title>
  <style>
		rect {
			stroke:black;
			fill:none;
			}
		text {
			fill:black;
			text-anchor:middle;
			}
	</style>
<image x="0" y="0" width="3145px" height="2953px"
    xlink:href="map.jpg"/>
ST;

fwrite($fh,$head); 


foreach ($schemas as $k => $v) {
	$lr = deg2rad($schemas[$k]['left']);
	$rr = deg2rad($schemas[$k]['right']);
	$tr = deg2rad(min(84,$schemas[$k]['top']));
	$br = deg2rad(max(-84,$schemas[$k]['bottom']));

  $tm = .5 * log((1+sin($tr))/(1-sin($tr)));
	$bm = .5 * log((1+sin($br))/(1-sin($br)));
	
	$x = $lr*500+1570;
	$y = 1474-$tm*500;
	$w = ($rr-$lr)*500;
	$h = ($tm-$bm)*500;

	$tx = $x + $w/2;
	$ty = $y + $h/2+6;

	$str = "<g><rect x=\"$x\" y=\"$y\" width=\"$w\" height=\"$h\" />\n";
	$str .= "<text x=\"$tx\" y=\"$ty\">$k</text>\n";
	$str .= "</g>\n";
	fwrite($fh,$str);
	}

fwrite($fh,"</svg>");

fclose($fh);

?>