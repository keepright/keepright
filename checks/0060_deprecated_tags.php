<?php

// find ways/nodes/relations that use tags
// listed on the wiki's deprecated features page

// list copied from http://wiki.openstreetmap.org/index.php/Deprecated_features
//"Date", "Deprecated Key", "Deprecated Value", "Replaced by", "Reason")
$replacement_list = array(

	/*
	// removed at 2009-07-20 because of a discussion on talk list
	array("2007-07-16", "landuse", "wood", "landuse=forest", "?"),
	*/
	array("2007-07-16", "abutters", "*", "landuse=* around the general area and abutters=* along street-front exceptions. ", "abutters doesn't indicate how far from the road the land use extends, so when you have imagery, consider using landuse instead. However, abutters is still useful for marking street-frontage exceptions to a general rule imposed by landuse."),
	array("2007-07-13", "amenity", "bakers", "shop=bakery ", "Shop introduced"),
	array("2007-07-13", "amenity", "butchers", "shop=butcher ", "Shop introduced"),
	array("2007-07-13", "amenity", "candle_stick_makers", "shop=chandler ", "Shop introduced"),
	array("2009-02-12", "amenity", "signpost", "information=guidepost"),
	array("2007-07-16", "amenity", "store", "shop=* ", "Shop introduced"),
	array("2007-07-13", "amenity", "supermarket", "shop=supermarket ", "Shop introduced"),
	/*
	// removed at 2010-02-23 because class=free is used for
	// wlan access points nowadays.
	array("2006", "class", "*", "highway=* ", "highway introduced"),
	*/
	array("2007", "highway", "bridge", "highway=* + bridge=*", "any type of highway can cross a bridge."),
	array("2008-10-19", "highway", "cattle_grid", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
	array("2008-10-19", "highway", "gate", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
	array("2007", "highway", "minor", "highway=* (whatever fits: tertiary, unclassified, service, ...)", "?"),
	array("2008-10-19", "highway", "stile", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
	array("2008-10-19", "highway", "toll_booth", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
	array("2008-06-12", "highway", "viaduct", "bridge=*"),
	array("2008-03-19", "highway", "unsurfaced", "highway=*+surface=unpaved or highway=track", "any highway classification can be unsurfaced."),
	array("2008-01-10", "historic", "icon", "?", "no description, usage completely unknown"),
	array("2008-01-20", "historic", "museum", "tourism=museum ", "better fits into toplevel tags"),
	array("2008-05-30", "man_made", "power_fossil", "power=generator and power_source=*"),
	array("2008-05-30", "man_made", "power_hydro", "power=generator and power_source=hydro"),
	array("2008-05-30", "man_made", "power_nuclear", "power=generator and power_source=nuclear"),
	array("2008-05-30", "man_made", "power_wind", "power=generator and power_source=wind"),
	array("2008-11-18", "natural", "marsh", "natural=wetland, wetland=*"),
	array("2007-07-16", "railway", "preserved_rail", "railway=preserved ", "the '_rail' suffix is redundant"),
	array("2008-06-12", "railway", "viaduct", "bridge=*"),
	array("2008-05-30", "route", "ncn", "Cycle_routes: http://wiki.openstreetmap.org/index.php/Cycle_routes"),
	array("2008-03-19", "waterway", "mooring", "mooring=yes ", "More flexibility; allows us to keep original value of waterway=."),
	array("2008-03-19", "waterway", "waste_disposal", "amenity=waste_disposal ", "Something this general should be in amenity."),
	array("2008-03-21", "waterway", "water_point", "amenity=drinking_water ", "Access to drinking water is not limited to waterways."),
	array("2009-08-1", "highway", "byway", "highway=path or highway=track etc plus designation=byway_open_to_all_traffic or designation=restricted_byway", "More accurate tagging for Public rights of way in England and Wales. "),
	array("2010-09-1", "power_source", "*", "generator:source=*", "Reorganised tagging for power generator related things (see Proposed features/generator rationalisation). "),
	array("2010-09-1", "power_rating", "*", "generator:output=*", "Reorganised tagging for power generator related things (see Proposed features/generator rationalisation). "),
	array("2011-10-27", "building", "entrance", "entrance=*", "Cleaning the semantics of the \"building\" tag; entrance=* approved (see Proposed_features/entrance)."),
	array("2011-10-27", "building:type", "*", "building=*", "Please use building=<building typology>"),
	array("2011-10-27", "natural", "land", "type=multipolygon (islands in lakes and rivers) or natural=coastline (islands in the sea)", "Use Relations/Multipolygon for islands in lakes and rivers, natural=coastline for islands in the sea."),
	array("2012-01-19", "bicycle_parking", "sheffield", "bicycle_parking=stand", "No real difference and sheffield is only used in UK, see Talk:Key:bicycle parking"),
	array("2012-01-19", "highway", "ford", "ford=*", ""),
	array("2012-11-28", "color", "*", "colour=*", "Usage of American form color probably mainly due to a bug in JOSM presets."),
	/*
	// removed at 2013-3-29 because http://wiki.openstreetmap.org/wiki/Key:power list it as valid
	array("2013-01-24", "power", "station", "power=sub_station", "'power=station' may be confused with 'power station' (= power plant) and is never used as a synonym for substation in English.")
	*/
);



$tables = array('node'=>'node_tags', 'way'=>'way_tags', 'relation'=>'relation_tags');

// this loop will build up similar queries for node_tags, way_tags and relation_tags tables:
foreach ($replacement_list as $replacement) {
	logger("checking for {$replacement[1]} = {$replacement[2]}");

	$where = ' (k LIKE \'' . pg_escape_string($db2, $replacement[1]) . '\'';
	if ($replacement[2]<>'*') 
		$where .= " AND v LIKE '". pg_escape_string($db2, $replacement[2]) . "'";
	$where .= ")";


  $msgid="This $1 uses deprecated tag ''$2=$3''";
  
	if (strlen(trim($replacement[3]))>1) {
		$msgid .= '. Please use &quot;$4&quot; instead!';
		$repl = quote($db2, trim($replacement[3]));
	} else {
		$repl='';
	}


	foreach ($tables as $object_type=>$table) {
		query("
			INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, txt2, txt3, txt4, last_checked)
			SELECT $error_type, '$object_type', {$object_type}_id, '$msgid', '$object_type', htmlspecialchars(k), htmlspecialchars(v), '$repl', NOW()
			FROM $table
			WHERE $where
		", $db2, false);
	}
}

?>