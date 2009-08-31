<?php

/*
-------------------------------------
-- dead-ended one-ways
-------------------------------------

a way tagged as oneway must always be connected to other ways on both end-nodes

*/


query("DROP TABLE IF EXISTS _tmp_one_ways", $db1);
query("
	CREATE TABLE _tmp_one_ways (
	way_id bigint NOT NULL,
	first_node_id bigint,
	last_node_id bigint,
	last_node_lat double precision,
	last_node_lon double precision,
	PRIMARY KEY (way_id)
	)
", $db1);


// fetch all one-way tagged ways
// that are ways with oneway=yes/true/1
// and all motorways (tagged implicitly)
// and all motorway_links that don't have a oneway=no/false/0 tag
query("
	INSERT INTO _tmp_one_ways (way_id)
	SELECT way_id
	FROM way_tags
	WHERE (k='oneway' AND v IN ('yes', 'true', '1')) OR
		(k='highway' AND v = 'motorway') OR
		(k='highway' AND v = 'motorway_link' AND NOT EXISTS(
			SELECT tmp.k
			FROM way_tags tmp
			WHERE tmp.way_id=way_tags.way_id AND
			tmp.k='oneway' AND tmp.v IN ('no', 'false', '0')
		))
	GROUP BY way_id
", $db1);

query("CREATE INDEX idx_tmp_one_ways_first_node_id ON _tmp_one_ways (first_node_id)", $db1, false);
query("CREATE INDEX idx_tmp_one_ways_last_node_id ON _tmp_one_ways (last_node_id)", $db1, false);


// find id of first and last node as well as coordinates of last node
query("
	UPDATE _tmp_one_ways AS c
	SET first_node_id=w.first_node_id,
	last_node_id=w.last_node_id,
	last_node_lat=w.last_node_lat,
	last_node_lon=w.last_node_lon
	FROM ways AS w
	WHERE w.id=c.way_id
", $db1);


// find end nodes that are not connected to any other way
// exclude ring-ways (firstnode==lastnode)
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type, 'way', o.way_id, 'The first node (id ' || o.first_node_id || ') of this one-way is not connected to any other way.', NOW()
	FROM _tmp_one_ways o
	WHERE o.first_node_id<>o.last_node_id AND
	NOT EXISTS(
		SELECT way_id
		FROM way_nodes wn1
		WHERE o.first_node_id=wn1.node_id AND wn1.way_id<>o.way_id
	)
", $db1);

// do the same for the last node of one-ways
// need to specify lat/lon values here, because if they are left NULL
// the main script will insert lat/lon of the way's first node
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked, lat, lon)
	SELECT $error_type+1, 'way', o.way_id, 'The last node (id ' || o.last_node_id || ') of this one-way is not connected to any other way.', NOW(), 1e7*o.last_node_lat, 1e7*o.last_node_lon
	FROM _tmp_one_ways o
	WHERE o.first_node_id<>o.last_node_id AND
	NOT EXISTS(
		SELECT way_id
		FROM way_nodes wn2
		WHERE o.last_node_id=wn2.node_id AND wn2.way_id<>o.way_id
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
	'first'=>'This node cannot be reached, because one-ways only lead away from here.',
	'last'=>'You cannot escape from this node, because one-ways only lead to here.'
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
		INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
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

query("DROP TABLE IF EXISTS _tmp_one_ways", $db1);
query("DROP TABLE IF EXISTS _tmp_one_way_junctions", $db1);
?>