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
		INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2,   last_checked)
		SELECT $error_type, '$object_type', {$object_type}_id, 'This $1 has an empty tag: $2', '$object_type', htmlspecialchars(array_to_string(array(
			SELECT '\"' || COALESCE(k,'') || '=' || COALESCE(v,'') || '\"'
			FROM $table AS tmp
			WHERE tmp.{$object_type}_id=t.{$object_type}_id AND (tmp.k IS NULL or LENGTH(TRIM(tmp.k))=0 OR tmp.v IS NULL or LENGTH(TRIM(tmp.v))=0)
		), ', ')), NOW()

		FROM $table t
		WHERE k IS NULL or LENGTH(TRIM(k))=0 OR v IS NULL or LENGTH(TRIM(v))=0
		GROUP BY {$object_type}_id
	", $db1);
}



// build a view containing all way_tags except some non-sense-tags
query("
	CREATE OR REPLACE VIEW tmp_tagged_ways AS
	SELECT way_id as id
	FROM way_tags wt
	WHERE k NOT IN ('created_by', 'source')
", $db1);

// note ways that have no tags in said view...
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type+1, 'way', ways.id, 'This way has no tags', NOW()
	FROM ways LEFT JOIN tmp_tagged_ways tw USING(id)
	WHERE tw.id IS NULL
", $db1);

// exception: members of relations
// need not be tagged, as long as the relation is tagged
// these are dropped

// help Postgres on Windows find a better query plan
query("ANALYZE _tmp_errors", $db1);

query("
	DELETE FROM _tmp_errors e
	WHERE e.error_type=$error_type+1 AND EXISTS(
		SELECT rm.relation_id
		FROM relation_members rm INNER JOIN relation_tags rt ON (rm.relation_id=rt.relation_id)
		WHERE rm.member_id=e.object_id
		AND rm.member_type='W'
		AND rt.k IS NOT NULL
	)
", $db1);


query("DROP VIEW tmp_tagged_ways", $db1);



// quite similar for nodes: no tags, not member of a way,
// not member of a relation (people draw for example
// speed cameras as relation where the node acting as
// role==device doesn't have any tag but it seems to be ok)
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT 2+$error_type, 'node', id,
		'This node is not member of any way and does not have any tags', NOW()
	FROM nodes n
	WHERE NOT EXISTS (
		SELECT 1
		FROM way_nodes wn
		WHERE wn.node_id=n.id
	) AND NOT EXISTS (
		SELECT 1
		FROM node_tags nt
		WHERE nt.node_id=n.id
	) AND NOT EXISTS (
		SELECT 1
		FROM relation_members rm
		WHERE rm.member_id=n.id AND
		rm.member_type='N'
	)
", $db1);


?>