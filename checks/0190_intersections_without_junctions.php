<?php

/*
-------------------------------------
-- intersections_without_junctions
-------------------------------------

find crossings of highways that are not represented by a common node

to avoid false positives on highways crossing squares, areas are excluded here

*/


if (!type_exists($db1, 'type_way_type'))
	query("CREATE TYPE type_way_type AS ENUM('highway','waterway','riverbank')", $db1);

// tmp_ways will contain all highways with their linestring geometry and layer tag
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways (
	way_id bigint NOT NULL,
	layer text,
	way_type type_way_type DEFAULT 'highway'
	)
", $db1);
query("SELECT AddGeometryColumn('_tmp_ways', 'geom', 4326, 'LINESTRING', 2)", $db1);

// find any highway-tagged way
query("
	INSERT INTO _tmp_ways (way_id, geom)
	SELECT DISTINCT id, geom
	FROM ways
	WHERE EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE wt.k = 'highway' AND wt.way_id=ways.id
	)
", $db1);

query("ALTER TABLE _tmp_ways ADD PRIMARY KEY (way_id);", $db1);
query("ANALYZE _tmp_ways", $db1);

// now add waterways but not riverbanks(docks)
query("
	INSERT INTO _tmp_ways (way_id, geom, way_type)
	SELECT DISTINCT id, geom, CAST('waterway' AS type_way_type)
	FROM ways
	WHERE EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE wt.k = 'waterway' AND wt.v NOT IN ('riverbank', 'dock') AND wt.way_id=ways.id
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
	SELECT DISTINCT id, geom, CAST('riverbank' AS type_way_type)
	FROM ways
	WHERE EXISTS (
		SELECT wt.v
		FROM way_tags wt
		WHERE ((wt.k = 'waterway' AND wt.v IN ('riverbank', 'dock')) OR
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
query("
	UPDATE _tmp_ways c
	SET layer=t.v
	FROM way_tags t
	WHERE t.way_id=c.way_id AND t.k='layer'
", $db1);

// set default layers:
// bridges have layer +1 (if no layer tag is given)
// tunnels have layer -1 (if no layer tag is given)
// anything else has layer 0 (if no layer tag is given)
query("
	UPDATE _tmp_ways c
	SET layer=1
	FROM way_tags t
	WHERE layer IS NULL AND
	t.way_id=c.way_id AND
	t.k='bridge'
", $db1);
query("
	UPDATE _tmp_ways c
	SET layer=-1
	FROM way_tags t
	WHERE layer IS NULL AND
	t.way_id=c.way_id AND
	t.k='tunnel'
", $db1);
query("
	UPDATE _tmp_ways c
	SET layer=0
	WHERE layer IS NULL
", $db1);

query("CREATE INDEX idx_tmp_ways_layer ON _tmp_ways (layer)", $db1);
query("CREATE INDEX idx_tmp_ways_way_type ON _tmp_ways (way_type)", $db1);
query("CREATE INDEX idx_tmp_ways_geom ON _tmp_ways USING gist (geom)", $db1);
query("ANALYZE _tmp_ways", $db1);


// create a helper table needed by connected_near() function
// all junctions of ways and the location of junction
// first include all crossings; remove crossings on ways not interesting
query("DROP TABLE IF EXISTS _tmp_xings", $db1);
query("
	CREATE TABLE _tmp_xings AS
	SELECT wn1.way_id as way1, wn2.way_id as way2, wn1.x, wn1.y
	FROM way_nodes wn1 INNER JOIN way_nodes wn2 USING (node_id)
", $db1);
query("CREATE INDEX idx_tmp_xings ON _tmp_xings (way1, way2)", $db1);
query("ANALYZE _tmp_xings", $db1);

query("
	DELETE FROM _tmp_xings
	WHERE NOT EXISTS (
		SELECT way_id FROM _tmp_ways w1 WHERE w1.way_id=_tmp_xings.way1
	) OR NOT EXISTS (
		SELECT way_id FROM _tmp_ways w2 WHERE w2.way_id=_tmp_xings.way2
	)
", $db1);




// ignore crossings/overlappings of a riverbank with the river itself (there are thousands!)
// ignore crossings/overlappings of a riverbanks with each other (there are thousands too!)
$waterway_exlusion="
	NOT ((w1.way_type='waterway' AND w2.way_type='riverbank') OR
	(w1.way_type='riverbank' AND w2.way_type='waterway') OR
	(w1.way_type='riverbank' AND w2.way_type='riverbank'))
";


// find ways that graphically intersect (i.e. cross or overlap)
// intersecting is not an error if ways share a common node; this will be checked later
// check every way with every other way but don't check way A with B AND B with A
// leads to "w1.way_id<w2.way_id"
$result=query("
	SELECT w1.way_id as way_id1, w2.way_id as way_id2, asText(ST_intersection(w1.geom, w2.geom)) AS geom, w1.way_type as typ1, w2.way_type as typ2
	FROM _tmp_ways w1, _tmp_ways w2
	WHERE w1.layer=w2.layer AND
		w1.way_id<w2.way_id AND
		$waterway_exlusion AND
		ST_crosses(w1.geom, w2.geom)
", $db1);

// ST_intersection() may return one of the following geometry types:
// POINT() for one single intersection point
// MULTIPOINT() if ways intersect in more than one spot
// LINESTRING() if ways overlap (ie. segments running on the same line)
// MULTILINESTRING() if more than one overlapping occurs
// GEOMETRYCOLLECTION() if a combination of the above is found
// This geometry is a container for different sub-geometries of above types.

while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

	$points = get_startingpoints($row['geom']);

	foreach ($points as $point)
		if (!connected_near($row['way_id1'], $row['way_id2'], $point[0], $point[1], $db2)) {

			$additivum = subtype_number($row['typ1'], $row['typ2']);
			if ($additivum <> -1)

				query("
					INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked, lon, lat)
					VALUES($error_type+$additivum, CAST('way' AS type_object_type), {$row['way_id1']},
					'This {$row['typ1']} intersects the {$row['typ2']} #' || {$row['way_id2']} || ' but there is no junction node', NOW()," . 
					round(1e7*merc_lon($point[0])) . ',' . round(1e7*merc_lat($point[1])) . ')'
				, $db2, false);

		}

}
pg_free_result($result);



// now look for overlapping ways
// that is ways that (partly) use the same sequences of nodes.
// Such segments lie on top of each other and are not covered
// by the intersections-test above

$result=query("
	SELECT w1.way_id as way_id1, w2.way_id as way_id2, asText(ST_intersection(w1.geom, w2.geom)) AS geom, w1.way_type as typ1, w2.way_type as typ2
	FROM _tmp_ways w1, _tmp_ways w2
	WHERE w1.layer=w2.layer AND
		w1.way_id<w2.way_id AND
		$waterway_exlusion AND
		ST_overlaps(w1.geom, w2.geom)
", $db1);

while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

	$points = get_startingpoints($row['geom']);
	$point = $points[0];

	$additivum = subtype_number($row['typ1'], $row['typ2']);
	if ($additivum <> -1)
		query("
			INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked, lon, lat) 
			VALUES($error_type+10+$additivum, CAST('way' AS type_object_type), {$row['way_id1']},
			'This {$row['typ1']} overlaps the {$row['typ2']} #' || {$row['way_id2']} || '.', NOW()," .
			1e7*merc_lon($point[0]) . ',' . 1e7*merc_lat($point[1]) . ')'
		, $db2, false);

}
pg_free_result($result);


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
// any other: invalid (-1)
function subtype_number($type1, $type2) {

	switch ($type1) {
		case 'highway':
			switch ($type2) {
				case 'highway':
					return 1;
				break;
				case 'waterway':
					return 2;
				break;
				case 'riverbank':
					return 3;
				break;
			}
		break;
		case 'waterway':
			switch ($type2) {
				case 'highway':
					return 2;
				break;
				case 'waterway':
					return 4;
				break;
				case 'riverbank':
					return -1;
				break;
			}
		break;
		case 'riverbank':
			switch ($type2) {
				case 'highway':
					return 3;
				break;
				case 'waterway':
					return -1;
				break;
				case 'riverbank':
					return -1;
				break;
			}
		break;
	}
	// something terrible must have happened
	echo "cannot assign an error type to a junction of $type1 and $type2\n";
	return -1;
}
?>
