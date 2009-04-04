<?php

/*
-------------------------------------
-- finding ways that are not connected to the rest of the map
-------------------------------------

thesis: any point in the whole map should be connected to any other node
in other words: from any point in the whole map one should be able to reach 
any one of well known points that have to be defined (for performance reasons: 
at least one point on every continent).
This check includes even small islands because ferries are considered to be 
highways. So it is not neccessary to define starting points on every island.

algorithm: starting in a few nodes find ways connected to given nodes
now find nodes that are member of these ways.
do this until no more nodes/ways are found
any way that now is not member of the list of found ways is an island (not connected)

*/



// these are way_ids picked randomly in central locations
// ways are chosen that seem to be "well-connected" (motorways typically)
$islands = array(
	'europe' => array(4433559, 4680727, 4990561, 13863372, 24318266),
	'australia' => array(20275086, 5152283),
	'africa' => array(26278273, 4698854, 27352405),
	'japan' => array(24039781),
	'america' => array(27053604, 28433666, 4615026, 15729697, 13235888, 4757176, 19627507)
);



// leave out intermediate-nodes that don't interest anybody:
// just these nodes are important, that are used at least twice
// in way_nodes (aka junctions)
// select nodes of ways (and ferries) used at least twice
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT node_id
	FROM way_nodes wn
	WHERE EXISTS (
		SELECT * FROM way_tags t WHERE t.way_id=wn.way_id AND (t.k='highway' OR (t.k='route' AND t.v='ferry') OR (t.k='railway' AND t.v='platform'))
	)
	GROUP BY node_id
	HAVING COUNT(DISTINCT way_id)>1
", $db1);
query("CREATE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);

// this is our optimized (==reduced) version of way_nodes with junctions only
query("DROP TABLE IF EXISTS _tmp_wn", $db1, false);
query("
	CREATE TABLE _tmp_wn AS
	SELECT wn.way_id, j.node_id
	FROM _tmp_junctions j INNER JOIN way_nodes wn USING (node_id)
", $db1);
query("CREATE INDEX idx_tmp_wn_node_id ON _tmp_wn (node_id)", $db1);
query("CREATE INDEX idx_tmp_wn_way_id ON _tmp_wn (way_id)", $db1);

// _tmp_island_members will hold the elitair nodes that belong to an island
query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("
	CREATE TABLE _tmp_ways (
	way_id bigint NOT NULL default 0,
	PRIMARY KEY (way_id)
	)
", $db1);
add_insert_ignore_rule('_tmp_ways', 'way_id', $db1);

query("DROP TABLE IF EXISTS _tmp_ways2", $db1, false);
query("
	CREATE TABLE _tmp_ways2 (
	way_id bigint NOT NULL default 0,
	PRIMARY KEY (way_id)
	)
", $db1);

// temporary table used for newly found nodes
query("DROP TABLE IF EXISTS _tmp_nodes", $db1, false);
query("
	CREATE TABLE _tmp_nodes (
	node_id bigint NOT NULL default 0
	)
", $db1);
query("CREATE INDEX idx_tmp_nodes_node_id ON _tmp_nodes (node_id)", $db1);



// add starting way_ids that are part of islands
$sql = "INSERT INTO _tmp_ways (way_id) VALUES ";
foreach ($islands as $island=>$ways) foreach ($ways as $way) $sql.="($way),";

query(substr($sql, 0, -1), $db1);
query("INSERT INTO _tmp_ways2 SELECT way_id FROM _tmp_ways", $db1, false);

do {
	// first find nodes that belong to ways found in the last round
	query("TRUNCATE TABLE _tmp_nodes", $db1, false);
	query("
		INSERT INTO _tmp_nodes (node_id)
		SELECT DISTINCT wn.node_id
		FROM _tmp_ways w INNER JOIN _tmp_wn wn USING (way_id)
	", $db1, false);

	// remove ways of last round
	query("TRUNCATE TABLE _tmp_ways", $db1, false);

	// insert ways that are connected to nodes found before. these make the starting
	// set for the next round
	$result=query("
		INSERT INTO _tmp_ways (way_id)
		SELECT DISTINCT wn.way_id
		FROM (_tmp_wn wn INNER JOIN _tmp_nodes n USING (node_id)) LEFT JOIN _tmp_ways2 w ON wn.way_id=w.way_id
		WHERE w.way_id IS NULL
	", $db1, false);
	$count=pg_affected_rows($result);

	// remember any newly found way in separate table
	query("INSERT INTO _tmp_ways2 SELECT way_id FROM _tmp_ways", $db1, false);
	echo "found $count additional ways\n";
} while ($count>0);



// any way that exists in way-temp-table but is not member of any island is an error
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT DISTINCT $error_type, CAST('way' AS type_object_type), wn.way_id, 'This way is not connected to the rest of the map', NOW()
	FROM _tmp_wn wn LEFT JOIN _tmp_ways2 w USING (way_id)
	WHERE w.way_id IS NULL
", $db1);



query("DROP TABLE IF EXISTS _tmp_nodes", $db1, false);
query("DROP TABLE IF EXISTS _tmp_junctions", $db1, false);
query("DROP TABLE IF EXISTS _tmp_island_members", $db1, false);
query("DROP TABLE IF EXISTS _tmp_wn", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways2", $db1, false);

?>