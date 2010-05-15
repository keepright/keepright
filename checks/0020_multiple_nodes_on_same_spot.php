<?php

/*-------------------------------------
-- find multiple nodes on the same spot
-------------------------------------

rounding x/y coordinates leads to a to an intolerance of +/- 0.5 Meters
*/


query("DROP TABLE IF EXISTS _tmp_nodes_rastered", $db1, false);
query("
	CREATE TABLE _tmp_nodes_rastered (
		x int NOT NULL,
		y int NOT NULL
	)
", $db1, false);

query("
	INSERT INTO _tmp_nodes_rastered (x, y)
	SELECT round(x), round(y)
	FROM nodes n
	GROUP BY round(x), round(y)
	HAVING COUNT(id)>1
", $db1);



query("CREATE INDEX idx_tmp_nodes_rastered_xy ON _tmp_nodes_rastered (x, y)", $db1, false);
query("ANALYZE _tmp_nodes_rastered", $db1, false);

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type, 'node', MIN(n.id), 'There is more than one node in this spot. Offending node IDs: $1', group_concat('#' || n.id), NOW()
	FROM nodes n INNER JOIN _tmp_nodes_rastered r ON (round(n.x)=r.x AND round(n.y)=r.y)
	GROUP BY r.x, r.y
", $db1);

query("DROP TABLE IF EXISTS _tmp_nodes_rastered", $db1, false);

?>