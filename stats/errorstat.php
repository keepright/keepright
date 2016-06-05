#!/usr/bin/php

<?php


$opt = getopt("t::");

error_reporting(E_ALL ^ E_NOTICE);

// read number of nodes per square degree
echo "Reading node files\n";
foreach(glob("../results/nodes*.txt") as $file) {
  echo "Reading $file\n";
  $fin = fopen($file, "r");
  while(!feof($fin)) {
    $l = fscanf($fin,"%u %f %f %u");
    $nodes[($l[1])*10+900][($l[2])*10+1800] += $l[3];
    }

  fclose($fin);
  }


// read number of errors per error_type and square degree
foreach(glob("../results/error_view_*.txt") as $file) {
  print("Reading $file\n");

  $f=fopen($file, 'r');
  if ($f) {
    while (!feof($f)) {
      $buffer = explode("\t", fgets($f));
      $stats[$buffer[2]][round(($buffer[11]+90e7)/5e5)][round(($buffer[12]+180e7)/5e5)]++;
      $error_types[$buffer[2]]=$buffer[3];
      }
    fclose($f);
    }
  }

if(!array_key_exists('t',$opt)){$opt['t']=1;}

$pid_arr = array();

foreach ($stats as $error_type=>$s) {
  while(count($pid_arr) >= $opt['t']) {
    $s=-1;
    pcntl_waitpid(0,$s);
    array_pop($pid_arr);  //just pop an entry - doesn't matter which one
    }
  $p = pcntl_fork();
  if(!$p) {
    print "Doing ".$error_type."\n";
    $f=fopen('../tmp/' . $error_type . '.txt', 'w');
    if ($f) {
      for($lat=0;$lat<=900*2*2;$lat++) {
        for($lon=0;$lon<=1800*2*2;$lon++) {
          if ($nodes[round($lat/2)][round($lon/2)]>0) {
            $value=$s[$lat][$lon]/$nodes[round($lat/2)][round($lon/2)]/2;
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
      system("echo \"
set pm3d map;
set key off; 
set cbrange [-8:0]; 
set lmargin at screen 0.05;
set rmargin at screen 0.90;
set tmargin at screen 0.99;
set bmargin at screen 0.05;
set palette defined (-10.1 'black', -10 'white', -8 '#000088', -7 'blue', -6 'green', -4 'yellow', -2 'red', 0 '#ff66ff', 0.1 'black'); 
set terminal png size 4600,3000;
set output 'content/$error_type.png';
splot '../tmp/$error_type.txt';\" | gnuplot");
      }
    exit(0);
    }
  else {
    print "Push ".$error_type."\n";
    array_push($pid_arr,$p);
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
