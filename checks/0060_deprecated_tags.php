<?php

// find ways/nodes/relations that use tags 
// listed on the wiki's deprecated features page

// list copied from http://wiki.openstreetmap.org/index.php/Deprecated_features
//"Date", "Deprecated Key", "Deprecated Value", "Replaced by", "Reason")
$replacement_list = array(

	array("2008-11-18", "natural", "marsh", "natural=wetland, wetland=*"),
	array("2008-10-19", "highway", "gate", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"), 
	array("2008-10-19", "highway", "stile", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"), 
	array("2008-10-19", "highway", "cattle_grid", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"), 
	array("2008-10-19", "highway", "toll_booth", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"), 
	array("2008-06-12", "highway", "viaduct", "bridge=*"),
	array("2008-06-12", "railway", "viaduct", "bridge=*"),
	array("2008-05-30", "route", "ncn", "Cycle_routes: http://wiki.openstreetmap.org/index.php/Cycle_routes"),
	array("2008-05-30", "man_made", "power_wind", "power=generator and power_source=wind"),
	array("2008-05-30", "man_made", "power_hydro", "power=generator and power_source=hydro"),
	array("2008-05-30", "man_made", "power_fossil", "power=generator and power_source=*"),
	array("2008-05-30", "man_made", "power_nuclear", "power=generator and power_source=nuclear"),
	array("2008-03-19", "highway", "unsurfaced", "highway=*+surface=unpaved or highway=track", "any highway classification can be unsurfaced."),
	array("2008-03-19", "waterway", "waste_disposal", "amenity=waste_disposal ", "Something this general should be in amenity."),
	array("2008-03-19", "waterway", "mooring", "mooring=yes ", "More flexibility; allows us to keep original value of waterway=."),
	array("2008-01-20", "historic", "museum", "tourism=museum ", "better fits into toplevel tags"),
	array("2008-01-10", "historic", "icon", "?", "no description, usage completely unknown"),
	array("2007-07-16", "abutters", "*", "landuse=* around the area ", "?"),
	array("2007-07-16", "amenity", "store", "shop=* ", "Shop introduced"),
	array("2007-07-16", "boundary", "national_park", "?", "?"),
	/*
	// removed at 2009-07-20 because of a discussion on talk list
	array("2007-07-16", "landuse", "wood", "landuse=forest", "?"),
	*/
	array("2007-07-16", "railway", "preserved_rail", "railway=preserved ", "the '_rail' suffix is redundant"),
	array("2007-07-13", "amenity", "supermarket", "shop=supermarket ", "Shop introduced"),
	array("2007-07-13", "amenity", "bakers", "shop=bakery ", "Shop introduced"),
	array("2007-07-13", "amenity", "butchers", "shop=butcher ", "Shop introduced"),
	array("2007-07-13", "amenity", "candle_stick_makers", "shop=chandler ", "Shop introduced"),
	array("2007", "highway", "minor", "highway=* (whatever fits: tertiary, unclassified, service, ...)", "?"),
	array("2007", "highway", "bridge", "highway=* + bridge=*", "?"),
	array("2006", "class", "*", "highway=* ", "highway introduced")
);

$tables = array('node'=>'node_tags', 'way'=>'way_tags', 'relation'=>'relation_tags');

// this loop will build up similar queries for node_tags, way_tags and relation_tags tables:
foreach ($replacement_list as $replacement) {
	echo "checking for {$replacement[1]} = {$replacement[2]}\n";

	$where = ' (k LIKE \'' . addslashes($replacement[1]) . '\'';
	if ($replacement[2]<>'*') 
		$where .= " AND v LIKE '". addslashes($replacement[2]) . "'";
	$where .= ")";

	$hint = (strlen(trim($replacement[3]))>1 ? '. Please use ' . trim($replacement[3]) . ' instead!' : '');

	foreach ($tables as $object_type=>$table) {
		query("
			INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked) 
			SELECT $error_type, '$object_type', {$object_type}_id, 'This $object_type uses deprecated tag ' || k || '=' || v || '$hint', NOW()
			FROM $table 
			WHERE $where
		", $db2, false);
	}
}

?>