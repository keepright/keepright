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
    print "Now included in export_errors\n";
    print "INSERT INTO error_statistics (schema,error_type,count,date) SELECT schema, error_type, COUNT(1),  extract(epoch from now()) FROM error_view e WHERE schema = '$k' GROUP BY e.schema, e.error_type ORDER BY e.error_type\n";
  
    }
  }
else { 
  print "<html><body><table border='1'><tr><th>Schema<th>Errors<th>Change<th>LastRun<th>RunBefore\n";
  foreach ($schemas as $k => $v) {
    $result = query("SELECT schema, date, SUM(count) as errcount FROM error_statistics WHERE schema='$k' GROUP BY date, schema ORDER BY date DESC LIMIT 2;",$db1,false);
    $now=pg_fetch_assoc($result);
    $was=pg_fetch_assoc($result);
    
    print "<tr><td>$k";
    print "<td>".$now["errcount"];
    printf("<td>%.2f%%",(($now["errcount"] - $was["errcount"])/$now["errcount"]*100));
    print "<td>".date("Y/m/d H:i",$now["date"]);
    print "<td>".date("Y/m/d H:i",$was["date"])." (".$was['errcount'].")\n";
    
    pg_free_result($result);
    }
  print "</table></body></html>";
  }



pg_close($db1);


?>