<?php

/*
inspired by maplint these routines find nodes, ways and relations with either empty keys
or empty walues in their tags

also according to maplint ways are found that have no tags (except 'created_by')
*/

$tables = array('node'=>'node_tags', 'way'=>'way_tags', 'relation'=>'relation_tags');

// this loop will execute similar queries for all three *_tags tables
foreach ($tables as $object_type=>$table) {

	query("
		INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
		SELECT $error_type, '$object_type', {$object_type}_id, 'This $object_type has an empty tag: ' || array_to_string(array(
			SELECT '\"' || COALESCE(k,'') || '=' || COALESCE(v,'') || '\"'
			FROM $table AS tmp
			WHERE tmp.{$object_type}_id=t.{$object_type}_id AND (tmp.k IS NULL or LENGTH(TRIM(tmp.k))=0 OR tmp.v IS NULL or LENGTH(TRIM(tmp.v))=0)
		), ' and '), NOW()

		FROM $table t
		WHERE k IS NULL or LENGTH(TRIM(k))=0 OR v IS NULL or LENGTH(TRIM(v))=0
		GROUP BY {$object_type}_id
	", $db1);
}



// build a view containing all way_tags except 'created_by'
query("
	CREATE OR REPLACE VIEW tmp_tagged_ways AS
	SELECT way_id as id
	FROM way_tags wt
	WHERE k<>'created_by'
", $db1);

// note ways that have no tags in said view...
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
	SELECT $error_type+1, 'way', ways.id, 'This way has no tags', NOW()
	FROM ways LEFT JOIN tmp_tagged_ways tw USING(id)
	WHERE tw.id IS NULL
", $db1);

// exception: members of multipolygon relations (used in buildings)
// need not be tagged, especially the inner members
// these are dropped
query("
	DELETE FROM _tmp_errors e
	WHERE e.error_type=$error_type+1 AND EXISTS(
		SELECT rm.relation_id
		FROM relation_members rm INNER JOIN relation_tags rt ON (rm.relation_id=rt.relation_id)
		WHERE rm.member_id=e.object_id
		AND rm.member_type=2
		AND rm.member_role in ('inner', 'outer')
		AND rt.k='type'
		AND rt.v='multipolygon'
	)
", $db1);





query("DROP VIEW tmp_tagged_ways", $db1);

?>