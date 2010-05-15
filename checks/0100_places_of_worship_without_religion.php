<?php


/*
inspired by maplint places of worship are reported if they lack a religion tag
*/



query("
	CREATE OR REPLACE VIEW _tmp_places_of_worship AS
	SELECT node_id
	FROM node_tags
	WHERE k='amenity' AND v='place_of_worship'
	GROUP BY node_id
", $db1);

query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type, 'node', node_id, 'This node is tagged as place of worship and therefore needs a religion tag', NOW()
	FROM _tmp_places_of_worship b
	WHERE NOT EXISTS( SELECT * FROM node_tags nt WHERE nt.node_id=b.node_id AND nt.k IN ('religion', 'denomination') )
", $db1);

query("DROP VIEW _tmp_places_of_worship", $db1);

?>