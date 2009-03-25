<?php

if ($argc<>2) {
	echo "Usage: \">php prepare_tablestructure.php AT\"\n";
	echo "will perform modifications to the tables as they are created by Osmosis'\n";
	echo "simple table schema creation script. \n";
	echo "database credentials are configured in config file\n";
	exit;
}

require('config.inc.php');
require('helpers.inc.php');
require('BufferedInserter.php');


echo "Adapting table structure for $db_postfix \n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db2 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);

$starttime=microtime(true);

//--------------------------------------------------

add_column('nodes', 'lat', 'double precision', $db1);
add_column('nodes', 'lon', 'double precision', $db1);
add_column('nodes', 'x', 'double precision', $db1);
add_column('nodes', 'y', 'double precision', $db1);

add_column('way_nodes', 'lat', 'double precision', $db1);
add_column('way_nodes', 'lon', 'double precision', $db1);
add_column('way_nodes', 'x', 'double precision', $db1);
add_column('way_nodes', 'y', 'double precision', $db1);

add_column('ways', 'first_node_id', 'bigint', $db1);
add_column('ways', 'last_node_id', 'bigint', $db1); 

add_column('ways', 'first_node_lat', 'double precision', $db1);
add_column('ways', 'first_node_lon', 'double precision', $db1);
add_column('ways', 'first_node_x', 'double precision', $db1);
add_column('ways', 'first_node_y', 'double precision', $db1);

add_column('ways', 'last_node_lat', 'double precision', $db1);
add_column('ways', 'last_node_lon', 'double precision', $db1);
add_column('ways', 'last_node_x', 'double precision', $db1);
add_column('ways', 'last_node_y', 'double precision', $db1);

add_column('ways', 'node_count', 'integer', $db1);

query("ALTER TABLE way_nodes ALTER COLUMN sequence_id TYPE integer", $db1);

query("ALTER TABLE ways ALTER COLUMN user_name DROP NOT NULL", $db1);
query("ALTER TABLE nodes ALTER COLUMN user_name DROP NOT NULL", $db1);
query("ALTER TABLE relations ALTER COLUMN user_name DROP NOT NULL", $db1);
query("ALTER TABLE way_tags ALTER COLUMN v DROP NOT NULL", $db1);
query("ALTER TABLE node_tags ALTER COLUMN v DROP NOT NULL", $db1);

//--------------------------------------------------

pg_close($db1);
pg_close($db2);


?>
