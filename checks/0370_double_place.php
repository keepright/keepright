<?php


/*

-------------------------------------
-- physical tag given on a node and on an area that includes the node
-------------------------------------

doubles labels on the map

*/


query("DROP TABLE IF EXISTS _tmp_ways", $db1);

// select all ways that are closed-loop
// check "closed loop" state in a relaxed way; distance
// between first and last node may be up to 10 meters
// ensure the linestring is closed and convert it into a polygon
// ST_AddPoint(geom, ST_StartPoint(geom)) adds the first poin to the end
// of the linestring and thus closes it
query("
	SELECT id, ST_MakePolygon(ST_AddPoint(geom, ST_StartPoint(geom))) AS geom
	INTO _tmp_ways
	FROM ways
	WHERE node_count>2 AND
		POWER(first_node_x-last_node_x, 2) + POWER(first_node_y-last_node_y, 2) < 100
", $db1);

query("CREATE INDEX idx_tmp_ways_geom ON _tmp_ways USING gist (geom)", $db1);
query("ALTER TABLE _tmp_ways ADD PRIMARY KEY (id);", $db1);
query("ANALYZE _tmp_ways", $db1);



// include nodes that have some tags and
// are not member of any way
// the latter avoids a roundabout containing the nodes building
// the boundary
query("DROP TABLE IF EXISTS _tmp_nodes", $db1);
query("
	SELECT id, geom
	INTO _tmp_nodes
	FROM nodes n
	WHERE EXISTS (
		SELECT node_id
		FROM node_tags nt
		WHERE n.id=nt.node_id
	) AND NOT EXISTS (
		SELECT node_id
		FROM way_nodes wn
		WHERE n.id=wn.node_id
	)
", $db1);

query("CREATE INDEX idx_tmp_nodes_geom ON _tmp_nodes USING gist (geom)", $db1);
query("ALTER TABLE _tmp_nodes ADD PRIMARY KEY (id);", $db1);
query("ANALYZE _tmp_nodes", $db1);



query("DROP TABLE IF EXISTS _tmp_inclusions", $db1);

// select all ways that contain nodes
query("
	SELECT w.id as way_id, n.id as node_id
	INTO _tmp_inclusions
	FROM _tmp_ways AS w INNER JOIN _tmp_nodes n ON
		ST_Within(n.geom, w.geom)

", $db1);


query("CREATE INDEX idx_tmp_inclusions_w ON _tmp_inclusions (way_id)", $db1);
query("CREATE INDEX idx_tmp_inclusions_n ON _tmp_inclusions (node_id)", $db1);
query("ANALYZE _tmp_inclusions", $db1);




// keys that represent physical entities
// restriced to entities that have 2-dimensional extents in most cases
// i.e. NOT highways, phones etc
// the place tag is an exception: according to wiki it is valid use to provide
// a place tag on a node AND on a surrounding way. So the place tag is omitted here
$keylist = "'abutters', 'aerialway', 'aeroway', 'agricultural', 'amenity', 'area', 'barrier', 'basin',  'boundary', 'brewery', 'bridge', 'building', 'club', 'craft', 'emergency', 'ford', 'fuel', 'habitat', 'harbour', 'healthcare', 'historic', 'landmark', 'landuse', 'leisure', 'location', 'man made', 'mooring', 'natural', 'parking', 'playground', 'power', 'railway', 'repair', 'reservation', 'resource', 'route', 'ruins', 'school', 'service', 'shelter', 'shop', 'sport', 'tourism', 'tunnel', 'water', 'waterway', 'wood', 'zoo'";


// outer part: select from inner part where way as well as node
// are tagged with the same name.
// inner part:
// select way/node pairs with common key/value pairs
// where key is on the list from above, thus representing some
// physical 2D entity
query("

	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
	SELECT $error_type, 'node', T.node_id, 'This node has tags in common with the surrounding way #$1 (including the name \'$2\') and seems to be redundand', T.way_id, wt2.v, NOW()

	FROM (
		SELECT i.*
		FROM _tmp_inclusions i
		WHERE EXISTS (
			SELECT 1
			FROM way_tags wt
			WHERE wt.way_id=i.way_id AND
			wt.k in ($keylist) AND
			EXISTS (
				SELECT 1
				FROM node_tags nt
				WHERE nt.node_id=i.node_id AND
				nt.k=wt.k AND
				nt.v=wt.v
			)
		)
	) AS T

	INNER JOIN way_tags wt2 USING (way_id)
	INNER JOIN node_tags nt2 USING (node_id)
	WHERE wt2.k='name' AND
		nt2.k='name' AND
		wt2.v=nt2.v
", $db1);



// query("DROP TABLE IF EXISTS _tmp_ways", $db1);
// query("DROP TABLE IF EXISTS _tmp_nodes", $db1);
// query("DROP TABLE IF EXISTS _tmp_inclusions", $db1);

?>