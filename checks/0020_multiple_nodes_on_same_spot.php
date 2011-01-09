<?php

/*
-------------------------------------
-- find multiple nodes on the same spot
-------------------------------------

in contrast to earlier implementations of this check there is no tolerance
window any more. Ie only nodes on exactly the same coordinates are considered
an error. People tend to adding more and more detailed features so precicion
increased over time and nowadays any minimum allowed distance greater than
zero may be too large.

This check now respects the ele tag in one special case to avoid false positives:
Consider multiple nodes on the same lat/lon coordinates but with different elevation.
If (and only if) nodes on the same lat/lon coordinates have unique ele values
then no error is raised. This can happen at towers for example
*/


query("DROP TABLE IF EXISTS _tmp_elevations", $db1, false);
query("
	CREATE TABLE _tmp_elevations (
		lon double precision NOT NULL,
		lat double precision NOT NULL,
		ele text
	)
", $db1, false);

// consider up to one ele value per node id
// don't let the check stumble on nodes with multiple tags
query("
	INSERT INTO _tmp_elevations (lon, lat, ele)
	SELECT MAX(lon), MAX(lat), MAX(v)
	FROM nodes n INNER JOIN node_tags nt ON (n.id=nt.node_id)
	WHERE nt.k='ele'
	GROUP BY n.id
", $db1);

query("CREATE INDEX idx_tmp_elevations_xy ON _tmp_elevations (lon, lat)", $db1, false);
query("ANALYZE _tmp_elevations", $db1, false);



query("DROP TABLE IF EXISTS _tmp_node_dupes", $db1, false);
query("
	CREATE TABLE _tmp_node_dupes (
		lon double precision NOT NULL,
		lat double precision NOT NULL,
		nodecount int NOT NULL
	)
", $db1, false);

query("
	INSERT INTO _tmp_node_dupes (lon, lat, nodecount)
	SELECT lon, lat, COUNT(n.id)
	FROM nodes n
	GROUP BY lon, lat
	HAVING COUNT(n.id)>1
", $db1);

query("CREATE INDEX idx_tmp_node_dupes_xy ON _tmp_node_dupes (lon, lat)", $db1, false);
query("ANALYZE _tmp_node_dupes", $db1, false);



// exclude nodes from the errors list where the number of nodes on the spot equals
// the number of unique elevation values [ie. every node has a elevation value
// and all elevation values are different]
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type, 'node', MIN(n.id), 'There is more than one node in this spot. Offending node IDs: $1', group_concat('#' || n.id), NOW()
	FROM nodes n INNER JOIN _tmp_node_dupes d ON n.lon=d.lon AND n.lat=d.lat
	WHERE NOT(d.nodecount = (
		SELECT COUNT(DISTINCT ele)
		FROM _tmp_elevations e
		WHERE e.lon=d.lon AND e.lat=d.lat
	))
	GROUP BY d.lon, d.lat
", $db1);

print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_node_dupes", $db1, false);
query("DROP TABLE IF EXISTS _tmp_elevations", $db1, false);

?>