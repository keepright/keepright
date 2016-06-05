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

  $types = array();
  print "<html><body><table border='1' style='font-size:10pt;border-collapse:collapse;padding:2px;text-align:center;'><tr><th>Schema<th>Errors<th>LastRun<th>RunBefore\n";
  $result = query("SELECT error_type, SUM(count) as count FROM error_statistics WHERE TRUE GROUP BY error_type;",$db1,false);
  while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) { 
    if($row['count']) {
      array_push($types,$row['error_type']); 
      }
    }
  sort($types);
  foreach ($types as $t) {print "<th>$t<br>";}

  foreach ($schemas as $k => $v) {
    $result = query("SELECT schema, date, SUM(count) as errcount FROM error_statistics WHERE schema='$k' GROUP BY date, schema ORDER BY date DESC LIMIT 2;",$db1,false);
    $now=pg_fetch_assoc($result);
    $was=pg_fetch_assoc($result);
    pg_free_result($result);

    $now['type'] = array(); $was['type'] = array();

    $result = query("SELECT count, error_type FROM error_statistics WHERE schema='$k' AND date = '".$now['date']."';",$db1,false);
    while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) { $now['type'][$row["error_type"]] = $row['count']; }
    pg_free_result($result);

    $result = query("SELECT count, error_type FROM error_statistics WHERE schema='$k' AND date = '".$was['date']."';",$db1,false);
    while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) { $was['type'][$row["error_type"]] = $row['count']; }
    pg_free_result($result);
    
    print "<tr><td>$k";
    print "<td>".$now["errcount"];
    printf("<br>%.2f%%",(($now["errcount"] - $was["errcount"])/$was["errcount"]*100));
    print "<td>".date("Y/m/d H:i",$now["date"]);
    print "<td>".date("Y/m/d H:i",$was["date"])."<br>(".$was['errcount'].")\n";
    foreach ($types as $t) {
      if (!array_key_exists($t,$now['type'])) { $now['type'][$t] = 0; }
      if (!array_key_exists($t,$was['type'])) { $was['type'][$t] = 0; }
      print "<td>".$now['type'][$t].'<br>';
      if ($was['type'][$t])
        printf("%.2f%%",(($now['type'][$t] - $was['type'][$t])/$was['type'][$t]*100));
      }
    }
  print "</table></body></html>";
  }



pg_close($db1);


?>
