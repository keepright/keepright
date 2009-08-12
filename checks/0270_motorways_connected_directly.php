<?php

/*
-------------------------------------
-- leaking motorways
-------------------------------------

motorways can only be connected to highway=motorway_link
which in turn can be connecte to any highway=primary or
whatever road.
Any connection of a motorway to any way other than motorway_link is wrong

*/
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways AS
	SELECT way_id
	FROM way_tags t
	WHERE t.k='highway' and t.v='motorway'
", $db1);
query("CREATE INDEX idx_tmp_ways_way_id ON _tmp_ways (way_id)", $db1);


// leave out intermediate-nodes that don't interest anybody:
// just these nodes are important, that are used at least twice
// in way_nodes (aka junctions)
// select nodes of ways used at least twice
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT way_id, node_id
	FROM way_nodes wn INNER JOIN _tmp_ways w USING (way_id)
", $db1);
query("CREATE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);




query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type, CAST('node' AS type_object_type), node_id, 'This node is a junction of a motorway and a highway other than motorway, motorway_link, trunk, service or unclassified', NOW()
	FROM way_nodes wn INNER JOIN _tmp_junctions j USING (node_id)
	WHERE wn.way_id<>j.way_id AND EXISTS (
		SELECT t.k FROM way_tags t WHERE t.way_id=wn.way_id AND
		t.k='highway' AND t.v NOT IN ('motorway', 'motorway_link', 'trunk', 'service', 'unclassified')
	)
", $db1);


query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
?>
