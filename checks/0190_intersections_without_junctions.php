<?php

/*
-------------------------------------
-- intersections_without_junctions
-------------------------------------

find crossings of highways that are not represented by a common node

to avoid false positives on highways crossing squares, areas are excluded here

*/


if (!type_exists($db1, 'type_way_type'))
	query("CREATE TYPE type_way_type AS ENUM('highway','cycleway/footpath','waterway','riverbank')", $db1);

// tmp_ways will contain all highways with their linestring geometry and layer tag
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways (
	way_id bigint NOT NULL,
	layer text NOT NULL DEFAULT '0',
	way_type type_way_type NOT NULL
	)
", $db1);
query("SELECT AddGeometryColumn('_tmp_ways', 'geom', 4326, 'LINESTRING', 2)", $db1);

// find any highway-tagged way that is not a cycleway/footpath
// exclude proposed and construction highways as they are intentionally not connected
query("
	INSERT INTO _tmp_ways (way_id, geom, way_type)
	SELECT id, geom, CAST('highway' AS type_way_type)
	FROM ways
	WHERE geom IS NOT NULL AND EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE wt.k = 'highway' AND wt.v NOT IN ('cycleway', 'footpath', 'proposed', 'construction')
		AND wt.way_id=ways.id
	)
", $db1);

query("ALTER TABLE _tmp_ways ADD PRIMARY KEY (way_id);", $db1);
query("ANALYZE _tmp_ways", $db1);

// find any cycleway/footpaths
query("
	INSERT INTO _tmp_ways (way_id, geom, way_type)
	SELECT id, geom, CAST('cycleway/footpath' AS type_way_type)
	FROM ways
	WHERE geom IS NOT NULL AND EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE wt.k = 'highway' AND wt.v IN ('cycleway', 'footpath') AND wt.way_id=ways.id
	)
	AND NOT EXISTS (
		SELECT id
		FROM _tmp_ways tmp
		WHERE tmp.way_id=ways.id
	)
", $db1);

// now add waterways but not riverbanks(docks/boatyards)
query("
	INSERT INTO _tmp_ways (way_id, geom, way_type)
	SELECT id, geom, CAST('waterway' AS type_way_type)
	FROM ways
	WHERE geom IS NOT NULL AND EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE wt.k = 'waterway' AND wt.v NOT IN ('riverbank', 'dock', 'boatyard') AND wt.way_id=ways.id
	)
	AND NOT EXISTS (
		SELECT id
		FROM _tmp_ways tmp
		WHERE tmp.way_id=ways.id
	)
", $db1);

// finally add riverbanks
query("
	INSERT INTO _tmp_ways (way_id, geom, way_type)
	SELECT id, geom, CAST('riverbank' AS type_way_type)
	FROM ways
	WHERE geom IS NOT NULL AND EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE ((wt.k = 'waterway' AND wt.v IN ('riverbank', 'dock', 'boatyard')) OR
			(wt.k = 'natural' AND wt.v = 'water'))
			AND wt.way_id=ways.id
	)
	AND NOT EXISTS (
		SELECT id
		FROM _tmp_ways tmp
		WHERE tmp.way_id=ways.id
	)
", $db1);

// remove areas
query("
	DELETE FROM _tmp_ways
	WHERE EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE wt.k='area' AND wt.v='yes' AND wt.way_id=_tmp_ways.way_id
	)
", $db1);

// fetch layer tag
find_layer_values('_tmp_ways', 'way_id', 'layer', $db1);


query("CREATE INDEX idx_tmp_ways_layer ON _tmp_ways (layer)", $db1);
query("CREATE INDEX idx_tmp_ways_way_type ON _tmp_ways (way_type)", $db1);
query("CREATE INDEX idx_tmp_ways_geom ON _tmp_ways USING gist (geom)", $db1);
query("ANALYZE _tmp_ways", $db1);


// create a helper table needed by connected_near() function
// all junctions of ways and the location of junction
// first include all crossings; remove crossings on ways not interesting
query("DROP TABLE IF EXISTS _tmp_wn", $db1);
query("
	CREATE TABLE _tmp_wn AS
	SELECT way_nodes.way_id, way_nodes.node_id, way_nodes.x, way_nodes.y
	FROM way_nodes INNER JOIN _tmp_ways USING (way_id)
", $db1);
query("CREATE INDEX idx_tmp_wn_node_id ON _tmp_wn (node_id)", $db1);
query("ANALYZE _tmp_wn", $db1);

query("DROP TABLE IF EXISTS _tmp_xings", $db1);
query("
	CREATE TABLE _tmp_xings AS
	SELECT wn1.way_id as way1, wn2.way_id as way2, wn1.x, wn1.y
	FROM _tmp_wn wn1 INNER JOIN _tmp_wn wn2 USING (node_id)
	WHERE wn1.way_id<wn2.way_id
", $db1);
query("DROP TABLE IF EXISTS _tmp_wn", $db1);
query("CREATE INDEX idx_tmp_xings ON _tmp_xings (way1, way2)", $db1);
query("ANALYZE _tmp_xings", $db1);



// collect colliding ways here and check if they really are errors afterwards
query("DROP TABLE IF EXISTS _tmp_error_candidates", $db1);
query("
	CREATE TABLE _tmp_error_candidates (
	way_id1 bigint NOT NULL,
	way_id2 bigint NOT NULL,
	geom text NOT NULL,
	typ1 text NOT NULL,
	typ2 text NOT NULL,
	action text NOT NULL
	)
", $db1);

// find ways that graphically intersect (i.e. cross or overlap)
// intersecting is not an error if ways share a common node; this will be checked later
// check every way with every other way but don't check way A with B AND B with A
// leads to "w1.way_id<w2.way_id"

// ignore crossings/overlappings of a riverbank with the river itself (there are thousands!)
// ignore crossings/overlappings of a riverbanks with each other (there are thousands too!)
query("
	INSERT INTO _tmp_error_candidates
	SELECT w1.way_id as way_id1, w2.way_id as way_id2, asText(ST_intersection(w1.geom, w2.geom)) AS geom, w1.way_type as typ1, w2.way_type as typ2,
	CASE WHEN ST_crosses(w1.geom, w2.geom) THEN 'crosses' ELSE 'overlaps' END as action
	FROM _tmp_ways w1, _tmp_ways w2
	WHERE w1.layer=w2.layer AND
		w1.way_id>w2.way_id AND NOT (
			(w1.way_type='waterway' AND w2.way_type='riverbank') OR
			(w1.way_type='riverbank' AND w2.way_type='waterway') OR
			(w1.way_type='riverbank' AND w2.way_type='riverbank')
		) AND (ST_crosses(w1.geom, w2.geom) OR ST_overlaps(w1.geom, w2.geom))
", $db1);

// ST_intersection() may return one of the following geometry types:
// POINT() for one single intersection point
// MULTIPOINT() if ways intersect in more than one spot
// LINESTRING() if ways overlap (ie. segments running on the same line)
// MULTILINESTRING() if more than one overlapping occurs
// GEOMETRYCOLLECTION() if a combination of the above is found
// This geometry is a container for different sub-geometries of above types.

$result=query("SELECT * FROM _tmp_error_candidates WHERE action='crosses'", $db1);
while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

	$points = get_startingpoints($row['geom']);

	foreach ($points as $point)
		if (!connected_near($row['way_id1'], $row['way_id2'], $point[0], $point[1], $db2)) {

			$additivum = subtype_number($row['typ1'], $row['typ2']);
			if ($additivum <> -1)

				// the second sentence "but there is no junction node" is only valid for
				// intersections of objects of the same kind: e.g. highway-highway
				// but not highway-waterway. It were contraproductive if the error message
				// led someone to add a junction node between a waterway and a highway
				// instead of adding a bridge

				query("
					INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, txt3, last_checked, lon, lat)
					VALUES($error_type+$additivum, CAST('way' AS type_object_type), {$row['way_id1']},
					'This $1 intersects the $2 #$3' ||
					CASE WHEN $additivum IN(1, 4, 5, 6) THEN ' but there is no junction node' ELSE '' END,
					'{$row['typ1']}', '{$row['typ2']}', '{$row['way_id2']}', NOW()," .
					round(1e7*merc_lon($point[0])) . ',' . round(1e7*merc_lat($point[1])) . ')'
				, $db2, false);
		}
}
pg_free_result($result);



// now look for overlapping ways
// that is ways that (partly) use the same sequences of nodes.
// Such segments lie on top of each other and are not covered
// by the intersections-test above
$result=query("SELECT * FROM _tmp_error_candidates WHERE action='overlaps'", $db1);
while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

	$points = get_startingpoints($row['geom']);
	$point = $points[0];

	$additivum = subtype_number($row['typ1'], $row['typ2']);
	if ($additivum <> -1)
		query("
			INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, txt3, last_checked, lon, lat)
			VALUES($error_type+10+$additivum, CAST('way' AS type_object_type), {$row['way_id1']},
			'This $1 overlaps the $2 #$3', '{$row['typ1']}', '{$row['typ2']}', '{$row['way_id2']}', NOW()," .
			1e7*merc_lon($point[0]) . ',' . 1e7*merc_lat($point[1]) . ')'
		, $db2, false);
}
pg_free_result($result);


print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_error_candidates", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("DROP TABLE IF EXISTS _tmp_xings", $db1, false);
query("DROP TYPE type_way_type", $db1, false);





function get_startingpoints($geom) {
	$result=array();
	$matches=array();

	// match all POINT() or MULTIPOINT() features and capture the content of parentheses
	// which may be: "x1 y1,x2 y2" and has to be split further
	// find and store any point in $result
	preg_match_all('@POINT\(([0-9., -]+)\)@', $geom, $matches);

	foreach ($matches[1] as $part) {	// a $part may contain many points separated by ','
		$points = explode(',', $part);
		foreach ($points as $point) {	// a point contains x and y separated by a space
			list($x,$y) = explode(' ', $point);
			$result[]=array($x, $y);
		}
	}

	// match all LINESTRING() or MULTILINESTRING() features
	// and capture just coordinates of the first node of each linestring
	$matches=array();
	preg_match_all('@LINESTRING\({1,2}([0-9., ()-]+)\){1,2}@', $geom, $matches);
	foreach ($matches[1] as $part) {
		$strings = explode ('),(', $part);
		foreach ($strings as $string) {
			$endpos=strpos($string, ',');
			$centerpos=strpos($string,' ');
			$result[]=array(substr($string, 0, $centerpos), substr($string, $centerpos+1, $endpos-$centerpos-1));
		}
	}

	return $result;
}


// find out if way_id1 and way_id2 are connected near the point (x y)
// i.e. they have a common node near (x y)
// we allow a distance of up to 10 meters for rounding errors
function connected_near($way_id1, $way_id2, $x, $y, $db) {

	return query_firstval("
		SELECT COUNT(*)
		FROM _tmp_xings
		WHERE way1=$way_id1 AND way2=$way_id2
                AND (x-($x)) ^ 2 + (y-($y)) ^ 2 <= 100
	", $db, false);

}


// highway-highway: 1
// highway-waterway: 2
// highway-riverbank: 3
// waterway-waterway: 4
// cycleway/footpath-cycleway/footpath: 5
// highway-cycleway/footpath: 6
// cycleway/footpath-waterway: 7
// cycleway/footpath-riverbank: 8
// any other: invalid (-1)
function subtype_number($type1, $type2) {

	// intentionally omitting break statements here because return exits the funtion
	switch ($type1) {
		case 'highway':
			switch ($type2) {
				case 'highway':
					return 1;
				case 'waterway':
					return 2;
				case 'riverbank':
					return 3;
				case 'cycleway/footpath':
					return 6;
			}
		case 'cycleway/footpath':
			switch ($type2) {
				case 'highway':
					return 6;
				case 'waterway':
					return 7;
				case 'riverbank':
					return 8;
				case 'cycleway/footpath':
					return 5;
			}
		case 'waterway':
			switch ($type2) {
				case 'highway':
					return 2;
				case 'waterway':
					return 4;
				case 'riverbank':
					return -1;
				case 'cycleway/footpath':
					return 7;
			}
		case 'riverbank':
			switch ($type2) {
				case 'highway':
					return 3;
				case 'waterway':
					return -1;
				case 'riverbank':
					return -1;
				case 'cycleway/footpath':
					return 8;
			}
	}
	// something terrible must have happened
	echo "cannot assign an error type to a junction of $type1 and $type2\n";
	return -1;
}
?>
