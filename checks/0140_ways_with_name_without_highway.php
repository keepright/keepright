<?php


/*
ways that have a name tag but lack the highway-tag are reported here
*/



query("
	CREATE OR REPLACE VIEW _tmp_named_ways AS
	SELECT way_id, MIN(v) as v
	FROM way_tags
	WHERE k='name'
	GROUP BY way_id
", $db1);

query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, description, last_checked) 
	SELECT $error_type, 'way', way_id, 'This way has a name-tag but no frequently used tag that declares its use (e.g. highway, amenity, building etc)', NOW()
	FROM _tmp_named_ways b
	WHERE NOT EXISTS( 
		SELECT * 
		FROM way_tags wt 
		WHERE wt.way_id=b.way_id AND 
		wt.k NOT IN ('highway', 'history', 'natural', 'railway', 'building', 'amenity', 'boundary', 'leisure', 'aerialway', 'boat', 'bridge', 'tunnel', 'shop', 'area', 'tourism', 'place', 'man_made', 'wood', 'cycleway') )
", $db1);

query("DROP VIEW _tmp_named_ways", $db1);

?>