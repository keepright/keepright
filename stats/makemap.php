#!/usr/bin/php

#Generates a map of node density of the whole world
<?php

echo "Reading input files\n";

foreach(glob("../results/nodes*.txt") as $file) {
  echo "Reading $file\n";
  $fin = fopen($file, "r");
  while(!feof($fin)) {
    $l = fscanf($fin,"%u %f %f %u");
    $store[$l[2]*10][$l[1]*10] = $l[3];
    }

  fclose($fin);
  }
echo "Writing full data file\n";
$fout = fopen("./nodesmap.dat", "w");

for($x=-1800;$x<1800;$x++) {
  for($y=-800;$y<800;$y++) {
    if(isset($store[$x][$y]))
      $val = $store[$x][$y];
    else
      $val = -1;
    fprintf($fout,"%d %d %d\n",$x,$y,$val);
    }
  fprintf($fout,"\n");
  }

fclose($fout);
echo "Running Gnuplot\n";
system("gnuplot makemap.gp");

?>