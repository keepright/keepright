<?php


/*
common nodes between highways and railways (aka level crossings)
have to be tagged in a special way:
- need the railway=level_crossing tag to be set on the junction node
- this tag is allowed only if highway and railway are on the same layer
- it is unlikely that this tag is appropriate if the railway or the
  highway are tagged as bridge or as tunnel
*/


// find all railway=level_crossing nodes
query("DROP TABLE IF EXISTS _tmp_nodes", $db1);
query("
	CREATE TABLE _tmp_nodes AS
	SELECT DISTINCT node_id
	FROM node_tags
	WHERE k='railway' AND v='level_crossing'
", $db1);

query("CREATE INDEX idx_tmp_nodes_node_id ON _tmp_nodes (node_id)", $db1);
query("ANALYZE _tmp_nodes", $db1);


// find all ways passing through these nodes
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways (
		way_id bigint NOT NULL,
		node_id bigint NOT NULL,
		layer text DEFAULT '0'
	)
", $db1);
query("
	INSERT INTO _tmp_ways (way_id, node_id)
	SELECT DISTINCT wn.way_id, wn.node_id
	FROM way_nodes wn INNER JOIN _tmp_nodes n USING (node_id)
", $db1);

query("CREATE INDEX idx_tmp_ways_way_id ON _tmp_ways (way_id)", $db1);
query("CREATE INDEX idx_tmp_ways_node_id ON _tmp_ways (node_id)", $db1);
query("ANALYZE _tmp_ways", $db1);

find_layer_values('_tmp_ways', 'way_id', 'layer', $db1);

 

query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type, 'node', node_id, 'There are ways in different layers coming together in this railway crossing', NOW()
	FROM _tmp_ways
	GROUP BY node_id
	HAVING COUNT(DISTINCT layer)>1
", $db1);



query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type, 'node', w.node_id, 'There are ways tagged as tunnel or bridge coming together in this railway crossing', NOW()
	FROM _tmp_ways w
	WHERE EXISTS (
		SELECT 1
		FROM way_tags wt
		WHERE wt.way_id=w.way_id AND
		wt.k IN ('bridge', 'tunnel')
		AND wt.v NOT IN ('no', 'false', '0')
	)
", $db1);


query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("DROP TABLE IF EXISTS _tmp_nodes", $db1, false);

?>
