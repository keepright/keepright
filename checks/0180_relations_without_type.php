<?php

/*
find any relation that has no type tag, wich is mandatory
*/


query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type, 'relation', r.id, 'This relation has no type tag, which is mandatory for relations', NOW()
	FROM relations r
	WHERE NOT EXISTS (

		SELECT k FROM relation_tags t
		WHERE t.relation_id=r.id AND k='type'

	)
", $db1);


?>