<?php

/*
-------------------------------------
-- dead-ended one-ways
-------------------------------------

a way tagged as oneway must always be connected to other ways on both end-nodes

*/

// function part excluded into helpers because of reusability for other checks
find_oneways($db1);

// find end nodes that are not connected to any other way
// exclude ring-ways (firstnode==lastnode)

// exclude furthermore ways that build a loop and end in itself like this:
// these ways aren't connected to other ways but they are still valid!
//
//              ---<-------<-----+
//              |                |
//              v                ^
//              |                |
//              |                |
//              +-->------->---- *
//                               |
//                               ^
//                               |
//  -----------------------------*--------------
//
// these ways have their first (last) node (at least) twice in way_nodes
// but with different sequence ids

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked, lat, lon)
	SELECT $error_type, 'way', o.way_id, 'The first node (id $1) of this one-way is not connected to any other way', o.first_node_id, NOW(), 1e7*o.first_node_lat, 1e7*o.first_node_lon
	FROM _tmp_one_ways o
	WHERE o.first_node_id<>o.last_node_id AND
	NOT EXISTS(
		SELECT way_id
		FROM way_nodes wn1
		WHERE o.first_node_id=wn1.node_id AND wn1.way_id<>o.way_id
	) AND
	NOT EXISTS(
		SELECT way_id
		FROM way_nodes wn2
		WHERE o.first_node_id=wn2.node_id AND wn2.way_id=o.way_id
		GROUP BY way_id, node_id
		HAVING COUNT(DISTINCT sequence_id)>1
	)
", $db1);

// do the same for the last node of one-ways
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked, lat, lon)
	SELECT $error_type+1, 'way', o.way_id, 'The last node (id $1) of this one-way is not connected to any other way', o.last_node_id, NOW(), 1e7*o.last_node_lat, 1e7*o.last_node_lon
	FROM _tmp_one_ways o
	WHERE o.first_node_id<>o.last_node_id AND
	NOT EXISTS(
		SELECT way_id
		FROM way_nodes wn2
		WHERE o.last_node_id=wn2.node_id AND wn2.way_id<>o.way_id
	) AND
	NOT EXISTS(
		SELECT way_id
		FROM way_nodes wn2
		WHERE o.last_node_id=wn2.node_id AND wn2.way_id=o.way_id
		GROUP BY way_id, node_id
		HAVING COUNT(DISTINCT sequence_id)>1
	)

", $db1);


// another point is nodes that are connected to other one-way streets but
// direction of ways was chosen wrongly so that one-way streets clash:
//      ------->*<------           <-------*-------->
// this node is a black hole     this node cannot be reached

// table for one-way streets connected together
query("DROP TABLE IF EXISTS _tmp_one_way_junctions", $db1, false);
query("
	CREATE TABLE _tmp_one_way_junctions (
	node_id bigint NOT NULL,
	PRIMARY KEY (node_id)
	)
", $db1);


// the following has to be done twice: for 'first' and for 'last' nodes
$cfg = array(
	'first'=>'This node cannot be reached, because one-ways only lead away from here',
	'last'=>'You cannot escape from this node, because one-ways only lead to here'
);

$additivum=2;
foreach ($cfg as $item=>$msg) {

	// find one-way streets connected together by their first (last) node
	query("TRUNCATE TABLE _tmp_one_way_junctions", $db1, false);
	query("
		INSERT INTO _tmp_one_way_junctions (node_id)
		SELECT ${item}_node_id
		FROM _tmp_one_ways o
		WHERE o.first_node_id<>o.last_node_id
		GROUP BY ${item}_node_id
		HAVING COUNT(way_id)>1
	", $db1);

	// it is an error, if...
	// some one-ways are connected by their first (last) nodes,
	// and there is no other way connected to this junction node
	// except these one-ways
	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
		SELECT $error_type+$additivum, 'node', j.node_id, '$msg', NOW()
		FROM _tmp_one_way_junctions j
		WHERE NOT EXISTS (
			SELECT way_id
			FROM way_nodes wn
			WHERE j.node_id=wn.node_id AND wn.way_id NOT IN (
				SELECT way_id
				FROM _tmp_one_ways o
				WHERE o.${item}_node_id=j.node_id
			)
		)
	", $db1);
	$additivum++;
}

print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_one_ways", $db1);
query("DROP TABLE IF EXISTS _tmp_one_way_junctions", $db1);
?>