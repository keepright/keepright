#!/usr/bin/php
<?php
require("../config/schemas.php");

$f=fopen("../results/mysqlSchemataDB.sql", 'w');

foreach ($schemas as $k=>$v) {
	$l = $v['left'];
	$r = $v['right'];
	$t = $v['top'];
	$b = $v['bottom'];
	$lp = $l - 0.5;
	$rp = $r + 0.5;
	$tp = $t + 0.5;
	$bp = $b - 0.5;
	fwrite($f,"INSERT INTO schemata VALUES($l,$r,$t,$b,$lp,$rp,$tp,$bp,$k);\n");   	
  }

fclose($f);
