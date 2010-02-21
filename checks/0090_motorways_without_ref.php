<?php


/*
inspired by maplint motorways are reported if they lack a 'ref', 'nat_ref' or 'int_ref' tag
to ease tagging, only one of these is required

including relations: It is valid not to have a ref tag if the motorway
is member of at least one relation that has a ref tag
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
		SELECT wt.k
		FROM way_tags wt
		WHERE wt.way_id=b.way_id AND
		wt.k IN ('ref', 'nat_ref', 'int_ref')

	) AND NOT EXISTS (
		SELECT rm.relation_id
		FROM relation_members rm, relation_tags rt
		WHERE rm.member_type='W' AND
		rm.member_id=b.way_id AND rt.relation_id=rm.relation_id AND
		rt.k IN ('ref', 'nat_ref', 'int_ref')
	)
", $db1);

query("DROP VIEW _tmp_motorways", $db1);
?>