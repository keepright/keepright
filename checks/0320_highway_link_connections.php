<?php

/*
-------------------------------------
-- invalid link connections
-------------------------------------

A primary_link must have at least one connection to a primary highway
the same is valid for motorway_link, secondary_link and trunk_link roads

*/

// find *_link highways
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways AS
	SELECT DISTINCT way_id, substr(v, 1, strpos(v, '_')-1) AS class
	FROM way_tags t
	WHERE t.k='highway' AND
	t.v IN ('motorway_link', 'trunk_link', 'primary_link', 'secondary_link')
", $db1);
query("CREATE INDEX idx_tmp_ways_way_id ON _tmp_ways (way_id)", $db1);
query("ANALYZE _tmp_ways", $db1);


// find all nodes these links contain
query("DROP TABLE IF EXISTS _tmp_wn", $db1);
query("
	CREATE TABLE _tmp_wn AS
	SELECT way_id, class, node_id
	FROM way_nodes wn INNER JOIN _tmp_ways w USING (way_id)
", $db1);
query("CREATE INDEX idx_tmp_wn_node_id ON _tmp_wn (node_id)", $db1);
query("ANALYZE _tmp_wn", $db1);


// find all other ways these *_link ways are connected to
query("DROP TABLE IF EXISTS _tmp_wn2", $db1);
query("
	CREATE TABLE _tmp_wn2 AS
	SELECT twn.way_id, twn.class, wn.way_id AS way_id2
	FROM way_nodes wn INNER JOIN _tmp_wn twn USING (node_id)
	WHERE wn.way_id<>twn.way_id
", $db1);
query("ALTER TABLE _tmp_wn2 ADD COLUMN class2 VARCHAR", $db1);


// determine their class
query("
	UPDATE _tmp_wn2 wn
	SET class2=CASE WHEN wt.v LIKE '%_link'
		THEN substr(v, 1, strpos(v, '_')-1)
		ELSE wt.v END
	FROM way_tags wt
	WHERE wn.way_id2=wt.way_id
		AND wt.k='highway' AND wt.v IN (wn.class, wn.class || '_link')
", $db1);


// any _link without at least one other highway of the same
// class is an error
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type, CAST('way' AS type_object_type), way_id, 'This way is tagged as highway=$1_link but doesn''t have a connection to any other $1 or $1_link', class, NOW()
	FROM _tmp_ways w
	WHERE NOT EXISTS (
		SELECT * FROM _tmp_wn2 t
		WHERE t.way_id=w.way_id AND t.class2 IS NOT NULL
	)
", $db1);


print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("DROP TABLE IF EXISTS _tmp_wn", $db1);
query("DROP TABLE IF EXISTS _tmp_wn2", $db1);

?>