<?php

/*-------------------------------------
-- find multiple nodes on the same spot
-------------------------------------

in contrast to earlier implementations of this check there is no tolerance
window any more. Ie only nodes on exactly the same coordinates are considered
an error. People tend to adding more and more detailed features so precicion
increased over time and nowadays any minimum allowed distance greater than
zero may be too large.
*/



query("DROP TABLE IF EXISTS _tmp_node_dupes", $db1, false);
query("
	CREATE TABLE _tmp_node_dupes (
		lon double precision NOT NULL,
		lat double precision NOT NULL
	)
", $db1, false);

query("
	INSERT INTO _tmp_node_dupes (lon, lat)
	SELECT lon, lat
	FROM nodes n
	GROUP BY lon, lat
	HAVING COUNT(id)>1
", $db1);

query("CREATE INDEX idx_tmp_node_dupes_xy ON _tmp_node_dupes (lon,lat)", $db1, false);
query("ANALYZE _tmp_node_dupes", $db1, false);


query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type, 'node', MIN(n.id), 'There is more than one node in this spot. Offending node IDs: $1', group_concat('#' || n.id), NOW()
	FROM nodes n INNER JOIN _tmp_node_dupes d ON n.lon=d.lon AND n.lat=d.lat
	GROUP BY d.lon, d.lat
", $db1);

query("DROP TABLE IF EXISTS _tmp_node_dupes", $db1, false);


?>