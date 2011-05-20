<?php


/*

-------------------------------------
-- highway=track ways should be added with more detail about the tracktype (grade1..grade5)
-------------------------------------
*/


query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type, 'way', way_id, 'This track doesn''t have a tracktype', NOW()

	FROM way_tags t
	WHERE k = 'highway' and v = 'track'
	AND NOT EXISTS (
		SELECT tmp.way_id
		FROM way_tags tmp
		WHERE tmp.way_id=t.way_id AND tmp.k = 'tracktype'
	)
	GROUP BY way_id
", $db1);

?>