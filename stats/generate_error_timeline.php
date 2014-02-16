#!/usr/bin/php
<?php

require_once('../checks/helpers.php');
require_once('../config/config.php');
require_once("../config/schemas.php");


$opt = getopt("fhg");

if (!array_key_exists('f',$opt) && !array_key_exists('g',$opt)) {
  print "-f fill table with current statistics\n";
  print "-g generate output plots\n\n";
  exit;
  }

$db1 = pg_pconnect(connectstring());

if (array_key_exists('f',$opt)) {
  query("CREATE TABLE IF NOT EXISTS error_statistics (
      schema character varying(8) NOT NULL,
      error_type integer NOT NULL,
      count integer,
      date bigint NOT NULL)
  ",$db1);


  foreach ($schemas as $k => $v) {
    print "Checking Schema $k\n";

    $result= query("INSERT INTO error_statistics (schema,error_type,count,date) SELECT schema, error_type, COUNT(1),  extract(epoch from now()) FROM error_view e WHERE schema = '$k' GROUP BY e.schema, e.error_type ORDER BY e.error_type",$db1);

    pg_free_result($result);

    }
  }

pg_close($db1);


?>