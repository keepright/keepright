<?php

// calculate the minimum distance of point P to a line built from A and B
function distance_point_segment($Px, $Py, $Ax, $Ay, $Bx, $By) {
/*
                 P
                *
             /  |
       AP /     |
       /        | d
    /  alfa     |
  *---------------------------------------*
  A                  AB                   B

given:	straight line vom A to B 
	Point P


    ( Px - Ax )
AP= (         )
    ( Py - Ay )


    ( Bx - Ax )
AB= (         )
    ( By - Ay )


equation for straight line: X = A + AB * k
where k is scalar. 
k==0 leads to X==A
k==1 leads to X==B

find the one k where distance from P to X becomes a minimum

PX = | P - A - AB * k|

|PX|² = [Px - Ax - (Bx-Ax)k]² + [Py - Ay - (By-Ay)k]²

d(|PX|²) / dk = 0
leads to
    (Px-Ax)(Bx-Ax) + (Py-Ay)(By-Ay)
k = --------------------------------
          (Bx-Ax)² + (By-Ay)²
*/

	//printf("P(%01.2f, %01.2f) A(%01.2f, %01.2f) B(%01.2f, %01.2f)\n", $Px, $Py, $Ax, $Ay, $Bx, $By);

	$denominator = pow($Bx-$Ax,2) + pow($By-$Ay,2);
	
	// denominator may be zero if A==B. return the distance from P to A in this case
	if ($denominator==0) {
		return sqrt( pow($Px-$Ax,2) + pow($Py-$Ay,2) );
	} else {
		$k = (($Px-$Ax)*($Bx-$Ax) + ($Py-$Ay)*($By-$Ay)) / $denominator;
		//echo "k=$k ";

		// cut off k at 0/1 because only points between A and B are valid
		if ($k<0) $k=0;
		if ($k>1) $k=1;
		$Xx=$Ax + ($Bx-$Ax)*$k;		// X is the one point on the line that is nearest to P
		$Xy=$Ay + ($By-$Ay)*$k;
		return sqrt( pow($Px-$Xx,2) + pow($Py-$Xy,2) );		// distance PX
	}
}	



function tile_for_xy($ix, $yps) {
	$t=0;
	$mask=1;
	$adder=1;
	$x=1*$ix;
	$y=1*$yps;
	//echo "\t$x\t$y";
	for ($i=0; $i<16; $i++) {
		if ($y & $mask) $t += $adder;
		$adder*=2;
	
		if ($x & $mask) $t += $adder;
		$mask*=2;
		$adder*=2;
	}

	return $t;
}
function xy_for_tile($tile) {
	$x=0; 
	$y=0;
	$mask=1;
	$adder=1;
	$t=1*$tile;

	for ($i=0; $i<16; $i++) {
		if ($t & $mask) $y += $adder;
		$mask*=2;
	
		if ($t & $mask) $x += $adder;
		$mask*=2;
		$adder*=2;
	}
	return array('x'=>$x, 'y'=>$y);
}

function lon2x($lon) {
	return round(($lon + 180 ) * 65535 / 360);
}

function lat2y($lat) {
	return round(($lat + 90) * 65535 / 180);
}

function x2lon($x) {
	return ($x*360/65535) - 180;
}

function y2lat($y) {
	return ($y*180/65535)-90;
}


// execute $sql using database link $link
// echo debug messages if $debug is set
function query($sql, $link, $debug=true) {
	if ($debug) {
		echo "\n\n" . rtrim(preg_replace('/(\s)\s+/', '$1', $sql)) . "\n";
		$starttime=microtime(true);
	}
	//$result=mysql_unbuffered_query($sql, $link);
	$result=mysqli_query($link, $sql, MYSQLI_USE_RESULT);
	if (!$result) {
		$message  = 'Invalid query: ' . mysqli_errno($link) . ": " . mysqli_error($link) . "\n";
		$message .= 'Whole query: ' . $sql . "\n";
		$message .= 'Query result: ' . $result . "\n";
		echo($message);
	}
	if ($debug) echo format_time(microtime(true)-$starttime) ."\n";
	return $result;
}



/*
http://wiki.openstreetmap.org/index.php/Mercator
Php Code by Erhan Baris 19:19, 01.09.2007

START

*/

function deg_rad($ang)
{
	return (float)((float)$ang * (float)(M_PI / 180.0));
}

function merc_x($lon)
{
	$r_major = 6378137.000;
	return (float)($r_major * deg_rad($lon));
}

function merc_y($lat)
{
	if ($lat > 89.5) $lat = 89.5;
	if ($lat < -89.5) $lat = -89.5;
	$r_major = 6378137.000;
	$r_minor = 6356752.3142;
	$temp = $r_minor / $r_major;
	$es = 1.0 - ($temp * $temp);
	$eccent = sqrt($es);
	$phi = deg_rad($lat);
	$sinphi = sin($phi);
	$con = $eccent * $sinphi;
	$com = 0.5 * $eccent;
	$con = pow(((1.0-$con)/(1.0+$con)), $com);
	$ts = tan(0.5 * ((M_PI*0.5) - $phi))/$con;
	$y = 0 - $r_major * log($ts);
	return $y;
}

function merc($x,$y) {
    return array('x'=>merc_x($x),'y'=>merc_y($y));
}

/*
http://wiki.openstreetmap.org/index.php/Mercator
Php Code by Erhan Baris 19:19, 01.09.2007

END

*/


function create_mysql_functions($db) {
	query("DROP FUNCTION IF EXISTS deg_rad", $db, false);
	query("DROP FUNCTION IF EXISTS merc_x", $db, false);
	query("DROP FUNCTION IF EXISTS merc_y", $db, false);
	
	query("
		CREATE FUNCTION deg_rad (ang DOUBLE) RETURNS DOUBLE NO SQL
		BEGIN 
			RETURN ang * PI() / 180.0;
		END
	", $db, false);
	
	query("
		CREATE FUNCTION merc_x (lon INT(11)) RETURNS DOUBLE NO SQL
		BEGIN 
			RETURN 6378137.000 * deg_rad(lon);
		END
	", $db, false);
	
	query("
		CREATE FUNCTION merc_y (lat1 INT(11)) RETURNS DOUBLE NO SQL
		BEGIN 
			DECLARE lat DOUBLE;
			DECLARE r_major DOUBLE;
			DECLARE r_minor DOUBLE;
			DECLARE eccent DOUBLE;
			DECLARE phi DOUBLE;
			DECLARE con DOUBLE;
			DECLARE com DOUBLE;
		
			SET lat=lat1;
			IF lat1 > 89.5 THEN 
				SET lat = 89.5; 
			END IF;
			IF lat1 < -89.5 THEN 
				SET lat = -89.5; 
			END IF;
			SET r_major = 6378137.000;
			SET r_minor = 6356752.3142;
			SET eccent = SQRT(1.0 - POW(r_minor / r_major, 2));
			SET phi = deg_rad(lat);
			SET con = eccent * sin(phi);
			SET com = 0.5 * eccent;
			SET con = POW(((1.0-con)/(1.0+con)), com);
			RETURN 0 - r_major * LOG(TAN(0.5 * ((PI()*0.5) - phi))/con);
		END
	", $db, false);
}

function drop_mysql_functions($db) {
	query("DROP FUNCTION IF EXISTS deg_rad;", $db, false);
	query("DROP FUNCTION IF EXISTS merc_x;", $db, false);
	query("DROP FUNCTION IF EXISTS merc_y;", $db, false);
}



// gets a time value in seconds and writes it in s, min, h
// according to its amount
function format_time($t) {
	if ($t<60) {
		return sprintf("%01.2fs", $t);						// seconds
	} elseif ($t<3600) {
		return sprintf("%01.0fm %01.0fs", floor($t/60), $t % 60);		// minutes
	} else 
		return sprintf("%01.0fh %01.0fm", floor($t/3600), ($t % 3600)/60);	// hours
}
?>