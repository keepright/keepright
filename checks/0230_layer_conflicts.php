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

// select all highways but ignore steps as they are meant for changing layers
query("DROP TABLE IF EXISTS _tmp_highways", $db1);
query("
	CREATE TABLE _tmp_highways AS
	SELECT DISTINCT way_id
	FROM way_tags
	WHERE k='highway' AND v<>'steps'
", $db1);
query("CREATE UNIQUE INDEX idx_tmp_highways_way_id ON _tmp_highways (way_id)", $db1);
query("ANALYZE _tmp_highways", $db1);


// leave out intermediate-nodes that don't interest anybody:
// just these nodes are important, that are used at least twice
// in way_nodes (aka junctions)
// select nodes of ways used at least twice
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT node_id
	FROM way_nodes wn INNER JOIN _tmp_highways USING (way_id)
	GROUP BY node_id
	HAVING COUNT(DISTINCT way_id)>1
", $db1);
query("CREATE UNIQUE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);
query("ANALYZE _tmp_junctions", $db1);


// tmp_ways will contain all highways with their nodes and layer tag
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways (
	way_id bigint NOT NULL,
	node_id bigint NOT NULL,
	end_node boolean NOT NULL DEFAULT FALSE,
	layer text DEFAULT '0',
	PRIMARY KEY (way_id, node_id)
	)
", $db1);

// find any way/node tupel using just junction nodes and just the highways
query("
	INSERT INTO _tmp_ways (way_id, node_id)
	SELECT DISTINCT wn.way_id, wn.node_id
	FROM (_tmp_junctions j INNER JOIN way_nodes wn USING (node_id)) INNER JOIN _tmp_highways USING (way_id)
", $db1);

query("CREATE INDEX idx_tmp_ways_layer ON _tmp_ways (layer)", $db1);
query("CREATE INDEX idx_tmp_ways_node_id ON _tmp_ways (node_id)", $db1);
query("ANALYZE _tmp_ways", $db1);


// fetch layer tag
find_layer_values('_tmp_ways', 'way_id', 'layer', $db1);

// mark end nodes
query("
	UPDATE _tmp_ways c
	SET end_node=true
	FROM ways w
	WHERE w.id=c.way_id AND
	((w.first_node_id = node_id) OR (w.last_node_id = node_id))
", $db1);


query("ANALYZE _tmp_ways", $db1);



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
		HAVING COUNT(DISTINCT _tmp_ways.layer)>=2
	)
", $db1);
query("CREATE INDEX idx_tmp_error_candidates_node_id ON _tmp_error_candidates (node_id)", $db1);
query("ANALYZE _tmp_error_candidates", $db1);


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

query("CREATE INDEX idx_tmp_error_candidates_all_intermediate_nodes ON _tmp_error_candidates (all_intermediate_nodes)", $db1);
query("ANALYZE _tmp_error_candidates", $db1);

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type + 1,
	CAST('node' AS type_object_type), node_id, 'This node is a junction of ways on different layers: $1', group_concat('#' || way_id || '(' || layer || ')'), NOW()
	FROM _tmp_error_candidates AS T
	WHERE all_intermediate_nodes
	GROUP BY node_id
", $db1);



print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_error_candidates", $db1);
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("DROP TABLE IF EXISTS _tmp_highways", $db1);





//////////////////////////////////////////////////////////
// this is something completely different:
// check for bridges with negative layer tags and
// tunnels with positive layer tags.

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
	SELECT $error_type + 2,
	CAST('way' AS type_object_type), wt1.way_id, 'This $1 is tagged with layer $2. This need not be an error, but it looks strange', wt1.k, wt2.v, NOW()
	FROM way_tags wt1 INNER JOIN way_tags wt2 ON wt1.way_id=wt2.way_id
	WHERE (wt1.k='bridge' AND wt1.v NOT IN ('no', 'false', '0') AND wt2.k='layer' AND wt2.v in ('-1', '-2', '-3', '-4', '-5'))
	OR (wt1.k='tunnel' AND wt1.v NOT IN ('no', 'false', '0') AND wt2.k='layer' AND wt2.v in ('1', '2', '3', '4', '5'))

", $db1);

?>
