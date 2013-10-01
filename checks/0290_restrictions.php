<?php

/*
-------------------------------------
-- errors on turn restrictions
-------------------------------------

* missing restriction tag
* missing type tag (already covered by check 180)
* unknown value on restriction tag
* not exactly one item as from way
* not exactly one item as to way
* from and to need to be ways
* if via is present: from and to need to be connected to via
* if location_hint is present: it has to be one single node
*/


$restriction_types = "'restriction', 'restriction:hgv', 'restriction:caravan', 'restriction:motorcar', 'restriction:bus', 'restriction:agricultural', 'restriction:motorcycle', 'restriction:bicycle', 'restriction:hazmat'";


// find all restrictions
query("DROP TABLE IF EXISTS _tmp_restrictions", $db1, false);
query("
	CREATE TABLE _tmp_restrictions AS
	SELECT relation_id
	FROM relation_tags t
	WHERE t.k='type' and t.v IN ($restriction_types)
", $db1);
query("CREATE INDEX idx_tmp_restrictions_relation_id ON _tmp_restrictions (relation_id)", $db1, false);
query("ANALYZE _tmp_restrictions", $db1, false);


// missing or wrong restriction tag
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type+1, CAST('relation' AS type_object_type),
	r.relation_id, 'This turn-restriction has no restriction type', NOW()
	FROM _tmp_restrictions r
	WHERE NOT EXISTS (
		SELECT v FROM relation_tags t
		WHERE t.relation_id=r.relation_id AND t.k IN ($restriction_types)
	)
", $db1);

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type+1, CAST('relation' AS type_object_type),
	r.relation_id, 'This turn-restriction has no known restriction type', NOW()
	FROM _tmp_restrictions r LEFT JOIN relation_tags t USING (relation_id)
	WHERE t.k IN ($restriction_types) AND t.v NOT IN (
		'no_left_turn','no_right_turn','no_u_turn',
		'only_straight_on','no_straight_on',
		'only_left_turn','only_right_turn',
		'no_entry', 'no_exit'
	)
", $db1);


// check cardinality of from and to members
foreach (array(2=>'from', 3=>'to') as $k=>$v) {
	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
		SELECT $error_type+$k, CAST('relation' AS type_object_type),
		r.relation_id, 'A turn-restriction needs exactly one $1 member. This one has $2', '$v', COUNT(rm.member_id), NOW()
		FROM _tmp_restrictions r LEFT JOIN (
			SELECT relation_id, member_id
			FROM relation_members m
			WHERE member_role='$v'
		) rm USING (relation_id)
		GROUP BY r.relation_id
		HAVING COUNT(rm.member_id)<>1
	", $db1);
}


// check that from and to really are ways
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type+4, CAST('relation' AS type_object_type),
	r.relation_id, 'From- and To-members of turn restrictions need to be ways. $1',
	htmlspecialchars(group_concat(m.member_role ||
		CASE WHEN m.member_type='N' THEN ' node #' ELSE ' relation #' END
		|| m.member_id)),
	NOW()
	FROM _tmp_restrictions r INNER JOIN relation_members m USING (relation_id)
	WHERE member_role IN ('from', 'to') AND m.member_type<>'W'
	GROUP BY r.relation_id
", $db1);




print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_restrictions", $db1);

?>