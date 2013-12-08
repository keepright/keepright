<?php


/*
inspired by maplint bridges and tunnels are reported if they lack a layer tag
*/

// this check is obsoleted by 190_intersections_without_junctions.
// layer tag is not mandatory for bridges and tunnels as long
// as there are no intersections

/*
query("
	CREATE OR REPLACE VIEW _tmp_bridges_tunnels AS
	SELECT way_id
	FROM way_tags wt
	WHERE (k='bridge' OR k='tunnel') AND v IN ('yes', 'true', '1')
	GROUP BY way_id
", $db1);

query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
	SELECT $error_type, 'way', way_id, 'This way is tagged as bridge or tunnel and therefore needs a layer tag', NOW()
	FROM _tmp_bridges_tunnels b 
	WHERE NOT EXISTS( SELECT * FROM way_tags wt WHERE wt.way_id=b.way_id AND wt.k='layer' )
", $db1);

query("DROP VIEW _tmp_bridges_tunnels", $db1);
*/
?>