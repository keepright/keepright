<?php

/*
-------------------------------------
-- errors on boundaries
-------------------------------------

* missing name
* missing admin_level
* not all members part of the relation
* more than one relation of same name and admin_level
* relation not closed-loop
* a tagged way may not be member of a relation of lower admin_level

about direction of ways:
it is annoying that member ways of boundary-relations may have
the wrong direction. But that's a problem, you cannot solve if a
single way can be member of more than one boundary at the same time:

        +--->------->---+
        |               |
        ^               v
        |               |
        +--><------><---+
        |               |
        ^               v
        |               |
        +-------<-------+

The middle way has always the wrong direction!
So we have to live with that...


*/


// missing name
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT DISTINCT $error_type+1,
	CASE WHEN relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END,
	COALESCE(relation_id, way_id), 'This boundary has no name', NOW()
	FROM _tmp_border_ways
	WHERE name IS NULL
", $db1);


// missing admin_level
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT DISTINCT $error_type+2,
	CASE WHEN relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END,
	COALESCE(relation_id, way_id), 'The boundary of $1 has no admin_level', htmlspecialchars(COALESCE(name, '(no name)')), NOW()
	FROM _tmp_border_ways
	WHERE admin_level IS NULL
", $db1);


// not a numeric admin_level
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT DISTINCT $error_type+2,
	CASE WHEN relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END,
	COALESCE(relation_id, way_id), 'The boundary of $1 has no valid numeric admin_level. Please do not use admin levels like for example 6;7. Always tag the lowest admin_level of all boundaries.', htmlspecialchars(COALESCE(name, '(no name)')), NOW()
	FROM _tmp_border_ways
	WHERE NOT (trim(admin_level) ~ '^[0-9]+$')
", $db1);



// non-closed loops
// compare the appropriate end nodes of the first and last way segments
// consider the direction field and use the other end node if it has opposite direction
// b1 is the first segment, b2 the last segment of the same part of a boundary

query("DROP TABLE IF EXISTS _tmp_open_parts", $db1);
query("
	CREATE TABLE _tmp_open_parts (
		name text,
		admin_level text,
		relation_id bigint,
		way_id bigint,
		node_id1 bigint,
		node_id2 bigint
	)
", $db1);


query("
	INSERT INTO _tmp_open_parts (relation_id, way_id, name, node_id1, node_id2)
	SELECT b1.relation_id, b1.way_id, b1.name,
	CASE WHEN COALESCE(b1.direction,1)=1 THEN
		b1.first_node_id
	ELSE
		b1.last_node_id
	END,
	CASE WHEN COALESCE(b2.direction,1)=1 THEN
		b2.last_node_id
	ELSE
		b2.first_node_id
	END

	FROM _tmp_border_ways b1, _tmp_border_ways b2
	WHERE b1.name=b2.name AND b1.admin_level=b2.admin_level AND b1.part=b2.part AND
	b1.sequence_id = (
		SELECT MIN(t1.sequence_id)
		FROM _tmp_border_ways t1
		WHERE t1.name=b2.name AND t1.admin_level=b2.admin_level AND t1.part=b2.part
	) AND

	b2.sequence_id = (
		SELECT MAX(t2.sequence_id)
		FROM _tmp_border_ways t2
		WHERE t2.name=b2.name AND t2.admin_level=b2.admin_level AND t2.part=b2.part
	) AND

	CASE WHEN COALESCE(b1.direction,1)=1 THEN
		b1.first_node_id
	ELSE
		b1.last_node_id
	END
	<>
	CASE WHEN COALESCE(b2.direction,1)=1 THEN
		b2.last_node_id
	ELSE
		b2.first_node_id
	END
", $db1);


// insert errors trying to avoid duplicates
// raise an error only if
// a) the way is a member of a relation and doesn't have boundary tags himself
// b) the way is not member of a relation
// i.e. don't complain about tagged ways that are member of a relation because
// if the way belongs to multiple boundaries on different admin_levels, the
// way himself will be tagged with the highest admin_level
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked, lat, lon)
	SELECT $error_type+3,
	CASE WHEN relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END AS ot,
	COALESCE(relation_id, way_id) AS oid, 'The boundary of $1 is not closed-loop', htmlspecialchars(MIN(name)), NOW(), 1e7*n.lat, 1e7*n.lon
	FROM _tmp_open_parts o INNER JOIN nodes n ON (n.id=o.node_id1 OR n.id=o.node_id2)
	WHERE relation_id IS NOT NULL OR NOT EXISTS(
		SELECT tmp.relation_id
		FROM _tmp_border_ways tmp
		WHERE tmp.way_id=o.way_id AND tmp.relation_id IS NOT NULL
	)
	GROUP BY ot, oid, n.lat, n.lon
", $db1);



// degenerated loops are not rings. for example:
//
//       +---------------+
//       |               |
//       |               |
//       +---------------+-----
//
// they can be found because the invalid junction node
// belongs to three ways. The algorithm of ordering ways
// assigns one sequence_id twice

query("DROP TABLE IF EXISTS _tmp_nodelists", $db1);
query("
	CREATE TABLE _tmp_nodelists (
		name text,
		admin_level text,
		part integer,
		node_id bigint
	)
", $db1);


query("
	INSERT INTO _tmp_nodelists
	SELECT name, admin_level, part, first_node_id
	FROM _tmp_border_ways
	WHERE name IS NOT NULL AND admin_level IS NOT NULL
", $db1);
query("
	INSERT INTO _tmp_nodelists
	SELECT name, admin_level, part, last_node_id
	FROM _tmp_border_ways
	WHERE name IS NOT NULL AND admin_level IS NOT NULL
", $db1);

query("DROP TABLE IF EXISTS _tmp_evil_nodes", $db1);
query("
	CREATE TABLE _tmp_evil_nodes AS
	SELECT name, admin_level, part, node_id
	FROM _tmp_nodelists
	GROUP BY name, admin_level, part, node_id
	HAVING COUNT(*)>2
", $db1);

query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked, lat, lon)
	SELECT $error_type+4,
	CASE WHEN b.relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END AS ot,
	COALESCE(b.relation_id, b.way_id) AS oid, 'The boundary of $1 splits here', htmlspecialchars(MIN(nl.name)), NOW(), 1e7*n.lat, 1e7*n.lon
	FROM _tmp_evil_nodes nl INNER JOIN _tmp_border_ways b USING (name, admin_level)
	INNER JOIN nodes n on nl.node_id=n.id
	GROUP BY ot, oid, n.lat, n.lon
", $db1);



// a boundary that is member of relations and itself owns a boundary-tag
// must have the lowest admin_level of all relations in his own tag
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type+5, CAST('way' AS type_object_type) AS ot,
	b.way_id, 'This boundary-way has admin_level $1 but belongs to a relation with lower admin_level (higher priority); it should have the lowest admin_level of all relations', htmlspecialchars(MAX(b.admin_level)), NOW()
	FROM _tmp_border_ways b
	WHERE relation_id IS NULL AND TRIM(admin_level) ~ '^[0-9]+$'
	AND CAST(admin_level AS INT)=(SELECT MAX(CAST(tmp1.admin_level AS INT))
		FROM _tmp_border_ways tmp1
		WHERE tmp1.way_id=b.way_id AND tmp1.relation_id IS NULL
	)
	AND CAST(admin_level AS INT)>(SELECT MIN(CAST(tmp2.admin_level AS INT))
		FROM _tmp_border_ways tmp2
		WHERE tmp2.way_id=b.way_id AND tmp2.relation_id IS NOT NULL
	)
	GROUP BY b.way_id
", $db1);



print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_open_parts", $db1);
query("DROP TABLE IF EXISTS _tmp_nodelists", $db1);
query("DROP TABLE IF EXISTS _tmp_evil_nodes", $db1);

?>