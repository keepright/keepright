<?php


	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
		SELECT $error_type, 'way', w.id, 'There are one or more deleted nodes used in this way.', NOW()
		FROM (current_way_nodes AS wn INNER JOIN current_nodes AS n ON wn.node_id=n.id) INNER JOIN current_ways AS w ON wn.id=w.id
		WHERE n.visible<>1 AND w.visible=1;
	", $db1);


	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked) 
		SELECT $error_type+1, 'relation', r.id, 'There are one or more deleted nodes used in this relation.', NOW()
		FROM (current_nodes AS n INNER JOIN current_relation_members AS rm ON rm.member_id=n.id AND rm.member_type='node') 
		INNER JOIN current_relations as r ON rm.id=r.id
		WHERE n.visible<>1 AND r.visible=1;
	", $db1);


	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked) 
		SELECT $error_type+2, 'relation', r.id, 'There are one or more deleted ways used in this relation.', NOW()
		FROM (current_ways AS w INNER JOIN current_relation_members AS rm ON rm.member_id=w.id AND rm.member_type='way') 
		INNER JOIN current_relations as r ON rm.id=r.id
		WHERE w.visible<>1 AND r.visible=1;
	", $db1);


?>