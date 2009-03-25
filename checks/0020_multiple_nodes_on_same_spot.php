<?php


/*-------------------------------------
-- find multiple nodes on the same spot
-------------------------------------

create a copy of the node table with 'rastered' coordinates: 
latitude and longitude values are rounded to multiples of the 
mimimum distance one wants to search for. Nodes close to each
other (but not on the exact same coordinates) fall on the same
rastered coordinates and are identified as 'the same spot'

using a unique index on (x,y) to let the database look for
nodes that already exist with the same coordinates (detect insert errors)
looked like a good idea but is very slow on inserts.

*/



$raster=2;

query("DROP TABLE IF EXISTS _tmp_nodes_rastered", $db1, false);

query("
	CREATE TABLE _tmp_nodes_rastered (
	id bigint NOT NULL,
	x int NOT NULL,
	y int NOT NULL
	)
", $db1, false);



$bi=new BufferedInserter('_tmp_nodes_rastered', $db2, 1000);


$result=query("
	SELECT id, x, y
	FROM nodes
", $db1);

while ($row=pg_fetch_array($result)) {
	
	//echo $row['id']. ' ' . $row['x']) . ' ' . $row['y']) . "\n";
	$bi->insert($row['id'] ."\t". $raster*round($row['x'])/$raster) ."\t". $raster*round($row['y'])/$raster) ."\n");
}

pg_free_result($result);
$bi->flush_buffer();


query("CREATE INDEX idx_tmp_nodes_rastered_xy ON _tmp_nodes_rastered (x, y)", $db1);

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked) 
	SELECT $error_type, 'node', MIN(id), 'There is more than one node in this spot (raster is $raster Meter). Offending node IDs: ' || array_to_string(array(
		
		SELECT tmp.id FROM _tmp_nodes_rastered tmp WHERE tmp.x=n.x AND tmp.y=n.y

	), ','), NOW()
	FROM _tmp_nodes_rastered n
	GROUP BY x,y
	HAVING COUNT(id)>1
", $db1);

query("DROP TABLE IF EXISTS _tmp_nodes_rastered", $db1, false);


?>