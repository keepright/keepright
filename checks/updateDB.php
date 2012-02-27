<?php


require('prepare_helpertables.php');
require('prepare_countries.php');



// updateDB asserts a populated database ready for running checks
function updateDB($schema) {

	planet_update($schema);

	emptyDB($schema);

	loadDB($schema);

	prepare_helpertables($schema);

	prepare_countries($schema);

}


function emptyDB($schema) {
	global $config;

	$db = pg_pconnect(connectstring($schema));

	query('TRUNCATE relation_tags', $db);
	query('TRUNCATE relation_members', $db);
	query('TRUNCATE relations', $db);
	query('TRUNCATE way_nodes', $db);
	query('TRUNCATE way_tags', $db);
	query('TRUNCATE ways', $db);
	query('TRUNCATE node_tags', $db);
	query('TRUNCATE nodes', $db);
	//query('TRUNCATE users', $db);

	pg_close($db);
}



function loadDB($schema) {
	global $config;

	$db = pg_pconnect(connectstring($schema));


	// add SRID into spatial_ref_sys table
	query('DELETE FROM spatial_ref_sys WHERE srid = 900913;', $db);

	query('INSERT INTO spatial_ref_sys (srid, auth_name, auth_srid, srtext, proj4text) VALUES
	(900913,\'EPSG\',900913,\'PROJCS["WGS84 / Simple Mercator",GEOGCS["WGS 84",
	DATUM["WGS_1984",SPHEROID["WGS_1984", 6378137.0, 298.257223563]],PRIMEM["Greenwich", 0.0],
	UNIT["degree", 0.017453292519943295],AXIS["Longitude", EAST],AXIS["Latitude", NORTH]],
	PROJECTION["Mercator_1SP_Google"],PARAMETER["latitude_of_origin", 0.0],
	PARAMETER["central_meridian", 0.0],PARAMETER["scale_factor", 1.0],PARAMETER["false_easting", 0.0],
	PARAMETER["false_northing", 0.0],UNIT["m", 1.0],AXIS["x", EAST],
	AXIS["y", NORTH],AUTHORITY["EPSG","900913"]]\',
	\'+proj=merc +a=6378137 +b=6378137 +lat_ts=0.0 +lon_0=0.0 +x_0=0.0 +y_0=0 +k=1.0 +units=m +nadgrids=@null +no_defs\');', $db);


	// Import the table data from the data files using the fast COPY method.
	// replace temp-path for data files created by osmosis
	query("copy nodes FROM '" . $config['temp_dir'] . "nodes.txt' WITH NULL AS 'NULL';", $db);
	query("copy node_tags FROM '" . $config['temp_dir'] . "node_tags.txt' WITH NULL AS 'NULL';", $db);
	query("copy ways (id, user_name, tstamp, first_node_id, last_node_id, node_count) FROM '" . $config['temp_dir'] . "ways.txt' WITH NULL AS 'NULL';", $db);
	query("copy way_tags FROM '" . $config['temp_dir'] . "way_tags.txt' WITH NULL AS 'NULL';", $db);
	query("copy way_nodes (way_id, node_id, sequence_id) FROM '" . $config['temp_dir'] . "way_nodes.txt' WITH NULL AS 'NULL';", $db);
	query("copy relations FROM '" . $config['temp_dir'] . "relations.txt' WITH NULL AS 'NULL';", $db);
	query("copy relation_tags FROM '" . $config['temp_dir'] . "relation_tags.txt' WITH NULL AS 'NULL';", $db);
	query("copy relation_members FROM '" . $config['temp_dir'] . "relation_members.txt' WITH NULL AS 'NULL';", $db);

	// Add the primary keys and indexes back again (except the way bbox index).
	query('ALTER TABLE ONLY schema_info ADD CONSTRAINT pk_schema_info PRIMARY KEY (version);', $db, false);
	query('ALTER TABLE ONLY nodes ADD CONSTRAINT pk_nodes PRIMARY KEY (id);', $db, false);
	query('ALTER TABLE ONLY ways ADD CONSTRAINT pk_ways PRIMARY KEY (id);', $db, false);
	query('ALTER TABLE ONLY way_nodes ADD CONSTRAINT pk_way_nodes PRIMARY KEY (way_id, sequence_id);', $db, false);
	query('ALTER TABLE ONLY relations ADD CONSTRAINT pk_relations PRIMARY KEY (id);', $db, false);

	query('CREATE INDEX idx_node_tags_node_id ON node_tags USING btree (node_id);', $db);
	query('CREATE INDEX idx_nodes_geom ON nodes USING gist (geom);', $db);

	query('CREATE INDEX idx_way_tags_way_id ON way_tags USING btree (way_id);', $db);
	query('CREATE INDEX idx_way_nodes_node_id ON way_nodes USING btree (node_id);', $db);

	query('CREATE INDEX idx_relation_tags_relation_id ON relation_tags USING btree (relation_id);', $db, false);
	query('CREATE INDEX idx_relations_member_id ON relation_members USING btree (member_id);', $db, false);
	query('CREATE INDEX idx_relations_member_role ON relation_members USING btree (member_role);', $db, false);
	query('CREATE INDEX idx_relations_member_type ON relation_members USING btree (member_type);', $db, false);

	// Index for keys and values
	query('DELETE FROM way_tags WHERE v IS NULL;', $db, false);
	query('DELETE FROM node_tags WHERE v IS NULL;', $db, false);

	query('CREATE INDEX idx_node_tags_k ON node_tags (k);', $db);
	query('CREATE INDEX idx_node_tags_v ON node_tags (v);', $db);

	query('CREATE INDEX idx_way_tags_k ON way_tags (k);', $db);
	query('CREATE INDEX idx_way_tags_v ON way_tags (v);', $db);

	query('CREATE INDEX idx_relation_tags_k ON relation_tags (k);', $db);
	query('CREATE INDEX idx_relation_tags_v ON relation_tags (v);', $db);


	query("
		UPDATE way_nodes
		SET lat=nodes.lat, lon=nodes.lon, x=nodes.x, y=nodes.y
		FROM nodes
		WHERE nodes.id=way_nodes.node_id;
	", $db);

	query("
		UPDATE ways
		SET first_node_lat=nodes.lat, first_node_lon=nodes.lon, first_node_x=nodes.x, first_node_y=nodes.y
		FROM nodes
		WHERE nodes.id=ways.first_node_id;
	", $db);


	query("
		UPDATE ways
		SET last_node_lat=nodes.lat, last_node_lon=nodes.lon, last_node_x=nodes.x, last_node_y=nodes.y
		FROM nodes
		WHERE nodes.id=ways.last_node_id;
	", $db);
	pg_close($db);


	// eventually delete data files to save disk space
        if (!$config['keep_database_after_processing']) {

		unlink($config['temp_dir'] . 'nodes_sorted.txt');
		unlink($config['temp_dir'] . 'node_tags.txt');
		unlink($config['temp_dir'] . 'relation_members.txt');
		unlink($config['temp_dir'] . 'relations.txt');
		unlink($config['temp_dir'] . 'relation_tags.txt');
		unlink($config['temp_dir'] . 'users.txt');
		unlink($config['temp_dir'] . 'way_nodes2.txt');
		unlink($config['temp_dir'] . 'ways.txt');
		unlink($config['temp_dir'] . 'way_tags.txt');

	}
}

?>