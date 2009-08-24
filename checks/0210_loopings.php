<?php


/*

there should be two types of ways:
1) straight ways like A,B,C,D
2) closed loop ways like A,B,C,D,A

anything else like A,B,C,D,A,B looks like an error.

So any way may contain up to one node twice at most.
If any node is found more than twice in the same way or
if more than one node is found twice it looks like an error.

Any way with only 2 different nodes in it,
having one node more than once, is also an error.

*/



query("DROP TABLE IF EXISTS _tmp_node_count", $db1, false);
query("
	CREATE TABLE _tmp_node_count(
                way_id bigint NOT NULL,
		node_id bigint NOT NULL,
		node_count bigint
	)
", $db1);

// select all ways that contain nodes at least twice
query("
	INSERT INTO _tmp_node_count
	SELECT way_id, node_id, COUNT(sequence_id) as node_count
	FROM way_nodes
	GROUP BY way_id, node_id
	HAVING COUNT(sequence_id)>1
", $db1);



// first part:
// any way that contains any single node more than twice is considered an error

query("DROP TABLE IF EXISTS _tmp_tmp_errors", $db1, false);
query("
	SELECT c.way_id, c.node_id, c.node_count, 1e7*n.lat as lat, 1e7*n.lon as lon
	INTO _tmp_tmp_errors
	FROM _tmp_node_count c INNER JOIN nodes n ON (c.node_id=n.id)
	WHERE c.node_count>2
", $db1);

// there have been cases when a way contained two nodes more than twice,
// that where located in exactly the same spot.
// to ensure uniqueness of way_id/lat/lon we have to decide which node to report
// let's take the node with biggest node_count. Remember: there could
// be two node ids with the same number of occurrences, so just pick the larger id one...
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked, lat, lon)
	SELECT $error_type, CAST('way' AS type_object_type), c.way_id, 'This way contains node #' || c.node_id || ' ' || c.node_count || ' times. This may or may not be an error.', NOW(), c.lat, c.lon
	FROM _tmp_tmp_errors c
	WHERE node_id=(
		SELECT MAX(node_id)
		FROM _tmp_tmp_errors tmp
		WHERE tmp.way_id=c.way_id and tmp.lat=c.lat and tmp.lon=c.lon
		AND node_count=(
			SELECT MAX(node_count)
			FROM _tmp_tmp_errors tmp2
			WHERE  tmp2.way_id=c.way_id and tmp2.lat=c.lat and tmp2.lon=c.lon
		)
	)
", $db1);
query("DROP TABLE IF EXISTS _tmp_tmp_errors", $db1, false);



// second part:
// any way may contain just one node twice. If more than one node is found twice it is considered an error
query("
        INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked)
	SELECT 1+$error_type, CAST('way' AS type_object_type), c.way_id, 'This way contains more than one node at least twice. Nodes are ' || array_to_string(array(

		SELECT '#' || t.node_id
		FROM _tmp_node_count t
		WHERE t.way_id=c.way_id

	), ', ') || '. This may or may not be an error.', NOW()
	FROM _tmp_node_count c
	GROUP BY c.way_id
	HAVING COUNT(c.node_id)>1
", $db1);


// third part:
// Any way with only 2 different nodes in it, having one node more than once, is an error.
query("
        INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked)
	SELECT DISTINCT 2+$error_type, CAST('way' AS type_object_type), nc.way_id, 'This way has only two different nodes and contains one of them more than once.', NOW()
	FROM _tmp_node_count nc

	WHERE EXISTS(
		SELECT wn.way_id
		FROM way_nodes wn
		WHERE wn.way_id=nc.way_id
		GROUP BY wn.way_id
		HAVING COUNT(DISTINCT wn.node_id)<=2
	)
", $db1);



query("DROP TABLE IF EXISTS _tmp_node_count", $db1, false);
?>