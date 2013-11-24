#!/usr/bin/php

<?php

error_reporting(E_ALL ^ E_NOTICE);
/*
$nodes=array();
$stats=array();
$error_types=array();
echo "Preparing array\n";
for($x=-180;$x<=180;$x++) {
  for($y=-90;$y<=90;$y++) {
    $nodes[$y][$x] = 0;
    $stats[$y][$x] = 0;
    }
  }*/

// read number of nodes per square degree
echo "Reading node files\n";
foreach(glob("../results/nodes*.txt") as $file) {
  echo "Reading $file\n";
  $fin = fopen($file, "r");
  while(!feof($fin)) {
    $l = fscanf($fin,"%u %f %f %u");
    $nodes[($l[1])*10][($l[2])*10] += $l[3];
    }

  fclose($fin);
  }


// read number of errors per error_type and square degree
foreach(glob("../results/error_view*.txt") as $file) {
  print("Reading $file\n");

  $f=fopen($file, 'r');
  if ($f) {
    while (!feof($f)) {
      $buffer = explode("\t", fgets($f));
      $stats[$buffer[2]][round($buffer[11]/1e6)][round($buffer[12]/1e6)]++;
      $error_types[$buffer[2]]=$buffer[3];
      }
    fclose($f);
    }
  }


foreach ($stats as $error_type=>$s) {
//   if ($error_type != 130) continue;
  $f=fopen('../tmp/' . $error_type . '.txt', 'w');
  if ($f) {

    for($lat=-900;$lat<=900;$lat++) {
      for($lon=-1800;$lon<=1800;$lon++) {
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

    system("echo \"set pm3d map; set key off; set palette rgbformulae 21,22,23 negative;set terminal png size 2300,1500;set output 'content/$error_type.png';splot '../tmp/$error_type.txt';\" | gnuplot");


//     fwrite($html, "<h3>$error_type - $error_types[$error_type]</h3><img src='../tmp/$error_type.png'><br>\n");
  }
}






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
?>