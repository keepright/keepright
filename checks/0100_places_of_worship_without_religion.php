<?php


/*
inspired by maplint places of worship are reported if they lack a religion tag
*/

$tables = array('node', 'way');

// this loop will execute similar queries for all *_tags tables
foreach ($tables as $object_type) {

	query("
		INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1,  last_checked)
		SELECT $error_type, '{$object_type}', {$object_type}_id, 'This $1 is tagged as place of worship and therefore needs a religion tag', '{$object_type}', NOW()
		FROM {$object_type}_tags b
		WHERE k='amenity' AND v='place_of_worship'
		AND NOT EXISTS(
			SELECT * FROM {$object_type}_tags t
			WHERE t.{$object_type}_id=b.{$object_type}_id AND
			t.k IN ('religion', 'denomination')
		)
		GROUP BY {$object_type}_id
	", $db1);
}
?>