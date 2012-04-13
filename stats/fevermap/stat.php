<?php


//system("cat nodes_* > nodes.txt");
//system("bunzip2 *.bz2");


// read number of nodes per square degree
$nodes=array();
$f=fopen('nodes.txt', 'r');
if ($f) {
	while (!feof($f)) {
		$buffer = explode("\t", fgets($f, 4096));
		//print_r($buffer);
		$nodes[$buffer[1]][$buffer[2]]+=$buffer[3];
	}
	fclose($f);
}
//print_r($nodes);


/*
note the order of columns in keepright_errors file:

0 schema
1 error_id
2 error_type
3 error_name
4 object_type
5 object_id
6 state
7 description
8 first_occurrence
9 last_checked
10 object_timestamp
11 lat
12 lon
13 comment
14 comment_timestamp
15 msgid
16 txt1
17 txt2
18 txt3
19 txt4
20 txt5

*/

// read number of errors per error_type and square degree
$stats=array();
$error_types=array();
$f=fopen('keepright_errors.txt', 'r');
if ($f) {
	while (!feof($f)) {
		$buffer = explode("\t", fgets($f));
		$stats[$buffer[2]][round($buffer[11]/1e7)][round($buffer[12]/1e7)]++;
		$error_types[$buffer[2]]=$buffer[3];
//    		if ($asdf++>50000) break;
	}
	fclose($f);
}



/*
$stats=unserialize(file_get_contents('stats.dat'));
$error_types=unserialize(file_get_contents('error_types.dat'));
$nodes=unserialize(file_get_contents('nodes.dat'));
*/

file_put_contents('stats.dat', serialize($stats));
file_put_contents('error_types.dat', serialize($error_types));
file_put_contents('nodes.dat', serialize($nodes));




// create an html result document that displays the images
$html=fopen('stats.html', 'w');
fwrite($html, "<html><body>");


// write one file per error_type that contains the number
// of errors per square degree
ksort($stats);
foreach ($stats as $error_type=>$s) {
	$f=fopen('content/' . $error_type . '.txt', 'w');
	if ($f) {

		for($lat=-90;$lat<=90;$lat++) {
			for($lon=-180;$lon<=180;$lon++) {
				if ($nodes[$lat][$lon]>0) {
					$value=$s[$lat][$lon]/$nodes[$lat][$lon];
					if ($value>1) $value=1;
					$value=log($value, 10.0);
				} else
					$value=0;
				fwrite($f, "$lon\t$lat\t$value\n");
			}
			fwrite($f, "\n");
		}

		fclose($f);

		system("echo \"set pm3d map;set palette rgbformulae 21,22,23 negative;set terminal png;set output 'content/$error_type.png';splot 'content/$error_type.txt';\" | gnuplot");


		fwrite($html, "<h3>$error_type - $error_types[$error_type]</h3><img src='content/$error_type.png'><br>\n");
	}
}

fwrite($html, "</body></html>");
fclose($html);

?>