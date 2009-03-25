<?php

/*
find any way/node/relation that is tagged with FIXME anywhere in key or value
*/


$tables=array('node', 'way', 'relation');
// execute similar queries for all three *_tags tables
foreach($tables as $table) {

	query("
		INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
		SELECT $error_type, '$table', {$table}_id, 'This {$table} is fixme-tagged: ' || array_to_string(array(
			SELECT '\"' || COALESCE(k,'') || '=' || COALESCE(v,'') || '\"'
			FROM {$table}_tags AS tmp
			WHERE tmp.{$table}_id=t.{$table}_id AND (tmp.k LIKE '%fixme%' OR tmp.v LIKE '%fixme%' OR (k='name' AND v='tbd') OR (k='ref' AND v='tbd'))
		), ' and '), NOW()

		FROM {$table}_tags t
		WHERE (k LIKE '%fixme%' OR v LIKE '%fixme%' OR (k='name' AND v='tbd') OR (k='ref' AND v='tbd'))
		GROUP BY {$table}_id
	", $db1);

}

?>