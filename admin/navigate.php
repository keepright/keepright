<h1>Keep Right!<sup>VM</sup></h1>

<a target="_blank" href="/phppgadmin">PHPPgAdmin</a> |
<a target="_blank" href="/phpmyadmin">PHPMyAdmin</a> |
<a href="configure.php">config</a> |
<a href="update.php">update</a> |
<a href="logs.php">logs</a> |
<a href="results">results</a> |
<a href="webUpdateClient.php">ftp upload</a>
<br>

<?php

// dirty hack: config.inc.php depends on a country code given on the command line to finish
// this is not needed here, so we override it
if (!isset($argv[1])) $argv[1]='AT';
include("config.inc.php");

// determine which databases exist in the MySQL server and provide links to the map
$db1=mysqli_connect($WEB_DB_HOST, $WEB_DB_USER, $WEB_DB_PASS, $WEB_DB_NAME);
$result=mysqli_query($db1, "
  SELECT TABLE_NAME
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='$WEB_DB_NAME' AND TABLE_NAME LIKE 'error\_view\_osm\___'
");

while ($row = mysqli_fetch_assoc($result)) {

  $iso_code=substr($row['TABLE_NAME'], -2, 2);
  echo '<a target="_blank" href="/keepright/report_map.php?db=osm_' . $iso_code . '">map_' . $iso_code . '</a> ';

}

mysqli_free_result($result);
mysqli_close($db1);

?>