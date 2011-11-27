<?php


/*

-------------------------------------
-- language of value in name tag unknown
-------------------------------------

where an object has both 'name' and 'name:xx' but they are
different strings, the language of the main name should be specified.
So this would be incorrect:

    name=London
    name:fr=Londres

the check warns that the two strings are different, and that an explicit
tag should be added to show the language of the main name:

    name=London
    name:en=London
    name:fr=Londres

This helps applications to choose the appropriate name to show to the user.
*/


$tables = array('node'=>'node_tags', 'way'=>'way_tags', 'relation'=>'relation_tags');

// this loop will execute similar queries for all three *_tags tables
foreach ($tables as $object_type=>$table) {


	// find any object that is tagged with name=... and that has
	// at least one name:XX tag but there is no name:XX tag with
	// the same value than the original name tag
	query("
		INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2,  last_checked)
		SELECT $error_type, '$object_type', {$object_type}_id, 'It would be nice if this $1 had an additional tag ''name:XX=$2'' where XX shows the language of its name ''$2''.', '$object_type', htmlspecialchars(MAX(tags.v)), NOW()

		FROM $table tags
		WHERE k='name' AND EXISTS(
			SELECT t.{$object_type}_id
			FROM $table t
			WHERE t.{$object_type}_id=tags.{$object_type}_id AND
			t.k LIKE 'name:__'

		) AND NOT EXISTS(
			SELECT t.{$object_type}_id
			FROM $table t
			WHERE t.{$object_type}_id=tags.{$object_type}_id AND
			t.v=tags.v AND t.k LIKE 'name:__'
		)
		GROUP BY {$object_type}_id
	", $db1);

}

?>