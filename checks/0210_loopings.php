<?php


/*

there should be two types of ways:
1) straight ways like A,B,C,D
2) closed loop ways like A,B,C,D,A

anything else like A,B,C,D,A,B looks like an error.

So any way may contain up to one node twice at most. 
If any node is found more than twice in the same way or 
if more than one node is found twice it looks like an error. 
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
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked, lat, lon) 
	SELECT $error_type, 'way', c.way_id, 'This way contains node #' || c.node_id || ' ' || c.node_count || ' times. This may or may not be an error.'
		, NOW(), 1e7*n.lat, 1e7*n.lon
	FROM _tmp_node_count c INNER JOIN nodes n ON (c.node_id=n.id)
	WHERE c.node_count>2
", $db1);


// second part:
// any way may contain just one node twice. If more than one node is found twice it is considered an error
query("
        INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
	SELECT 1+$error_type, 'way', c.way_id, 'This way contains more than one node at least twice. Nodes are ' || array_to_string(array(

		SELECT '#' || t.node_id
		FROM _tmp_node_count t
		WHERE t.way_id=c.way_id	
	
	), ', ') || '. This may or may not be an error.', NOW()
	FROM _tmp_node_count c
	GROUP BY c.way_id
	HAVING COUNT(c.node_id)>1
", $db1);

query("DROP TABLE IF EXISTS _tmp_node_count", $db1, false);
?>