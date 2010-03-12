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
query("ANALYZE _tmp_ways", $db1);


// now find all nodes belonging to motorways
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT way_id, node_id
	FROM way_nodes wn INNER JOIN _tmp_ways w USING (way_id)
", $db1);
query("CREATE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);
query("ANALYZE _tmp_junctions", $db1);


// avoid error markers on endings of motorways where motorways
// are connected to eg. primary roads intentionally.
// drop nodes from the list that are part of just one motorway
// and that are the first or last node of a way
query("
	DELETE FROM _tmp_junctions
	WHERE node_id IN (
		SELECT j.node_id
		FROM _tmp_junctions j
		GROUP BY j.node_id
		HAVING COUNT(j.way_id)=1

	) AND EXISTS (
		SELECT w.id
		FROM _tmp_ways tw INNER JOIN ways w ON (tw.way_id=w.id)
		WHERE node_id=w.first_node_id OR node_id=w.last_node_id
	)
", $db1);


query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type, CAST('node' AS type_object_type), node_id, 'This node is a junction of a motorway and a highway other than motorway, motorway_link, trunk, service, unclassified or construction', NOW()
	FROM way_nodes wn INNER JOIN _tmp_junctions j USING (node_id)
	WHERE wn.way_id<>j.way_id AND EXISTS (
		SELECT t.k FROM way_tags t WHERE t.way_id=wn.way_id AND
		t.k='highway' AND t.v NOT IN ('motorway', 'motorway_link', 'trunk', 'service', 'unclassified', 'construction')
	)
", $db1);


query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
?>
