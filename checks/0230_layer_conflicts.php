<?php

/*
-------------------------------------
-- layer_conflicts
-------------------------------------

find junctions of highways that are not on the same layer
*/



// leave out intermediate-nodes that don't interest anybody:
// just these nodes are important, that are used at least twice
// in way_nodes (aka junctions)
// select nodes of ways used at least twice
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT node_id
	FROM way_nodes wn
	WHERE EXISTS (
		SELECT * FROM way_tags t WHERE t.way_id=wn.way_id AND t.k='highway'
	)
	GROUP BY node_id
	HAVING COUNT(DISTINCT way_id)>1
", $db1);
query("CREATE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);




// tmp_ways will contain all highways with their linestring geometry and layer tag
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways (
	way_id bigint NOT NULL,
	node_id bigint NOT NULL,
	layer text,
	PRIMARY KEY (way_id, node_id)
	)
", $db1);

// find any highway-tagged way
query("
	INSERT INTO _tmp_ways (way_id, node_id)
	SELECT DISTINCT wn.way_id, wn.node_id
	FROM _tmp_junctions j INNER JOIN way_nodes wn USING (node_id)
", $db1);

// fetch layer tag
query("
	UPDATE _tmp_ways c
	SET layer=t.v
	FROM way_tags t
	WHERE t.way_id=c.way_id AND t.k='layer'
", $db1);

/*
// set default layers:
// bridges have layer +1 (if no layer tag is given)
// tunnels have layer -1 (if no layer tag is given)
// anything else has layer 0 (if no layer tag is given)
query("
	UPDATE _tmp_ways c
	SET layer=1
	FROM way_tags t
	WHERE layer IS NULL AND
	t.way_id=c.way_id AND
	t.k='bridge'
", $db1);
query("
	UPDATE _tmp_ways c
	SET layer=-1
	FROM way_tags t
	WHERE layer IS NULL AND
	t.way_id=c.way_id AND
	t.k='tunnel'
", $db1);
query("
	UPDATE _tmp_ways c
	SET layer=0
	WHERE layer IS NULL
", $db1);
*/

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type, CAST('node' AS type_object_type), w1.node_id,
	'This node is a junction of ways on different layers: ' ||
	group_concat('#' || w1.way_id || '(' || w1.layer || ') - #' || w2.way_id || '(' || w2.layer || ')'), NOW()
	FROM _tmp_ways w1 INNER JOIN _tmp_ways w2 USING (node_id)
	WHERE w1.way_id<w2.way_id AND w1.layer<>w2.layer
	GROUP BY w1.node_id
", $db1);


query("CREATE INDEX idx_tmp_ways_layer ON _tmp_ways (layer)", $db1);

?>
