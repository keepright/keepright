<?php

/*
-------------------------------------
-- finding non-loop-ways that are tagged as areas
-------------------------------------

select all way-ids into a temporary table and find the id of the
first and last node of each way.
If borderlines or areas are closed loops there must not be any way ending
in a node, where not another way starts. In other words: every
last node of a way has to be the first node of another (or the same) way

There's one exception to this: an area may be built from smaller segments
that share common nodes (imagine a lake as a closed loop and the river
leaving the lake, both drawn as areas: the borders of the river end in common
nodes with the lake border and are non-closed loops)
So the first part is not the whole story...
For each way that is not continued by the end-node of another way or closed-loop
in itself one has to look if there is any connection from it's open-ended node
to its other end-node using equal-tagged ways but not the way itself.
*/


// some exceptions that need not always be areas and thus shouldn't be checked:
$dontcheck = array(
	'leisure' => 'track',
	'religion' => 'christian'
);


query("DROP TABLE IF EXISTS _tmp_way_tags;", $db1, false);
query("
	CREATE TEMPORARY TABLE _tmp_way_tags (
	id serial NOT NULL,
	k varchar(255) NOT NULL,
	v varchar(255) NOT NULL,
	PRIMARY KEY (id)
	)
", $db1, false);



//open standards-file and extract rules that lead to drawing an area
//insert key-value-pairs into tmp table

$xmlstr=file_get_contents('standard.xml');
$xml = new SimpleXMLElement($xmlstr);

foreach ($xml->xpath('//rule/area') as $area) {
	//echo "--------------------------------------------\n";
	//print_r($area);
	foreach ($area->xpath('..') as $rule) {
		//print_r($rule);	
		$k=pg_escape_string($db1, (string) $rule['k']);
		$v=pg_escape_string($db1, (string) $rule['v']);
		$values=explode('|', $v);
		foreach ($values as $dontcare=>$vv) if (!array_key_exists($k,$dontcheck) || !($dontcheck[$k]===$vv)) {
			query("INSERT INTO _tmp_way_tags(k,v) VALUES ('$k', '$vv');", $db1, false);
		}

	}

}


// in some key values you have some values explicitly named
// and all others are catched with "*". That doesn't bother here,
// so delete all explicitly called values as they are included with "*"

query("
	DELETE FROM _tmp_way_tags
	WHERE k IN (SELECT DISTINCT k FROM _tmp_way_tags WHERE v='*')
	AND v<>'*'
", $db1, false);



// this is an exception, that is not drawn as area
// this check is also used for finding holes in coastlines
//	process_tag('natural', 'coastline', true, $db1, $db2, $db4);

$result = query("
	SELECT k, v
	FROM _tmp_way_tags
", $db3, false);

while ($row=pg_fetch_array($result)) {
	process_tag($row['k'], $row['v'], false, $db1, $db2, $db4);
}
pg_free_result($result);




function process_tag($k, $v, $check_strictly, $db1, $db2, $db4) {
	global $error_type;
	logger("checking for $k=$v");

	query("DROP TABLE IF EXISTS _tmp_ways;", $db1, false);
	query("
		CREATE TEMPORARY TABLE _tmp_ways (
		way_id bigint NOT NULL,
		first_node_id bigint,
		last_node_id bigint,
		PRIMARY KEY (way_id)
		)
	", $db1, false);

	// find id of ways that are tagged as areas
	query("
		INSERT INTO _tmp_ways (way_id)
		SELECT DISTINCT t.way_id
		FROM way_tags t
		WHERE t.k = '$k' " . (trim($v)=='*' ? '' : " AND t.v = '$v'")
	, $db1, false);
	query("ANALYZE _tmp_ways", $db1, false);

	// this is an exception introduced for eg. bridges that are attractions.
	// tourism-attractions that are highways need not be closed-loop
	// thank you, Hermann K.! (email, 2009-01-31 19:27)
	if (trim($k)=='tourism' && trim($v)=='attraction') {
		query("
			DELETE FROM _tmp_ways
			WHERE EXISTS (
				SELECT t.k
				FROM way_tags t
				WHERE t.way_id=_tmp_ways.way_id AND t.k='highway'
			)
		", $db1, false);
	}

	// find for those ways the first and last node
	query("
		UPDATE _tmp_ways w
		SET first_node_id=ways.first_node_id, last_node_id=ways.last_node_id
		FROM ways
		WHERE ways.id=w.way_id
	", $db1, false);
	query("CREATE INDEX idx_tmp_ways_first_node_id ON _tmp_ways (first_node_id)", $db1, false);
	query("CREATE INDEX idx_tmp_ways_last_node_id ON _tmp_ways (last_node_id)", $db1, false);
	query("ANALYZE _tmp_ways", $db1, false);


	// now find any intermediate nodes but discard nodes that are used only once
	// as they don't take part in junctions of ways

	// _tmp_way_nodes3 will contain any nodes that are used by area-ways
	query("DROP TABLE IF EXISTS _tmp_way_nodes3", $db1, false);
	query("
		CREATE TEMPORARY TABLE _tmp_way_nodes3 (
		way_id bigint NOT NULL,
		node_id bigint NOT NULL)
	", $db1, false);

	query("
		INSERT INTO _tmp_way_nodes3 (way_id, node_id)
		SELECT w.way_id, wn.node_id
		FROM _tmp_ways w INNER JOIN way_nodes wn ON w.way_id=wn.way_id
	", $db1, false);
	query("CREATE INDEX idx_tmp_way_nodes3 ON _tmp_way_nodes3 (node_id, way_id)", $db1, false);
	query("ANALYZE _tmp_way_nodes3", $db1, false);

	// find nodes that are used at least twice and
	// reduce _tmp_way_nodes3 to just the nodes found
	// and build _tmp_way_nodes
	query("DROP TABLE IF EXISTS _tmp_way_nodes", $db1, false);
	query("
		CREATE TEMPORARY TABLE _tmp_way_nodes (
		way_id bigint NOT NULL,
		node_id bigint NOT NULL)
	", $db1, false);
	query("
		INSERT INTO _tmp_way_nodes
		SELECT _tmp_way_nodes3.way_id, _tmp_way_nodes3.node_id
		FROM _tmp_way_nodes3
		WHERE node_id IN (
			SELECT tmp.node_id
			FROM _tmp_way_nodes3 tmp
			GROUP BY tmp.node_id
			HAVING COUNT(DISTINCT tmp.way_id)>1
		)
	", $db1, false);
	query("CREATE INDEX idx_tmp_way_nodes ON _tmp_way_nodes (way_id)", $db1, false);
	query("ANALYZE _tmp_way_nodes", $db1, false);


	// member_ways will contain all ways that are already connected
	query("DROP TABLE IF EXISTS _tmp_members_ways", $db1, false);
	query("
		CREATE TEMPORARY TABLE _tmp_members_ways (
		way_id bigint NOT NULL default 0,
		marker int NOT NULL default 0,
		PRIMARY KEY (way_id)
		)
	", $db1, false);
	query("CREATE INDEX idx_tmp_members_ways ON _tmp_members_ways (marker)", $db1, false);
	add_insert_ignore_rule('_tmp_members_ways', 'way_id', $db1);


	// temporary table used for newly found nodes
	query("DROP TABLE IF EXISTS _tmp_members_nodes", $db1, false);
	query("
		CREATE TEMPORARY TABLE _tmp_members_nodes (
		node_id bigint NOT NULL default 0,
		marker int NOT NULL default 0,
		PRIMARY KEY  (node_id)
		)
	", $db1, false);
	query("CREATE INDEX idx_tmp_members_nodes ON _tmp_members_nodes (marker)", $db1, false);
	add_insert_ignore_rule('_tmp_members_nodes', 'node_id', $db1);


	// member_ways will contain all ways that are already connected
	query("DROP TABLE IF EXISTS _tmp_members", $db1, false);
	query("
		CREATE TEMPORARY TABLE _tmp_members (
		way_id bigint NOT NULL default 0,
		last_way_id bigint NOT NULL default 0,
		first_node_id bigint NOT NULL default 0,
		last_node_id bigint NOT NULL default 0,
		marker int NOT NULL default 0,
		PRIMARY KEY (way_id)
		)
	", $db1, false);
	query("CREATE INDEX idx_tmp_members_marker ON _tmp_members (marker)", $db1, false);
	query("CREATE INDEX idx_tmp_members_first_node_id ON _tmp_members (first_node_id)", $db1, false);
	query("CREATE INDEX idx_tmp_members_last_node_id ON _tmp_members (last_node_id)", $db1, false);
	add_insert_ignore_rule('_tmp_members', 'way_id', $db1);



	$bi=new BufferedInserter('_tmp_errors', $db4, 1);

	$closed_ways=array();

	$result=query("
		SELECT way_id, first_node_id, last_node_id
		FROM _tmp_ways
		WHERE first_node_id<>last_node_id
	", $db1, false);

	while ($row = pg_fetch_array($result)) {

		if ($check_strictly) {
			if ($closed_ways[$row['way_id']]) { 
				echo "already know way #{$row['way_id']} to be closed-loop\n";
				continue;
			}

			$ways=is_closed_loop_strict($row['way_id'], $row['first_node_id'], $row['last_node_id'], $db2);

			if (is_array($ways)) {
				foreach ($ways as $id) $closed_ways[$id]=true;
				continue;
			} else {
				$bi->insert("$error_type\tway\t{$row['way_id']}\tNOW()\t\\N\t\\N\t\\N\t\\N\tThis coastline-way is not part of a closed-loop\t\\N\t\\N\t\\N");
			}

		} else {
			if (!is_closed_loop($row['way_id'], $row['first_node_id'], $row['last_node_id'], $db2))
				$bi->insert("$error_type\tway\t{$row['way_id']}\tNOW()\t\\N\t\\N\tThis way is tagged with '$1=$2' and should be closed-loop\t" . $bi->escape($k) . "\t" . $bi->escape($v) . "\t\\N\t\\N\t\\N");
		}
	}
	$bi->flush_buffer();
	pg_free_result($result);
}

print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_way_nodes;", $db1, false);
query("DROP TABLE IF EXISTS _tmp_way_nodes2;", $db1, false);
query("DROP TABLE IF EXISTS _tmp_way_nodes3;", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways;", $db1, false);
query("DROP TABLE IF EXISTS _tmp_way_tags;", $db1, false);
query("DROP TABLE IF EXISTS _tmp_members_ways;", $db1, false);
query("DROP TABLE IF EXISTS _tmp_members_nodes;", $db1, false);
query("DROP TABLE IF EXISTS _tmp_members", $db1, false);



// check if starting in $first_node_id and using just ways of the same type 
// $last_node_id can be reached only using first- and last nodes of ways
// (ie. only using complete ways)
function is_closed_loop_strict($way_id, $first_node_id, $last_node_id, $db2) {

	echo "----strictly checking way #$way_id\n";
	query("TRUNCATE TABLE _tmp_members", $db2, false);

	// add starting node
	query("INSERT INTO _tmp_members (way_id, first_node_id, last_node_id) VALUES ($way_id, $first_node_id, $last_node_id)", $db2, false);

	do {
		// find and add ways that are connected to the end-nodes found in the last round
		$result=query("
			INSERT INTO _tmp_members (way_id, last_way_id, first_node_id, last_node_id, marker)
			SELECT DISTINCT w.way_id, m.way_id, w.first_node_id, w.last_node_id, -1
			FROM _tmp_members m INNER JOIN _tmp_ways w ON (m.first_node_id=w.first_node_id or m.first_node_id=w.last_node_id)
			WHERE w.way_id<>$way_id AND m.marker=0
		", $db2, false);

		$new_ways=pg_affected_rows($result);
		//echo "found $new_ways additional ways\n";

		// unmark ways of last round
		query("UPDATE _tmp_members SET marker=marker+1", $db2, false);

		// look for our destination node (is it connected yet?)
		$found_last_node=query_firstval("
			SELECT COUNT(*) FROM _tmp_members WHERE way_id<>$way_id AND (first_node_id=$last_node_id OR last_node_id=$last_node_id)
		", $db2, false) > 0;

	} while ($new_ways>0 && !$found_last_node);


	if ($found_last_node) {

		//echo "way #$way_id closed!\n";
		return get_way_list($way_id, $db2);
	} else {

		//echo "way #$way_id open!\n";
		return false;
	}
}


// for a known closed-loop way this routine constructs an array
// of ways that were used to walk around the loop
// eyery way that is found here can be marked as closed-loop
// and needs not be checked again
function get_way_list($way_id, $db2) {
	$way_ids=array();

	$marker=0;
	// there may more than one way lead to the destination way
	// find just one way_id leading to the destination
	$last_way_id = query_firstval("
		SELECT DISTINCT m.way_id
		FROM _tmp_members m INNER JOIN _tmp_ways w ON (m.first_node_id=w.first_node_id or m.first_node_id=w.last_node_id)
		WHERE w.way_id=$way_id AND m.marker=0
	", $db2, false);
	$way_ids[] = $last_way_id;

	//echo "way id $last_way_id\n";
	while ($last_way_id != null) {

		// find the predecessor way_id of current way_id
		$last_way_id = query_firstval("
			SELECT last_way_id
			FROM _tmp_members
			WHERE marker=$marker AND way_id=$last_way_id
		", $db2, false);
		//echo "way id $last_way_id\n";

		$way_ids[] = $last_way_id;

		$marker++;
	}

	//echo "found loop:\n"; print_r($way_ids);
	return $way_ids;
}


// check if starting in $first_node_id and using just ways of the same type 
// $last_node_id can be reached using any path
function is_closed_loop($way_id, $first_node_id, $last_node_id, $db2) {

	//echo "----checking way #$way_id\n";
	query("TRUNCATE TABLE _tmp_members_ways", $db2, false);
	query("TRUNCATE TABLE _tmp_members_nodes", $db2, false);

	// add starting node
	query("INSERT INTO _tmp_members_nodes (node_id) VALUES($first_node_id)", $db2, false);

	do {
		// insert ways that are connected to nodes found before. these make the starting
		// point for the next round
		$result=query("
			INSERT INTO _tmp_members_ways (way_id)
			SELECT DISTINCT wn.way_id
			FROM _tmp_way_nodes wn INNER JOIN _tmp_members_nodes n USING (node_id)
			WHERE wn.way_id<>$way_id AND n.marker=0
		", $db2, false);

		$new_ways=pg_affected_rows($result);
		//echo "found $new_ways additional ways\n";

		// insert ways that share nodes with ways that were inserted last time:
		// first find nodes that belong to ways found in the last round
		query("
			INSERT INTO _tmp_members_nodes (node_id)
			SELECT DISTINCT wn.node_id
			FROM _tmp_members_ways m INNER JOIN _tmp_way_nodes wn USING (way_id)
			WHERE m.marker=0
		", $db2, false);

		// unmark ways of last round
		query("UPDATE _tmp_members_ways SET marker=1 WHERE marker=0", $db2, false);
		query("UPDATE _tmp_members_nodes SET marker=1 WHERE marker=0", $db2, false);
//		query("TRUNCATE TABLE _tmp_n", $db2, false);

		$result=query("
			SELECT COUNT(*) AS cnt FROM _tmp_members_nodes WHERE node_id=$last_node_id
		", $db2, false);
		if ($row = pg_fetch_array($result)) $found_last_node=$row['cnt']>0; else $found_last_node=false;
		pg_free_result($result);

	} while ($new_ways>0 && !$found_last_node);

	//if ($found_last_node) echo "way #$way_id closed!\n"; else echo "way #$way_id open!\n";
	return $found_last_node;
}


?>