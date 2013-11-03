<?php


require('prepare_helpertables.php');
require('prepare_countries.php');



// updateDB asserts a populated database ready for running checks
function updateDB($schema) {

	planet_update($schema);
	postprocess_datafiles($schema);

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
	query('TRUNCATE users', $db);

	pg_close($db);
}



function loadDB($schema) {
	global $config;
        $tmpdir = $config['temp_dir'].$schema."/"; 
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
	query("copy users FROM '" . $tmpdir . "users.txt' WITH NULL AS 'NULL';", $db);
	query("copy nodes FROM '" . $tmpdir . "nodes_sorted.txt' WITH NULL AS 'NULL';", $db);
	query("copy node_tags FROM '" . $tmpdir . "node_tags.txt' WITH NULL AS 'NULL';", $db);
	query("copy ways FROM '" . $tmpdir . "ways.txt' WITH NULL AS 'NULL';", $db);
	query("copy way_tags FROM '" . $tmpdir . "way_tags.txt' WITH NULL AS 'NULL';", $db);
	query("copy way_nodes FROM '" . $tmpdir . "way_nodes2.txt' WITH NULL AS 'NULL';", $db);
	query("copy relations FROM '" . $tmpdir . "relations.txt' WITH NULL AS 'NULL';", $db);
	query("copy relation_tags FROM '" . $tmpdir . "relation_tags.txt' WITH NULL AS 'NULL';", $db);
	query("copy relation_members FROM '" . $tmpdir . "relation_members.txt' WITH NULL AS 'NULL';", $db);


	// delete data files to save disk space
	unlink($tmpdir . 'nodes_sorted.txt');
	unlink($tmpdir . 'node_tags.txt');
	unlink($tmpdir . 'relation_members.txt');
	unlink($tmpdir . 'relations.txt');
	unlink($tmpdir . 'relation_tags.txt');
	unlink($tmpdir . 'users.txt');
	unlink($tmpdir . 'way_nodes2.txt');
	unlink($tmpdir . 'ways.txt');
	unlink($tmpdir . 'way_tags.txt');


	// Add the primary keys and indexes back again (except the way bbox index).
	query('ALTER TABLE ONLY schema_info ADD CONSTRAINT pk_schema_info PRIMARY KEY (version);', $db, false);
	query('ALTER TABLE ONLY users ADD CONSTRAINT pk_users PRIMARY KEY (id);', $db, false);
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

	pg_close($db);

}




// postprocessing on tab-separated data files created by osmosis before they
// can be inserted in postgres
// manually join table way_nodes and nodes to add node coordinates to way_nodes2
// manually join table ways with nodes to add node coordinates of first and last node to ways
function postprocess_datafiles($schema) {
	global $config;
        $tmpdir = $config['temp_dir']."/".$schema."/"; 
	$PATH=$tmpdir;
	$SORTOPTIONS='--temporary-directory="' . $tmpdir . '"';
	$SORT=$config['cmd_sort'];
	$JOIN=$config['cmd_join'];
	$lbl='postprocess_datafiles';



	// join way_nodes with node coordinates

	shellcmd("$SORT $SORTOPTIONS -t \"	\" -n -k 2,2 " . $PATH . "way_nodes.txt > " . $PATH . "way_nodes_sorted.txt", $lbl);
	unlink($PATH . 'way_nodes.txt');

	shellcmd("$SORT $SORTOPTIONS -t \"	\" -n -k 1,1 " . $PATH . "nodes.txt > " . $PATH . "nodes_sorted.txt", $lbl);
	unlink($PATH . 'nodes.txt');

	shellcmd("$JOIN -t \"	\" -e NULL -a 1 -1 2 -o 1.1,0,1.3,2.5,2.6,2.7,2.8 " . $PATH . "way_nodes_sorted.txt " . $PATH . "nodes_sorted.txt > " . $PATH . "way_nodes2.txt", $lbl);

	unlink($PATH . 'way_nodes_sorted.txt');


	// joining ways with coordinates of first and last node

	shellcmd("$SORT $SORTOPTIONS -t \"	\" -n -k 4,4 " . $PATH . "ways.txt > " . $PATH . "ways_sorted.txt", $lbl);
	unlink($PATH . 'ways.txt');

	shellcmd("$JOIN -t \"	\" -e NULL -a 1 -1 4 -o 1.1,1.2,1.3,0,1.5,2.5,2.6,2.7,2.8,1.6 " . $PATH . "ways_sorted.txt " . $PATH . "nodes_sorted.txt > " . $PATH . "ways2.txt", $lbl);

	shellcmd("$SORT $SORTOPTIONS -t \"	\" -n -k 5,5 " . $PATH . "ways2.txt > " . $PATH . "ways_sorted.txt", $lbl);
	unlink($PATH . 'ways2.txt');

	shellcmd("$JOIN -t \"	\" -e NULL -1 5 -o 1.1,1.2,1.3,1.4,0,1.6,1.7,1.8,1.9,2.5,2.6,2.7,2.8,1.10 " . $PATH . "ways_sorted.txt " . $PATH . "nodes_sorted.txt > " . $PATH . "ways.txt", $lbl);
	unlink($PATH . 'ways_sorted.txt');

}


?>
