<?php


/*
common nodes between highways and railways (aka (level) crossings)
need the railway=level_crossing tag or railway=crossing to be set on the junction node
exception: railway=tram tagged railways on highways are allowed
exception: a junction between a highway and a railway is allowed if it is on a railway=station
*/


// first find all ways and nodes of ways that are tagged as railways

query("DROP TABLE IF EXISTS _tmp_railways", $db1);
query("
        CREATE TABLE _tmp_railways (
		way_id bigint NOT NULL,
		node_id bigint NOT NULL
        )
", $db1);
query("
	INSERT INTO _tmp_railways
	SELECT wn.way_id, wn.node_id
	FROM way_tags wt INNER JOIN way_nodes wn USING (way_id)
	WHERE wt.k='railway' AND wt.v NOT IN ('disused', 'abandoned', 'tram', 'tram:disused', 'tram;disused', 'platform', 'Platform', 'plattform', 'plateform')
	AND NOT EXISTS(
		SELECT tmp.way_id
		FROM way_tags tmp
		WHERE tmp.k='disused' AND tmp.v IN ('yes', 'true', '1')
	)
	GROUP BY wn.way_id, wn.node_id
", $db1);


// find any node of a railway
// that is part of another way,
// that is tagged as a highway
// and which (the node) has not the railway=level_crossing tag or railway=crossing
query("
        INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT DISTINCT $error_type, CAST('node' as type_object_type), r.node_id, 'This crossing of a highway and a railway needs to be tagged as railway=crossing or railway=level_crossing', NOW()
	FROM _tmp_railways r
	WHERE EXISTS (
		SELECT wn.way_id FROM way_nodes wn
		WHERE wn.node_id=r.node_id AND wn.way_id<>r.way_id
		AND EXISTS (
			SELECT wt.k FROM way_tags wt
			WHERE wt.way_id=wn.way_id AND wt.k='highway'
		)
	)
	AND NOT EXISTS (
		SELECT nt.k FROM node_tags nt
		WHERE nt.node_id=r.node_id AND nt.k='railway' AND (nt.v='level_crossing' OR nt.v='crossing')
	)
	AND NOT EXISTS (
		SELECT nt2.k FROM node_tags nt2
		WHERE nt2.node_id=r.node_id AND nt2.k='railway' AND nt2.v='station'
	)
", $db1);

query("DROP TABLE IF EXISTS _tmp_railways", $db1);

?>