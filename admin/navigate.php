<h1>Keep Right!<sup>VM</sup></h1>

<a target="_blank" href="/phppgadmin">PHPPgAdmin</a> | 
<a target="_blank" href="/phpmyadmin">PHPMyAdmin</a> | 
<a href="configure.php">config</a> | 
<a href="update.php">update</a> | 
<a href="logs.php">logs</a> | 
<a href="results">results</a> | 
<!-- <a href="upload.php">ftp upload</a> | -->
<br>

<?php

// determine which databases exist in the MySQL server and provide links to the map
include("webconfig.inc.php");

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
$result=mysqli_query($db1, "
  SELECT TABLE_NAME
  FROM information_schema.TABLES
  WHERE TABLE_SCHEMA='$db_name' AND TABLE_NAME LIKE 'error\_view\_osm\___'
");

while ($row = mysqli_fetch_assoc($result)) {

  $iso_code=substr($row['TABLE_NAME'], -2, 2);
  echo '<a target="_blank" href="/keepright/report_map.php?db=osm_' . $iso_code . '">map_' . $iso_code . '</a> ';

}

mysqli_free_result($result);
mysqli_close($db1);

?>