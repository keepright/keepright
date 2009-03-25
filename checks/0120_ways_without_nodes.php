<?php


/*

ways without any node are reported as error 
although you wont be able to delete such a way in potlatch...

there are also ways that have just one single node. Someone should examine these too...

*/




query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
	SELECT node_count+$error_type, 'way', id, 
		CASE WHEN node_count=0 
			THEN 'This way has no nodes'
			ELSE 'This way has just one single node'
		END
		, NOW()
	FROM ways
	WHERE node_count<2
", $db1);



?>