<?php


/*
inspired by maplint motorways are reported if they lack a 'ref', 'nat_ref' or 'int_ref' tag
to ease tagging, only one of these is required
*/



query("
	CREATE OR REPLACE VIEW _tmp_motorways AS
	SELECT way_id
	FROM way_tags wt
	WHERE k='highway' AND v='motorway'
	GROUP BY way_id
", $db1);

query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
	SELECT $error_type, 'way', way_id, 'This way is tagged as motorway and therefore needs a ref, nat_ref or int_ref tag', NOW()
	FROM _tmp_motorways b
	WHERE NOT EXISTS (
		SELECT * FROM way_tags wt
		WHERE wt.way_id=b.way_id AND
		wt.k IN ('ref', 'nat_ref', 'int_ref')
	)
", $db1);


query("DROP VIEW _tmp_motorways", $db1);

?>