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
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type+1,
	CASE WHEN relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END,
	COALESCE(relation_id, way_id), 'This boundary has no name.', NOW()
	FROM _tmp_border_ways
	WHERE name IS NULL
", $db1);


// missing admin_level
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type+2,
	CASE WHEN relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END,
	COALESCE(relation_id, way_id), 'The boundary of ' || name || ' has no admin_level.', NOW()
	FROM _tmp_border_ways
	WHERE admin_level IS NULL
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
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked, lat, lon)
	SELECT $error_type+3,
	CASE WHEN relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END AS ot,
	COALESCE(relation_id, way_id) AS oid, ' The boundary of ' || MIN(name) || ' is not closed-loop.', NOW(), 1e7*n.lat, 1e7*n.lon
	FROM _tmp_open_parts o INNER JOIN nodes n ON (n.id=o.node_id1 OR n.id=o.node_id2)
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
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked, lat, lon)
	SELECT $error_type+4,
	CASE WHEN b.relation_id IS NULL THEN
		CAST('way' AS type_object_type)
	ELSE
		CAST('relation' AS type_object_type)
	END AS ot,
	COALESCE(b.relation_id, b.way_id) AS oid, ' The boundary of ' || MIN(nl.name) || ' splits here.', NOW(), 1e7*n.lat, 1e7*n.lon
	FROM _tmp_evil_nodes nl INNER JOIN _tmp_border_ways b USING (name, admin_level)
	INNER JOIN nodes n on nl.node_id=n.id
	GROUP BY ot, oid, n.lat, n.lon
", $db1);



query("DROP TABLE IF EXISTS _tmp_open_parts", $db1);
query("DROP TABLE IF EXISTS _tmp_nodelists", $db1);
query("DROP TABLE IF EXISTS _tmp_evil_nodes", $db1);

?>