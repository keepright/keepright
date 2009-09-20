<?php

/*
build up a table containing multipolygons for administrative boundaries


there are three major ways to describe boundaries in OSM:
* "old": special tagging of ways with boundary=administrative and left:country=... and right:country=...
* "new 1": using relations of type=boundary to tie boundary-ways together
* "new 2": using relations of type=multipolygon to tie boundary-ways together

All three shall be considered to find as many boundary-ways as possible.

The process is divided in three parts:
* find all ways that are part of boundaries
* put the ways in appropriate order to build closed loops if possible
* convert lists of ways into (multi)polygons and store them as PostGIS geometry


How to use the resulting boundaries table:

This statement will find all boundaries, a given point lies within
(include the ST_Touches() function to include checking for points lying
exactly on the boundary. This will obviously return both adjacent
countries for points lying on the boundary).
In general this query will return more than row; one for each admin level.

SELECT name, admin_level
FROM _tmp_boundaries
WHERE ST_Touches(GeomFromText('POINT(48.0843 16.2975)', 4326), geom) OR
ST_Within(GeomFromText('POINT(48.0843 16.2975)', 4326), geom)


*/


if ($argc<>2) {
	echo "Usage: \">php prepare_countries.php AT\"\n";
	echo "will create a helper table containing country borders.\n";
	echo "database credentials are configured in config file\n";
	exit;
}

require('config.inc.php');
require('helpers.inc.php');
require('BufferedInserter.php');


echo "Creating country borders table for $db_postfix \n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db2 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);

//--------------------------------------------------
create_postgres_functions($db1);



////////////////////////////////////////////
// find relations tagged as boundary
////////////////////////////////////////////


// table for relations that tie ways together to build administrative boundaries
query("DROP TABLE IF EXISTS _tmp_border_relations", $db1);
query("
	CREATE TABLE _tmp_border_relations (
	relation_id bigint,
	admin_level text,
	name text
	)
", $db1);


// fetch relations with 'boundary=administrative' tags
// there is a "boundary"-relation as well as a "multipolygon"-relation
// both are in use
query("
	INSERT INTO _tmp_border_relations
	SELECT t.relation_id
	FROM relation_tags t
	WHERE t.k='type' AND
	t.v IN ('boundary', 'multipolygon') AND
	EXISTS(
		SELECT t3.v
		FROM relation_tags t3
		WHERE t.relation_id=t3.relation_id
		AND t3.k='boundary' AND t3.v='administrative'
	)
", $db1);
query("CREATE INDEX idx_tmp_border_relations_relation_id ON _tmp_border_relations (relation_id)", $db1, false);
query("ANALYZE _tmp_border_relations", $db1);

// fetch name of boundary
query("
	UPDATE _tmp_border_relations c
	SET name=v
	FROM relation_tags t WHERE t.relation_id=c.relation_id AND t.k='name'
", $db1);

// fetch admin level
query("
	UPDATE _tmp_border_relations c
	SET admin_level=v
	FROM relation_tags t WHERE t.relation_id=c.relation_id AND t.k='admin_level'
", $db1);





// table for all the way segments building boundaries
// ways covered by relations as well as ways not member of relations
// but tagged as boundary will be collected here
query("DROP TABLE IF EXISTS _tmp_border_ways", $db1);
query("
	CREATE TABLE _tmp_border_ways (
	name text,
	admin_level text,
	relation_id bigint,
	way_id bigint,
	first_node_id bigint,
	last_node_id bigint,
	part integer,
	sequence_id integer,
	direction smallint
	)
", $db1);


////////////////////////////////////////////
// now add ways not member of relations but tagged as boundary
////////////////////////////////////////////


// take this list of keywords to filter administrative boundaries of interest
// out of way-tags. Consider both (left: and right:) kinds of tagging
$items=array(
'city', 'village', 'departement', 'county', 'region', 'country', 'border', 'district', 'commune', 'parish', 'state', 'suburb', 'town', 'municipality', 'province', 'governate', 'arrondissement', 'borough', 'diocese', 'unitary', 'prefecture'
);
$sql='';
foreach ($items as $item) $sql.=", 'left:$item', 'right:$item'";

query("
	INSERT INTO _tmp_border_ways (name, way_id)
	SELECT wt.v, wt.way_id
	FROM way_tags wt
	WHERE wt.k IN (" . substr($sql,2) . ") AND
	EXISTS(
		SELECT tmp.v
		FROM way_tags tmp
		WHERE tmp.way_id=wt.way_id AND tmp.k='boundary' AND tmp.v='administrative'
	)
", $db1);
query("CREATE INDEX idx_tmp_border_ways_way_id ON _tmp_border_ways (way_id)", $db1, false);
query("ANALYZE _tmp_border_ways", $db1);

// fetch admin level
query("
	UPDATE _tmp_border_ways c
	SET admin_level=v
	FROM way_tags t
	WHERE t.way_id=c.way_id AND t.k='admin_level'
", $db1);


// now add ways found as member of relations
query("
	INSERT INTO _tmp_border_ways
	SELECT br.name, br.admin_level, br.relation_id, rm.member_id
	FROM (_tmp_border_relations br INNER JOIN relation_members rm USING (relation_id))
	WHERE rm.member_type=2
", $db1);

query("CREATE INDEX idx_tmp_border_ways_relation_id ON _tmp_border_ways (relation_id)", $db1, false);
query("CREATE INDEX idx_tmp_border_ways_name ON _tmp_border_ways (name)", $db1, false);
query("CREATE INDEX idx_tmp_border_ways_admin_level ON _tmp_border_ways (admin_level)", $db1, false);
query("CREATE INDEX idx_tmp_border_ways_part ON _tmp_border_ways (part)", $db1, false);
query("ANALYZE _tmp_border_ways", $db1);


// some ways are already part of a relation but are still tagged as boundary
// themselves. We don't need the standalone ways, so delete them
// and keep the relation members.
query("
	DELETE FROM _tmp_border_ways bw
	USING _tmp_border_ways tmp
	WHERE bw.name=tmp.name AND bw.admin_level=tmp.admin_level AND bw.way_id=tmp.way_id AND
	bw.relation_id IS NULL AND tmp.relation_id IS NOT NULL
", $db1);


// add first and last node ids
query("
	UPDATE _tmp_border_ways c
	SET first_node_id=w.first_node_id, last_node_id=w.last_node_id
	FROM ways w
	WHERE c.way_id=w.id
", $db1);





////////////////////////////////////////////
// order the pieces of ways to form closed lines
////////////////////////////////////////////


query("CREATE INDEX idx_tmp_border_ways_sequence_id ON _tmp_border_ways (sequence_id)", $db1, false);
query("ANALYZE _tmp_border_ways", $db1);

// a boundary may consist of several "parts" that are not connected.
// Just think of France: There's the "main land" and several exclaves
// as well as some islands.
// multiple parts are ok as long as every part is closed-loop

// algorithm in short:
// * start anywhere (lowest way_id)
// * find another way that has first_node equal to actual last node ("forward")
// * find another way that has first_node equal to actual first node ("backward")
// * repeat last 2 steps with reversed ways. Direction doesn't matter!
// * if no more ways can be found, all connectable parts are connected.
// * start at the beginning with picking another random way that isn't already ordered
//   increase the part number
//   repeat all this as long as at least one new way could be added

$part=0;
do {
	// pick a "random" part of each boundary as starting way.
	// let's take the lowest way id
	query("DROP TABLE IF EXISTS _tmp_tmp", $db1, false);
	query("
		CREATE TABLE _tmp_tmp AS
		SELECT name, admin_level, MIN(way_id) AS way_id
		FROM _tmp_border_ways
		WHERE sequence_id IS NULL
		GROUP BY name, admin_level
	", $db1, false);

	$result=query("
		UPDATE _tmp_border_ways c
		SET part=$part, sequence_id=0, direction=1
		FROM _tmp_tmp T
		WHERE c.way_id=T.way_id AND T.name=c.name AND T.admin_level=c.admin_level
	", $db1, false);

	$count_newpart=pg_affected_rows($result);
	//echo "part #$part: found $count_newpart starting ways.\n";

	query("DROP TABLE IF EXISTS _tmp_tmp", $db1, false);


	$loop=1;
	do {

		// try to find ways that are connected to the most recently
		// found segment's ends.

		// consider four cases:

		// 1) way B starting at the end of way A; the simplest case:
		//	o---->---way-A--->----o---->--way-B--->---o

		// 2) way B starting at the end of way A but B has wrong direction:
		//	o---->---way-A--->----o----<--way-B---<---o


		// 3) way B ending at the beginning of way A; Considering this case
		//    is important if the randomly picked starting segment lies in
		//    the middle of a non-closed loop. Otherwise you wouldn't ever find
		//    the segments to the left of the starting way.
		//    Way B gets a negative sequence_id
		//	o---->--way-B--->---o---->---way-A--->----o

		// 4) same as 3) but B has wrong direction. Way B gets a negative sequence_id
		//	o----<--way-B---<---o---->---way-A--->----o


		// T1 means: newly found way. T0 means: way found in the last step

		// 1) find next way id in sequence in forward direction
		// we have to use the "other end" of the previous way if its direction was negative
		$result=query("
			UPDATE _tmp_border_ways T1
			SET part=$part, sequence_id=$loop, direction=1
			FROM _tmp_border_ways T0
			WHERE T1.name=T0.name AND T1.admin_level=T0.admin_level AND
			T1.sequence_id IS NULL AND T0.sequence_id=$loop-1 AND
			CASE WHEN COALESCE(T0.direction,1)=1 THEN
				T1.first_node_id=T0.last_node_id
			ELSE
				T1.first_node_id=T0.first_node_id
			END
		", $db1, false);
		$count_forward_straight=pg_affected_rows($result);

		// 2) find next way id in sequence in forward direction, way reversed
		$result=query("
			UPDATE _tmp_border_ways T1
			SET part=$part, sequence_id=$loop, direction=-1
			FROM _tmp_border_ways T0
			WHERE T1.name=T0.name AND T1.admin_level=T0.admin_level AND
			T1.sequence_id IS NULL AND T0.sequence_id=$loop-1 AND
			CASE WHEN COALESCE(T0.direction,1)=1 THEN
				T1.last_node_id=T0.last_node_id
			ELSE
				T1.last_node_id=T0.first_node_id
			END
		", $db1, false);
		$count_forward_reversed=pg_affected_rows($result);


		//echo "found $count_forward_straight ways and $count_forward_reversed reversed in forward direction\n";


		// 3) find next way id in sequence in backward direction
		$result=query("
			UPDATE _tmp_border_ways T1
			SET part=$part, sequence_id=-$loop, direction=1
			FROM _tmp_border_ways T0
			WHERE T1.name=T0.name AND T1.admin_level=T0.admin_level AND
			T1.sequence_id IS NULL AND T0.sequence_id=-$loop+1 AND
			CASE WHEN COALESCE(T0.direction,1)=1 THEN
				T1.last_node_id=T0.first_node_id
			ELSE
				T1.last_node_id=T0.last_node_id
			END
		", $db1, false);
		$count_backward_straight=pg_affected_rows($result);

		// 4) find next way id in sequence in backward direction, way reversed
		$result=query("
			UPDATE _tmp_border_ways T1
			SET part=$part, sequence_id=-$loop, direction=-1
			FROM _tmp_border_ways T0
			WHERE T1.name=T0.name AND T1.admin_level=T0.admin_level AND
			T1.sequence_id IS NULL AND T0.sequence_id=-$loop+1 AND
			CASE WHEN COALESCE(T0.direction,1)=1 THEN
				T1.first_node_id=T0.first_node_id
			ELSE
				T1.first_node_id=T0.last_node_id
			END
		", $db1, false);
		$count_backward_reversed=pg_affected_rows($result);


		//echo "found $count_backward_straight ways and $count_backward_reversed reversed in backward direction\n";


		$loop++;
	} while ($count_forward_straight+$count_forward_reversed+$count_backward_straight+$count_backward_reversed>0);

	$part++;
} while ($count_newpart>0);

query("ANALYZE _tmp_border_ways", $db1);


////////////////////////////////////////////
// finally build table of boundary-(multi)polygons
////////////////////////////////////////////

query("DROP TABLE IF EXISTS _tmp_boundaries", $db1);
$result=query("
	CREATE TABLE _tmp_boundaries AS
	SELECT name, admin_level, 0 as nodecount
	FROM _tmp_border_ways
	GROUP BY name, admin_level
", $db1);
query("SELECT AddGeometryColumn('_tmp_boundaries', 'geom', 4326, 'MULTIPOLYGON', 2)", $db1);
query("ALTER TABLE _tmp_boundaries ADD t text", $db1);


// _tmp_nodelists will hold the lat lon values as text for every node
// eg. "-1 -1,-1 -2,-2 -2,-2 -1,-1 -1"
query("DROP TABLE IF EXISTS _tmp_nodelists", $db1);
$result=query("
	CREATE TABLE _tmp_nodelists(
		way_id bigint,
		first_node_id bigint,
		last_node_id bigint,
		direction smallint,
		nodelist text,
		nodecount integer
	)
", $db1);

// insert way_ids
$result=query("
	INSERT INTO _tmp_nodelists
	SELECT way_id, first_node_id, last_node_id, direction
	FROM _tmp_border_ways b
	GROUP BY way_id, first_node_id, last_node_id, direction
", $db1);

// fetch number of nodes
query("UPDATE _tmp_nodelists t
	SET nodecount=(
		SELECT COUNT(node_id)
		FROM way_nodes wn
		WHERE wn.way_id=t.way_id
	)
", $db1);

query("CREATE INDEX idx_tmp_nodelists_way_id_direction ON _tmp_nodelists (way_id, direction)", $db1, false);
query("ANALYZE _tmp_nodelists", $db1);

// fetch sums of nodes for each boundary
query("UPDATE _tmp_boundaries b
	SET nodecount=(
		SELECT SUM(nodecount)
		FROM _tmp_border_ways t INNER JOIN _tmp_nodelists n USING (way_id, direction)
		WHERE b.name=t.name AND b.admin_level=t.admin_level
	)
", $db1);

// fetch nodelist as text
query("UPDATE _tmp_nodelists t
	SET nodelist=array_to_string(array(
		SELECT wn.lat || ' ' || wn.lon
		FROM way_nodes wn
		WHERE wn.way_id=t.way_id
		ORDER BY t.direction*wn.sequence_id

	), ',')
", $db1);


// build the multipolgon data structure


//MULTIPOLYGON(((0 0,4 0,4 4,0 4,0 0),(1 1,2 1,2 2,1 2,1 1)), ((-1 -1,-1 -2,-2 -2,-2 -1,-1 -1)))
// the first part of the multipolygon has a hole, the second is just a polygon

// the mpoly consists of all the parts of the boundary. Each part consists of
// multiple ways and their nodes. To make each part a closed loop, add the
// node coordinates of the first (last if it has opposite direction) node of
// the first way of this part.
// this can save "almost closed" polys but it can destroy polys if the closing
// line intersects any other segment of the polygon. These have to be dropped
// afterwards.
/*
query("UPDATE _tmp_boundaries t
	SET geom=ST_MPolyFromText( 'MULTIPOLYGON(' || array_to_string(array(

		SELECT '((' || array_to_string(array(

			SELECT nodelist
			FROM _tmp_border_ways b2 INNER JOIN _tmp_nodelists n USING (way_id, direction)
			WHERE b2.name=t.name AND b2.admin_level=t.admin_level AND b2.part=b.part
			ORDER BY b2.sequence_id

		), ',') || ',' || array_to_string(array(

			SELECT n.lat || ' ' || n.lon
			FROM _tmp_border_ways b3 INNER JOIN nodes n
			ON ((b3.direction=1 AND n.id=b3.first_node_id) OR
			(b3.direction=-1 AND n.id=b3.last_node_id))
			WHERE b3.name=t.name AND b3.admin_level=t.admin_level AND
			b3.part=b.part
			ORDER BY b3.sequence_id
			LIMIT 1

			), ',')
		|| '))'
		FROM _tmp_border_ways b
		WHERE b.name=t.name AND b.admin_level=t.admin_level
		GROUP BY b.part
		ORDER BY b.part

	), ',')

	|| ')',4326)

	WHERE nodecount>2
", $db1);
*/

query("UPDATE _tmp_boundaries t
	SET t='MULTIPOLYGON(' || array_to_string(array(

		SELECT '((' || array_to_string(array(

			SELECT nodelist
			FROM _tmp_border_ways b2 INNER JOIN _tmp_nodelists n USING (way_id, direction)
			WHERE b2.name=t.name AND b2.admin_level=t.admin_level AND b2.part=b.part
			ORDER BY b2.sequence_id

		), ',') || ',' || array_to_string(array(

			SELECT n.lat || ' ' || n.lon
			FROM _tmp_border_ways b3 INNER JOIN nodes n
			ON ((b3.direction=1 AND n.id=b3.first_node_id) OR
			(b3.direction=-1 AND n.id=b3.last_node_id))
			WHERE b3.name=t.name AND b3.admin_level=t.admin_level AND
			b3.part=b.part
			ORDER BY b3.sequence_id
			LIMIT 1

			), ',')
		|| '))'
		FROM _tmp_border_ways b
		WHERE b.name=t.name AND b.admin_level=t.admin_level
		GROUP BY b.part
		ORDER BY b.part

	), ',')

	|| ')'

	WHERE nodecount>2
", $db1);


// this is ugly: doing an UPDATE t SET geom = GeomFromText('...')
// will stop and rollback if even one single geometry is non-closed
// so we have to do it one.by.one for every row:

$result=query("
	SELECT name, admin_level
	FROM _tmp_boundaries
", $db1);

while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

	//echo "name='" . $row['name'] . "' admin_level='" . $row['admin_level'] . "\n";
	query("
		UPDATE _tmp_boundaries t
		SET geom=ST_MPolyFromText(t, 4326)
		WHERE name='" . addslashes($row['name']) . "' AND admin_level='" . $row['admin_level'] . "'
	", $db2, false);
}
pg_free_result($result);



// delete invalid geometries
// invalid geometries are self-intersecting ones
query("
	UPDATE _tmp_boundaries t
	SET geom=NULL
	WHERE NOT ST_IsValid(geom)
", $db1);


//query("DROP TABLE IF EXISTS _tmp_border_relations", $db1);
//query("DROP TABLE IF EXISTS _tmp_nodelists", $db1);

//--------------------------------------------------
drop_postgres_functions($db1);

pg_close($db1);
pg_close($db2);

?>