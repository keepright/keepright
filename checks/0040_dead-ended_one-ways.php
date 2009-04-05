<?php

/*
-------------------------------------
-- dead-ended one-ways
-------------------------------------

a way tagged as oneway must always be connected to other ways on both end-nodes

*/


	query("DROP TABLE IF EXISTS _tmp_one_ways;", $db1);

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

	query("
		INSERT INTO _tmp_one_ways (way_id)
		SELECT way_id
		FROM way_tags
		WHERE k='oneway' AND v IN ('yes', 'true', '1')
		GROUP BY way_id
	", $db1);

	query("CREATE INDEX idx_tmp_one_ways_first_node_id ON _tmp_one_ways (first_node_id)", $db1, false);
	query("CREATE INDEX idx_tmp_one_ways_last_node_id ON _tmp_one_ways (last_node_id)", $db1, false);


	// maybe disable this check on segments of motorways?
//	query("
//		DELETE FROM _tmp_one_ways
//		WHERE EXISTS(
//			SELECT *
//			FROM way_tags
//			WHERE k='highway' AND v='motorway' AND way_id=_tmp_one_ways.way_id
//		)
//	", $db1);

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

	query("DROP TABLE IF EXISTS _tmp_one_ways;", $db1);

?>