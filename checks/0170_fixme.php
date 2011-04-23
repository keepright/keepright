<?php

/*
find any way/node/relation that is tagged with FIXME anywhere in key or value

highway=road implies FIXME according to the wiki
*/


$tables=array('node', 'way', 'relation');
// execute similar queries for all three *_tags tables
// according to the Wiki 'fixme' is valid as well as 'FIXME' so we search case insensitive here
foreach($tables as $table) {

	query("
		INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, last_checked)
		SELECT $error_type, '$table', {$table}_id, '$1',  array_to_string(array(
			SELECT '\"' || COALESCE(k,'') || '=' || COALESCE(v,'') || '\"'
			FROM {$table}_tags AS tmp
			WHERE tmp.{$table}_id=t.{$table}_id AND (
				tmp.k iLIKE '%fixme%' OR
				tmp.v iLIKE '%fixme%' OR
				(tmp.k='name' AND tmp.v='tbd') OR
				(tmp.k='ref' AND tmp.v='tbd') OR
				(tmp.k='highway' AND tmp.v='road')
			)
		), ', '), NOW()

		FROM {$table}_tags t
		WHERE (
			k iLIKE '%fixme%' OR
			v iLIKE '%fixme%' OR
			(k='name' AND v='tbd') OR
			(k='ref' AND v='tbd') OR
			(k='highway' AND v='road')
		)
		GROUP BY {$table}_id
	", $db1);

}

?>