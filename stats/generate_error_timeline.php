#!/usr/bin/php
<?php

require_once('../checks/helpers.php');
require_once('../config/config.php');
require_once("../config/schemas.php");
require_once("../config/error_types.php");

$opt = getopt("fhg");

if (!array_key_exists('f',$opt) && !array_key_exists('g',$opt)) {
  print "-f fill table with current statistics\n";
  print "-g generate output plots\n\n";
  exit;
  }

  $hide = array(70,197,207,285,420);

  $types = array();  
  
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


  print "<html>
  <head>
  <link rel='stylesheet' type='text/css' href='../../lanes/style.css'>
  <link rel='stylesheet' type='text/css' href='stats.css'>
  </head><body>
  <table><thead><tr><th>Schema<th>Errors<th>LastRun<th>RunBefore";
  $result = query("SELECT error_type, SUM(count) as count FROM error_statistics WHERE TRUE GROUP BY error_type;",$db1,false);
  while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) { 
    if($row['count']) {
      array_push($types,$row['error_type']); 
      }
    }
  pg_free_result($result);  
  sort($types);
  $nowsum['type'] = array(); $wassum['type'] = array(); 
  foreach ($types as $t) {
    $bt = floor($t/10)*10;
    $wassum['type'][$t] = 0;
    $nowsum['type'][$t] = 0;
    if(in_array($t,$hide)) {continue;} 
    $title = $error_types[$bt]['description'];
    if($bt != $t) {
      $title = $error_types[$bt]['subtype'][$t]."\n".$title;
      }
    else {
      $title = $error_types[$t]['name']."\n".$title;
      }
    $title = htmlspecialchars($title,ENT_QUOTES);  
    print "<th title='$title'>$t";
    }
  
  print "</thead>\n<tbody>";

  
  foreach ($schemas as $k => $v) {
    if($k==0){continue;}
    $result = query("SELECT schema, date, SUM(count) as errcount FROM error_statistics WHERE schema='$k' GROUP BY date, schema ORDER BY date DESC LIMIT 2;",$db1,false);
    $now=pg_fetch_assoc($result);
    $was=pg_fetch_assoc($result);
    pg_free_result($result);

    $now['type'] = array(); $was['type'] = array();

    $result = query("SELECT count, error_type FROM error_statistics WHERE schema='$k' AND date = '".$now['date']."';",$db1,false);
    while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) { 
      $now['type'][$row["error_type"]] = $row['count']; 
      $nowsum['type'][$row["error_type"]] += $row['count'];
      }
    pg_free_result($result);

    $result = query("SELECT count, error_type FROM error_statistics WHERE schema='$k' AND date = '".$was['date']."';",$db1,false);
    while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) { 
      $was['type'][$row["error_type"]] = $row['count']; 
      $wassum['type'][$row["error_type"]] += $row['count'];
      }
    pg_free_result($result);
    
    printrow($k,$now,$was);
    }
  $wassum['errcount'] = array_sum($wassum['type']);  
  $nowsum['errcount'] = array_sum($nowsum['type']);  
  printrow("Sum",$nowsum,$wassum);    
  print "</tbody></table></body></html>";
  }




function printrow($k,$now,$was) {
  global $types, $hide;
  print "<tr><td>$k";
  print "<td title='".$was['errcount']."'>".$now["errcount"];
  printf("<br>%.2f%%",(($now["errcount"] - $was["errcount"])/$was["errcount"]*100));
  print "<td>".date("y/m/d H:i",$now["date"]);
  print "<td>".date("y/m/d H:i",$was["date"])."\n";
  foreach ($types as $t) {
    if(in_array($t,$hide)) {continue;}
    if (!array_key_exists($t,$now['type'])) { $now['type'][$t] = 0; }
    if (!array_key_exists($t,$was['type'])) { $was['type'][$t] = 0; }
    
    $change = 0;
    if ($was['type'][$t]) {
      $change    = ($now['type'][$t] - $was['type'][$t])/$was['type'][$t]*100;
      }
    $changeabs = $now['type'][$t] - $was['type'][$t];
    $color = definecolor($now['type'][$t],$change,$changeabs);
    
    print "<td title='$k-$t' style='color:$color;'>".$now['type'][$t].'<br>';
    if ($was['type'][$t])
      printf("%.2f%%",$change);
    }
  }

function definecolor($val,$change,$changeabs) {
  $col = 'black';
  if($val > 50) {
    if($change > 2  && $changeabs > 100 ) $col = '#d00';
    if($change < -2 && $changeabs < -100 ) $col = '#040';

    if ($change > 10) $col = "red";
    if ($change < -10) $col = "#090";
    
    }
  return $col;
  }
  

  
  
  
pg_close($db1);  
  
?>





