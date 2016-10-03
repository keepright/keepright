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
* if via is present: from and to need to start or end at via
* only_* restrictions must not direct to oneway in opposite direction
* no_* restrictions are useless if already covered by oneway tag on 'to' member
* if location_hint is present: it has to be one single node
*/


$restriction_types = "'restriction', 'restriction:hgv', 'restriction:caravan', 'restriction:motorcar', 'restriction:bus', 'restriction:agricultural', 'restriction:motorcycle', 'restriction:bicycle', 'restriction:hazmat'";


// find all restrictions and store members
query("DROP TABLE IF EXISTS _tmp_restrictions", $db1, false);
query("
	CREATE TABLE _tmp_restrictions (
		relation_id bigint,
		from_id bigint,
		to_id bigint,
		via_id bigint,
		via_lat double precision,
		via_lon double precision
	)
", $db1);


query("
	INSERT INTO _tmp_restrictions (relation_id)
	SELECT t.relation_id
	FROM relation_tags t
	WHERE t.k='type' and t.v IN ($restriction_types)
", $db1);

query("CREATE INDEX idx_tmp_restrictions_relation_id ON _tmp_restrictions (relation_id)", $db1, false);
query("ANALYZE _tmp_restrictions", $db1, false);


// find IDs of from, to and via objets

query("
	UPDATE _tmp_restrictions
	SET from_id=rm.member_id
	FROM relation_members rm
	WHERE _tmp_restrictions.relation_id = rm.relation_id AND
		rm.member_role = 'from' AND
		rm.member_type = 'W'
", $db1);

query("
	UPDATE _tmp_restrictions
	SET to_id=rm.member_id
	FROM relation_members rm
	WHERE _tmp_restrictions.relation_id = rm.relation_id AND
		rm.member_role = 'to' AND
		rm.member_type = 'W'
", $db1);

query("
	UPDATE _tmp_restrictions
	SET via_id=rm.member_id
	FROM relation_members rm
	WHERE _tmp_restrictions.relation_id = rm.relation_id AND
		rm.member_role = 'via' AND
		rm.member_type = 'N'
", $db1);

query("CREATE INDEX idx_tmp_restrictions_from_id ON _tmp_restrictions (from_id)", $db1, false);
query("CREATE INDEX idx_tmp_restrictions_to_id ON _tmp_restrictions (to_id)", $db1, false);
query("CREATE INDEX idx_tmp_restrictions_via_id ON _tmp_restrictions (via_id)", $db1, false);
query("ANALYZE _tmp_restrictions", $db1, false);


// retrieve position of via node
query("
	UPDATE _tmp_restrictions
	SET via_lat=n.lat, via_lon=n.lon
	FROM nodes n
	WHERE n.id=_tmp_restrictions.via_id
", $db1);


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

// delete relations with incomplete info; this is already reported
query("
	DELETE from _tmp_restrictions 
	WHERE from_id IS NULL 
		OR to_id IS NULL
		OR via_id IS NULL
", $db1);

// delete relations with errors from further testing
query("
	DELETE FROM _tmp_restrictions r USING _tmp_errors e
	WHERE r.relation_id = e.object_id AND e.error_type BETWEEN $error_type AND $error_type+4
", $db1);


// report restrictions where via is not the first or the last node in "from" or in "to"
// this doesn't make sense for relations with cardinalities>1 for from and to members
// because the database will choose at random which ID to write into from_id and to_id
// so error lists would fluctuate
foreach (array('from', 'to') as $type) {
	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, lat, lon, msgid, txt1, txt2, last_checked)
		SELECT $error_type+5, CAST('relation' AS type_object_type),
		r.relation_id, 1e7*r.via_lat, 1e7*r.via_lon,
		'via (node #$1) is not the first or the last member of $type (way #$2)',
		r.via_id, r.${type}_id,
		NOW()
		FROM _tmp_restrictions r INNER JOIN ways w ON r.${type}_id = w.id
		WHERE r.via_id != w.first_node_id AND r.via_id != w.last_node_id
	", $db1);
}


// report restrictions where angle do not correspond with restriction
// this one assumes that via is the first or last node of from/to
// so exclude relations with error 295 here
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, lat, lon, msgid, txt1, txt2, last_checked)
	SELECT $error_type+6, CAST('relation' AS type_object_type),
	ii.relation_id, 1e7*ii.via_lat, 1e7*ii.via_lon,
	'restriction type is $1, but angle is $2 degrees. Maybe the restriction type is not appropriate?',
	ii.restriction_type, round(ii.d),
	NOW()
	FROM (
		SELECT i.*,
			CASE WHEN (i.a2-i.a1) > pi() THEN ((i.a2-i.a1)/pi() -2)*180 
			WHEN (i.a2-i.a1) < -pi() THEN ((i.a2-i.a1)/pi() +2)*180 
			ELSE (i.a2-i.a1)/pi()*180 END as d 
		FROM (
			SELECT r.relation_id, r.via_lat, r.via_lon,
			rt.v as restriction_type,
			CASE WHEN ST_Azimuth(nf.geom, nv.geom) > pi() 
				THEN ST_Azimuth(nf.geom, nv.geom) - 2.0*pi() 
				ELSE ST_Azimuth(nf.geom, nv.geom) END as a1, 
			CASE WHEN ST_Azimuth(nv.geom, nt.geom) > pi() 
				THEN ST_Azimuth(nv.geom, nt.geom) - 2.0*pi() 
				ELSE ST_Azimuth(nv.geom, nt.geom) END as a2, 
			rt.v, nf.id, nf.geom, nv.id, nv.geom, nt.id, nt.geom 
			FROM _tmp_restrictions r 
			LEFT JOIN relation_tags rt ON r.relation_id = rt.relation_id 
				AND rt.k IN ($restriction_types)
			LEFT JOIN ways wf ON r.from_id = wf.id
			LEFT JOIN way_nodes wn1 ON r.from_id = wn1.way_id AND wn1.sequence_id = 
				(CASE WHEN r.via_id = wf.first_node_id THEN 1 ELSE wf.node_count -2 END) 
			LEFT JOIN nodes nf ON wn1.node_id = nf.id
			LEFT JOIN nodes nv ON r.via_id = nv.id
			LEFT JOIN ways wt ON r.to_id = wt.id
			LEFT JOIN way_nodes wn2 ON r.to_id = wn2.way_id AND wn2.sequence_id = 
				(CASE WHEN r.via_id = wt.first_node_id THEN 1 ELSE wt.node_count -2 END) 
			LEFT JOIN nodes nt ON wn2.node_id = nt.id
		) i
	) ii
WHERE 
	CASE
		WHEN (ii.v = 'only_straight_on' OR ii.v = 'no_straight_on') AND ii.d > -50 and ii.d < 50 THEN 0
		WHEN (ii.v = 'only_right_turn' OR ii.v = 'no_right_turn') AND ii.d > 5 THEN 0
		WHEN (ii.v = 'only_left_turn' OR ii.v = 'no_left_turn') AND ii.d < -5 THEN 0
		WHEN ii.v = 'no_u_turn' AND (ii.d < -95 or ii.d > 179.99) THEN 0
		ELSE 1
	END = 1 AND ii.d IS NOT NULL AND
	NOT EXISTS (
		SELECT 1
		FROM _tmp_errors e
		WHERE e.object_id=ii.relation_id AND
			e.error_type=$error_type+5

	)
", $db1);

// TODO: in the above check change no_u_turn angles to <-179.99 AND > -95 for right side
// driving countries


// check for restrictions guiding to oneway in wrong direction
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, lat, lon, msgid, txt1, last_checked)
	SELECT $error_type+7, CAST('relation' AS type_object_type),
	ii.relation_id, 1e7*ii.via_lat, 1e7*ii.via_lon,
	'wrong direction of to way $1',
	ii.to_id, NOW() FROM (

		SELECT r.relation_id, r.via_lat, r.via_lon, r.to_id
		FROM _tmp_restrictions r
		LEFT JOIN relation_tags rt ON r.relation_id = rt.relation_id
		LEFT JOIN ways tw ON r.to_id = tw.id
		LEFT JOIN way_tags twt ON r.to_id = twt.way_id
		WHERE rt.k = 'restriction' AND rt.v IN ('only_straight_on', 'only_left_turn', 'only_right_turn')
		   AND twt.k = 'oneway' AND (
			(twt.v = 'yes' AND r.via_id = tw.last_node_id)
			OR (twt.v = '-1' AND r.via_id = tw.first_node_id)
		   )

	) ii

", $db1);

// check for useless restrictions covered by oneway tag not allowing to enter
// 'to' member
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, lat, lon, msgid, txt1, last_checked)
	SELECT $error_type+8, CAST('relation' AS type_object_type),
	ii.relation_id, 1e7*ii.via_lat, 1e7*ii.via_lon,
	'entry already prohibited by oneway tag on $1',
	ii.to_id, NOW() FROM (

		SELECT r.relation_id, r.via_lat, r.via_lon, r.to_id
		FROM _tmp_restrictions r
		LEFT JOIN relation_tags rt ON r.relation_id = rt.relation_id
		LEFT JOIN ways tw ON r.to_id = tw.id
		LEFT JOIN way_tags twt ON r.to_id = twt.way_id
		WHERE rt.k = 'restriction' AND rt.v IN ('no_straight_on', 'no_left_turn', 'no_right_turn', 'no_u_turn')
		   AND twt.k = 'oneway' AND (
			(twt.v = 'yes' AND r.via_id = tw.last_node_id)
			OR (twt.v = '-1' AND r.via_id = tw.first_node_id)
		   )

	) ii

", $db1);

// TODO: check for useless only_* restrictions where there is only one way
// out of via

print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_restrictions", $db1);

?>