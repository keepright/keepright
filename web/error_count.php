<?php
/*

Martijn's Interface

query number of errors per error_type

*/

require('webconfig.inc.php');
require('helpers.inc.php');


$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);
mysqli_query($db1, "SET SESSION wait_timeout=60");


// parameter one: error type
$ch = $_GET['ch'];
if (!$ch) $ch='';

$list=explode(',', $ch);
$error_types='0';
foreach($list as $type) $error_types.="," . (1*$type);



// build SQL for fetching error counts
$sql="SELECT COALESCE(SUM(error_count), 0) AS c";
$sql.=" FROM error_counts e";
$sql.=" WHERE error_type IN ($error_types)";


$result=mysqli_query($db1, $sql);

while ($row = mysqli_fetch_assoc($result)) {

	echo $row['c'];

}

mysqli_free_result($result);
mysqli_close($db1);

?>