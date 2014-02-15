#!/usr/bin/php

<?php


$opt = getopt("t::");

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
  if(array_key_exists('t',$opt) && $opt['t']>0)  {
    $p = pcntl_fork();
    }
  else {
    $p = -1;
    }
  if($p<=0) {
    $f=fopen('../tmp/' . $error_type . '.txt', 'w');
    if ($f) {
      for($lat=-900;$lat<=900;$lat++) {
        for($lon=-1800;$lon<=1800;$lon++) {
          if ($nodes[$lat][$lon]>0) {
            $value=$s[$lat][$lon]/$nodes[$lat][$lon];
            if ($value>1) $value=1;
            $value=log($value, 10.0);
            } 
          else
            $value=1;
          fwrite($f, "$lon\t$lat\t$value\n");
          }
        fwrite($f, "\n");
        }

      fclose($f);
//      system("echo \"set pm3d map; set key off; set palette rgbformulae 21,22,23 negative;set terminal png size 2300,1500;set output 'content/$error_type.png';splot '../tmp/$error_type.txt';\" | gnuplot");
      system("echo \"
set pm3d map;
set key off; 
set cbrange [-8:0]; 
set lmargin at screen 0.05;
set rmargin at screen 0.90;
set tmargin at screen 0.99;
set bmargin at screen 0.05;
set palette defined (-10.1 'black', -10 'white', -8 '#000088', -7 'blue', -6 'green', -4 'yellow', -2 'red', 0 '#ff66ff', 0.1 'black'); 
set terminal png size 2300,1500;
set output 'content/$error_type.png';
splot '../tmp/$error_type.txt';\" | gnuplot");
//     fwrite($html, "<h3>$error_type - $error_types[$error_type]</h3><img src='../tmp/$error_type.png'><br>\n");
      }
    if($p==0)
      exit();
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
