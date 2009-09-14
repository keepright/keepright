<?php


if ($argc<>2) {
	echo "Usage: \">php prepare_helpertables.php AT\"\n";
	echo "will create helper tables used by checks on the database for Austria.\n";
	echo "database credentials are configured in config file\n";
	exit;
}

require('config.inc.php');
require('helpers.inc.php');
require('BufferedInserter.php');


echo "Creating helper tables for $db_postfix \n";

$db1 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);
$db2 = pg_pconnect($connectstring, PGSQL_CONNECT_FORCE_NEW);

$starttime=microtime(true);

//--------------------------------------------------
create_postgres_functions($db1);

// ways crossing the boundary of export bounding box get truncated
// ie in way_nodes you find node ids that are missing in nodes
// these are fetched here via api
/*
echo "retrieve missing nodes via api\n";
$count=0;
$result = query("
	SELECT DISTINCT node_id
	FROM way_nodes
	WHERE lat IS NULL
", $db1);
while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

	if ($response=file_get_contents("http://www.openstreetmap.org/api/0.6/node/" . $row['node_id'])) {
		$xml = new SimpleXMLElement($response);

		foreach ($xml->xpath('//node') as $node) {

			// add node
			query("INSERT INTO nodes(id, lat, lon, tstamp, user_name) VALUES (" . addslashes($node['id']) . ", " . addslashes($node['lat']) . ", " . addslashes($node['lon']) . ", '" . addslashes($node['timestamp']) . "', '" . addslashes($node['user']) . "')", $db2, false);


			// add tags of node
			foreach ($node->xpath('tag') as $tag) {

				query("INSERT INTO node_tags(node_id, k, v) VALUES (" . addslashes($node['id']) . ", '" . addslashes($tag['k']) . "', '" . addslashes($tag['v']) . "')", $db2, false);

			}
			$count++;
		}
	}
}
pg_free_result($result);
echo "fetched $count nodes via api.\n";
*/
//--------------------------------------------------




echo "add indexes to tags\n";
query("DELETE FROM way_tags WHERE v IS NULL", $db1);
query("DELETE FROM node_tags WHERE v IS NULL", $db1);

//Index for keys and values
query("CREATE INDEX idx_node_tags_k ON node_tags (k)", $db1);
query("CREATE INDEX idx_node_tags_v ON node_tags (v)", $db1);

query("CREATE INDEX idx_way_tags_k ON way_tags (k)", $db1);
query("CREATE INDEX idx_way_tags_v ON way_tags (v)", $db1);

query("CREATE INDEX idx_relation_tags_k ON relation_tags (k)", $db1);
query("CREATE INDEX idx_relation_tags_v ON relation_tags (v)", $db1);


// calculate x/y coordinates for nodes
query("UPDATE nodes
	SET x=merc_x(nodes.lon), y=merc_y(nodes.lat)
	WHERE x IS NULL
", $db1);

// build point geometry for nodes
query("UPDATE nodes
	SET geom=GeomFromText('POINT(' || x || ' ' || y || ')', 4326)
	WHERE geom IS NULL
", $db1);

// copy lat/lon and x/y coordinates from nodes into way_nodes, where missing
query("UPDATE way_nodes
	SET lat=nodes.lat, lon=nodes.lon, x=merc_x(nodes.lon), y=merc_y(nodes.lat)
	FROM nodes
	WHERE way_nodes.lat IS NULL AND nodes.id=way_nodes.node_id
", $db1);

// now remove everything that could not be retrieved
query("DELETE FROM way_nodes WHERE lat IS NULL", $db1);


echo "expand way_nodes with node data\n";

// find node counts and update table ways
query("DROP TABLE IF EXISTS _tmp_nodecounts", $db1);
query("SELECT ways.id AS way_id, COUNT(*) AS cnt
	INTO _tmp_nodecounts
	FROM ways INNER JOIN way_nodes ON (ways.id=way_nodes.way_id)
	GROUP BY ways.id
", $db1);
query("CREATE INDEX idx_tmp_nodecounts_way_id ON _tmp_nodecounts (way_id)", $db1);
query("UPDATE ways
	SET node_count=nc.cnt
	FROM _tmp_nodecounts nc
	WHERE id=nc.way_id
", $db1);
query("DROP TABLE IF EXISTS _tmp_nodecounts", $db1);


// Add a postgis bounding box column used for indexing the location of the way.
// This will contain a bounding box surrounding the extremities of the way.
query("SELECT AddGeometryColumn('ways', 'bbox', 4326, 'GEOMETRY', 2)", $db1);
query("SELECT AddGeometryColumn('ways', 'geom', 4326, 'LINESTRING', 2)", $db1);


query("UPDATE ways
	SET geom=GeomFromText( 'LINESTRING(' || array_to_string(array(
		SELECT wn.x || ' ' || wn.y
		FROM way_nodes wn
		WHERE ways.id=wn.way_id
		ORDER BY wn.sequence_id), ',')
	|| ')',4326)
	WHERE geom IS NULL AND node_count>1
", $db1);

//Update the bbox column of the way table 
//so that is a little bit larger than the linestring	
query("UPDATE ways SET bbox = Expand(geom, 10) WHERE bbox IS NULL", $db1);

//Index the way bounding box column.
query("CREATE INDEX idx_ways_bbox ON ways USING gist (bbox)", $db1);


// copy lat/lon and x/y coordinates from first nodes into ways, where missing
query("UPDATE ways
	SET first_node_id=wn.node_id, first_node_lat=wn.lat, first_node_lon=wn.lon, first_node_x=wn.x, first_node_y=wn.y
	FROM way_nodes wn
	WHERE ways.first_node_id IS NULL AND wn.way_id=ways.id AND wn.sequence_id=0
", $db1);


// copy lat/lon and x/y coordinates from first nodes into ways, where missing
query("UPDATE ways
	SET last_node_id=wn.node_id, last_node_lat=wn.lat, last_node_lon=wn.lon, last_node_x=wn.x, last_node_y=wn.y
	FROM way_nodes wn
	WHERE ways.last_node_id IS NULL AND wn.way_id=ways.id AND wn.sequence_id=(
		SELECT MAX(tmp.sequence_id)
		FROM way_nodes tmp
		WHERE tmp.way_id=ways.id
	)
", $db1);


//Perform database maintenance due to large database changes.
query("VACUUM ANALYZE", $db1);

//--------------------------------------------------
drop_postgres_functions($db1);

pg_close($db1);
pg_close($db2);

?>
