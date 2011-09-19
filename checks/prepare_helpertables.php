<?php


// create helper tables used by checks on the database for Austria.\n";

function prepare_helpertables($schema) {

	logger("Creating helper tables for $schema");

	$db1 = pg_pconnect(connectstring($schema), PGSQL_CONNECT_FORCE_NEW);
	$db2 = pg_pconnect(connectstring($schema), PGSQL_CONNECT_FORCE_NEW);

	$starttime=microtime(true);

	//--------------------------------------------------
	create_postgres_functions($db1);


	// every relation around the globe is included in each dump file
	// there are three kinds of relations:
	// ...where every member lies inside the current area (best case)
	// ...where some members lie outside
	// ...where all members lie outside
	// for the last case we can easily drop all data related to those relations:

	do {
		drop_column('relation_members', 'object_exists', $db1, '', false);
		add_column('relation_members', 'object_exists', 'boolean', $db1, '', false);

		$member_types=array('N'=>'node', 'W'=>'way', 'R'=>'relation');
		// execute similar queries for all three tables to find out
		// if the specified member exist in the current area
		foreach($member_types as $k=>$v) {

			query("
				UPDATE relation_members rm
				SET object_exists=true
				FROM {$v}s
				WHERE rm.member_type='$k' AND rm.member_id={$v}s.id
			", $db1, false);
		}

		query("CREATE INDEX idx_relation_members ON relation_members (object_exists)", $db1, false);
		query("ANALYZE relation_members (object_exists)", $db1, false);

		// foreign relations are those who don't have a single member
		// in the database
		query("DROP TABLE IF EXISTS _tmp_tmp", $db1, false);
		query("SELECT relation_id, object_exists INTO _tmp_tmp
			FROM relation_members rm
			GROUP BY relation_id, object_exists
		", $db1, false);

		query("DROP TABLE IF EXISTS _tmp_foreign_relations", $db1, false);
		query("
			SELECT relation_id INTO _tmp_foreign_relations
			FROM _tmp_tmp
			WHERE object_exists IS NULL
		", $db1, false);
		query("
			DELETE FROM _tmp_foreign_relations
			WHERE relation_id IN (
				SELECT relation_id FROM _tmp_tmp WHERE object_exists IS NOT NULL
			)
		", $db1, false);


		// drop relations and their tags if they don't have a single member in the current db
		foreach (array('relation_tags'=>'relation_id', 'relation_members'=>'relation_id', 'relations'=>'id') as $table=>$key) {
			$result=query("
				DELETE FROM $table WHERE $key IN
					(SELECT relation_id FROM _tmp_foreign_relations)
			", $db1, false);
			$record_count=pg_affected_rows($result);
		}
		echo "dropped $record_count foreign relations\n";
		query("DROP TABLE IF EXISTS _tmp_foreign_relations", $db1, false);
		query("DROP TABLE IF EXISTS _tmp_tmp", $db1, false);
	}
	while ($record_count>0);



	// now drop relations that don't have a single member
	// these are of course errors but where should they
	// be located on the map??
	query("DROP TABLE IF EXISTS _tmp_empty_relations", $db1, false);
	query("
		SELECT r.id INTO _tmp_empty_relations
		FROM relations r LEFT JOIN relation_members m ON r.id=m.relation_id
		WHERE m.relation_id IS NULL
	", $db1, false);


	// drop relations and their tags if they don't have a single member in the current db
	foreach (array('relation_tags'=>'relation_id', 'relations'=>'id') as $table=>$key) {
		$result=query("
			DELETE FROM $table WHERE $key IN
				(SELECT id FROM _tmp_empty_relations)
		", $db1, false);
		$record_count=pg_affected_rows($result);
	}
	echo "dropped $record_count empty relations\n";
	query("DROP TABLE IF EXISTS _tmp_empty_relations", $db1, false);




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
	query("SELECT way_id, COUNT(node_id) AS cnt
		INTO _tmp_nodecounts
		FROM way_nodes
		GROUP BY way_id
	", $db1);
	query("CREATE INDEX idx_tmp_nodecounts_way_id ON _tmp_nodecounts (way_id)", $db1);
	query("ANALYZE _tmp_nodecounts", $db1);
	query("ANALYZE ways", $db1);

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
	query("ANALYZE nodes", $db1);
	query("ANALYZE node_tags", $db1);
	query("ANALYZE ways", $db1);
	query("ANALYZE way_tags", $db1);
	query("ANALYZE way_nodes", $db1);
	query("ANALYZE relations", $db1);
	query("ANALYZE relation_tags", $db1);
	query("ANALYZE relation_members", $db1);
	query("VACUUM ANALYZE public.errors", $db1);
	query("VACUUM ANALYZE public.error_view", $db1);
	//--------------------------------------------------

	drop_postgres_functions($db1);

	pg_close($db1);
	pg_close($db2);

}
?>