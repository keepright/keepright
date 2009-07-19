<?php

/*
-------------------------------------
-- layer_conflicts
-------------------------------------

find junctions of highways that are not on the same layer


this is obviously wrong:
[two ways connected together while on different layers]

way A, layer 0	|
		|
		|           way B, layer 1
	--------*------------------------------------
		|
		|


this is also not OK (but nevertheless common practice):
[a bridge ending at a junction. there should be a short way on layer 0 between the junction and the start of the bridge]

way A, layer 0	|
		|
		|           way B, layer 1
		*------------------------------------
		|
		|



this is the only exception:
[a highway that runs over a bridge]

  way A, layer 0              way B, layer 1
-----------------------*----------------------------

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
		SELECT t.k FROM way_tags t WHERE t.way_id=wn.way_id AND t.k='highway'
	)
	GROUP BY node_id
	HAVING COUNT(DISTINCT way_id)>1
", $db1);
query("CREATE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);




// tmp_ways will contain all highways with their nodes and layer tag
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways (
	way_id bigint NOT NULL,
	node_id bigint NOT NULL,
	end_node boolean NOT NULL DEFAULT FALSE,
	layer text,
	PRIMARY KEY (way_id, node_id)
	)
", $db1);

// find any way/node tupel using just junction nodes
query("
	INSERT INTO _tmp_ways (way_id, node_id)
	SELECT DISTINCT wn.way_id, wn.node_id
	FROM _tmp_junctions j INNER JOIN way_nodes wn USING (node_id)
", $db1);

query("CREATE INDEX idx_tmp_ways_layer ON _tmp_ways (layer)", $db1);
query("CREATE INDEX idx_tmp_ways_node_id ON _tmp_ways (node_id)", $db1);


// fetch layer tag
query("
	UPDATE _tmp_ways c
	SET layer=t.v
	FROM way_tags t
	WHERE t.way_id=c.way_id AND t.k='layer'
", $db1);

// mark end nodes
query("
	UPDATE _tmp_ways c
	SET end_node=(w.first_node_id = node_id) OR (w.last_node_id = node_id)
	FROM ways w
	WHERE w.id=c.way_id
", $db1);


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




// error candidates are nodes that are members of ways
// on different layers (at least 2)
query("DROP TABLE IF EXISTS _tmp_error_candidates", $db1);
query("
	CREATE TABLE _tmp_error_candidates AS
	SELECT way_id, node_id, end_node, layer, false AS all_intermediate_nodes
	FROM _tmp_ways
	WHERE node_id IN (
		SELECT node_id
		FROM _tmp_ways
		GROUP BY node_id
		HAVING COUNT(DISTINCT layer)>=2
	)
", $db1);
query("CREATE INDEX idx_tmp_error_candidates_node_id ON _tmp_error_candidates (node_id)", $db1);


// error candidates are errors with the exception that
// if an error candidate node is part of exactly two ways
// and in both ways it is an end node it is ok.
// [in this case it is the exception described above,
// a node connecting the ends of two ways]
query("
	DELETE FROM _tmp_error_candidates AS T
	WHERE node_id IN (
		SELECT node_id
		FROM _tmp_error_candidates AS TT
		WHERE TRUE=ALL(
			SELECT end_node
			FROM _tmp_error_candidates AS TTT
			WHERE TTT.node_id=TT.node_id
		)
		GROUP BY node_id
		HAVING COUNT(*)=2
	)
", $db1);

// junctions on all intermediate nodes are major errors,
// junctions on end nodes are to be separated
// (because there are so much of them)
// so mark all nodes that are end nodes in all connected ways
query("
	UPDATE _tmp_error_candidates
	SET all_intermediate_nodes=TRUE
	WHERE node_id IN (
		SELECT node_id
		FROM _tmp_error_candidates AS TT
		WHERE FALSE=ALL(
			SELECT end_node
			FROM _tmp_error_candidates AS TTT
			WHERE TTT.node_id=TT.node_id
		)
		GROUP BY node_id
	)
", $db1);




query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type + CASE WHEN all_intermediate_nodes THEN 1 ELSE 2 END,
	CAST('node' AS type_object_type), node_id, 'This node is a junction of ways on different layers: ' ||
	group_concat('#' || way_id || '(' || layer || ')'), NOW()
	FROM _tmp_error_candidates AS T
	GROUP BY node_id, all_intermediate_nodes
", $db1);




query("DROP TABLE IF EXISTS _tmp_error_candidates", $db1);
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("DROP TABLE IF EXISTS _tmp_ways", $db1);

?>
