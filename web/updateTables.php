<?php
/*

this script will insert error_view entries into the error_view_shadow
on the webserver

please create a dump using updateTables.sh or phpmyadmin:
data format CSV
columns separated by ,
columns enclosed in "
escaped by \
lines separated by AUTO
NULL-values represented by NULL

compress it using bzip2 or leave it plain text


update procedure for seamless switching from old table to new table:

* create a copy of error_view called error_view_shadow and truncate it
* this script will import into error_view_shadow
* use phpmyadmin to rename error_view to error_view_old
* use phpmyadmin to rename error_view_shadow to error_view
* the last two steps have to be performed in two separate browser
  windows immediately to keep the site running without interruption

*/



// expecting input file name as GET parameter f or simply on the command line
// if called on the command line the database name has to be given
if (isset($_GET['f'])) {
	$file=$_GET['f'];
} else {
	$file=$argv[1];
	$db=$argv[2];
}



require('webconfig.inc.php');
require('helpers.inc.php');
require('BufferedInserter_MySQL.php');

//echo "db_name is $db_name <br>";

$db1=mysqli_connect($db_host, $db_user, $db_pass, $db_name);

load_dump($db1, $f, $argv[3]);

mysqli_close($db1);

?>
