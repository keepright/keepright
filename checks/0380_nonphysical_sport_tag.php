<?php


/*

-------------------------------------
-- every sport tag needs a physical tag (e.g. leisure, landuse, tourism, amenity)
-------------------------------------

'sports' is a non-physical tag that needs to be bound to some physical structure
like for example a leisure-item or an amenity

*/




query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type, 'way', way_id, 'This way is tagged $1 but has no physical tag like e.g. leisure, building, amenity or highway', 'sport=' || htmlspecialchars(MIN(t.v)), NOW()

	FROM way_tags t
	WHERE k = 'sport'
	AND NOT EXISTS (
		SELECT tmp.way_id
		FROM way_tags tmp
		WHERE tmp.way_id=t.way_id AND (
			tmp.k in ('leisure', 'piste', 'building', 'natural', 'landuse', 'highway', 'bridge', 'ski_resort', 'route', 'tourism', 'amenity', 'shop')
			OR tmp.k like 'piste:%'
		)
	)
	GROUP BY way_id
", $db1);



?>