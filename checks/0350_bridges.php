<?php


/*
missing *-tag on a bridge

scenario: someone creates a bridge and forgets to tag it as highway.
the bridge won't be seen by routing software.
the problem applies to highway, railway any some more tags.

algorithm:
1) find all bridges
2) find the next ways connected to the bridge and their tagging
3) find bridges that don't have even one single tag in common with
   its next neighbour ways
*/

// special tags that need to be there at the bridge if they are present
// on one of the neighbour ways
$way_types="'highway', 'railway', 'cycleway', 'waterway', 'footway', 'piste', 'piste:type', 'aerialway', 'pipeline', 'building', 'via_ferrata'";



// way_ids of bridges
query("DROP TABLE IF EXISTS _tmp_bridges", $db1);
query("
	CREATE TABLE _tmp_bridges (
	way_id bigint NOT NULL,
	first_node_id bigint,
	last_node_id bigint,
	PRIMARY KEY (way_id)
	)
", $db1);
query("
	INSERT INTO _tmp_bridges
	SELECT way_id
	FROM way_tags wt
	WHERE k='bridge' AND v NOT IN ('no', 'false', '0')
	GROUP BY way_id
", $db1);

// first and last node of bridge-ways
query("
	UPDATE _tmp_bridges
	SET first_node_id=ways.first_node_id, last_node_id=ways.last_node_id
	FROM ways
	WHERE ways.id=_tmp_bridges.way_id
", $db1);
query("CREATE INDEX idx_tmp_bridges_first_node_id ON _tmp_bridges (first_node_id)", $db1);
query("CREATE INDEX idx_tmp_bridges_last_node_id ON _tmp_bridges (last_node_id)", $db1);

// find tags on the bridge
query("DROP TABLE IF EXISTS _tmp_bridge_tags", $db1);
query("
	CREATE TABLE _tmp_bridge_tags AS
	SELECT wt.way_id, wt.k, wt.v
	FROM way_tags wt INNER JOIN _tmp_bridges b USING (way_id)
	WHERE wt.k IN ($way_types)
", $db1);
query("CREATE INDEX idx_tmp_bridge_tags_way_id ON _tmp_bridge_tags (way_id)", $db1);


// find tags on the bridge's neighbours ways
query("DROP TABLE IF EXISTS _tmp_neighbour_tags", $db1);
query("
	CREATE TABLE _tmp_neighbour_tags AS
	SELECT DISTINCT w.way_id, wt.k, wt.v
	FROM way_tags wt INNER JOIN (

		SELECT b1.way_id, wn1.way_id as neighbour_way_id
		FROM _tmp_bridges b1 INNER JOIN way_nodes wn1 ON (b1.first_node_id=wn1.node_id)

		UNION

		SELECT b2.way_id, wn2.way_id as neighbour_way_id
		FROM _tmp_bridges b2 INNER JOIN way_nodes wn2 ON (b2.last_node_id=wn2.node_id)

	) w ON (w.neighbour_way_id=wt.way_id)
	WHERE wt.k IN ($way_types)
", $db1);



// at least one tag on the neighbours need to exist on the bridge
// this query won't return a row for a bridge that doesn't have
// any neighbour, but that seems to be OK.
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type, CAST('way' AS type_object_type), tn.way_id, 'This bridge does not have a tag in common with its surrounding ways that shows the purpose of this bridge. There should be one of these tags: ' || group_concat(tn.k || '=' || tn.v), NOW()
	FROM _tmp_neighbour_tags tn LEFT JOIN _tmp_bridge_tags tb USING (way_id, k, v)
	GROUP BY tn.way_id
	HAVING EVERY(tb.way_id IS NULL)
", $db1);


query("DROP TABLE IF EXISTS _tmp_bridges", $db1);
query("DROP TABLE IF EXISTS _tmp_bridge_tags", $db1);
query("DROP TABLE IF EXISTS _tmp_neighbour_tags", $db1);

?>