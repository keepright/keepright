<?php

// find ways/nodes/relations that use tags
// listed on the wiki's deprecated features page

// list copied from http://wiki.openstreetmap.org/index.php/Deprecated_features
//"Date", "Deprecated Key", "Deprecated Value", "Replaced by", "Reason")
$replacement_list = array(

  // insert items sorted by "Deprecated Key", "Deprecated Value"

  /*
  // removed at 2009-07-20 because of a discussion on talk list
  array("2007-07-16", "landuse", "wood", "landuse=forest", "?"),
  */
  array("2007-07-16", "abutters", "*", "landuse=* around the general area and abutters=* along street-front exceptions. ", "abutters doesn't indicate how far from the road the land use extends, so when you have imagery, consider using landuse instead. However, abutters is still useful for marking street-frontage exceptions to a general rule imposed by landuse."),
  array("2007-07-13", "amenity", "bakers", "shop=bakery ", "Shop introduced"),
  array("2007-07-13", "amenity", "butchers", "shop=butcher ", "Shop introduced"),
  array("2007-07-13", "amenity", "candle_stick_makers", "shop=chandler ", "Shop introduced"),
  array("2015-02", "amenity", "dog_bin", "amenity=waste_basket + waste=dog_excrement or amenity=vending_machine + vending=excrement_bags", "Taken from Category:Tag_descriptions_with_status_'deprecated'"),
  array("2015-02", "amenity", "dog_waste_bin", "amenity=waste_basket + waste=dog_excrement or amenity=vending_machine + vending=excrement_bags", "Taken from Category:Tag_descriptions_with_status_'deprecated'"),
  array("2014-07", "amenity", "emergency_phone", "emergency=phone", "WikiProject Emergency Cleanup"),
  array("2015-05-13", "amenity", "ev_charging", "amenity=charging_station", "The latter tag is more expressive than the former."),
  array("", "amenity", "hotel", "tourism=hotel", "better fits into toplevel tags"),
  array("2007-07-16", "amenity", "shop", "shop=* ", "Shop introduced"),
  array("2009-02-12", "amenity", "signpost", "information=guidepost"),
  array("2007-07-16", "amenity", "store", "shop=* ", "Shop introduced"),
  array("2015-04", "amenity", "sauna", "leisure=sauna", "Taken from Category:Tag_descriptions_with_status_'deprecated'. Tagging as 'leisure' has been proposed, while 'amenity' has been marked as deprecated in the same time."),
  array("2007-07-13", "amenity", "supermarket", "shop=supermarket ", "Shop introduced"),
  array("2014-10-20", "amenity", "winery", "shop=winery or craft=winery", "Taken from Category:Tag_descriptions_with_status_'deprecated'"),
  array("2015-11-07", "amenity", "youth_centre", "amenity=community_centre + community_centre:for=juvenile or community_centre:for=child;juvenile", "This is more expressive and more flexible."),
  array("2012-01-19", "bicycle_parking", "sheffield", "bicycle_parking=stand", "No real difference and sheffield is only used in UK, see Talk:Key:bicycle parking"),
  array("2013-10-06", "bridge", "arch", "bridge=yes + bridge:structure=arch", "Proposed features/Bridge types"),
  array("2013-10-06", "bridge", "beam", "bridge=yes + bridge:structure=beam", "Proposed features/Bridge types"),
  array("2013-10-06", "bridge", "humpback", "bridge=yes + bridge:structure=humpback", "Proposed features/Bridge types"),
  array("2013-10-06", "bridge", "lift", "bridge=movable + bridge:movable=lift", "Proposed features/Bridge types"),
  array("2013-10-06", "bridge", "pontoon", "bridge=yes + bridge:structure=floating", "Proposed features/Bridge types"),
  array("2013-10-06", "bridge", "suspension", "bridge=yes + bridge:structure=suspension", "Proposed features/Bridge types"),
  array("2013-10-06", "bridge", "swing", "bridge=movable + bridge:movable=swing or bridge=yes + bridge:structure=simple-suspension", "Proposed features/Bridge types - was formerly used to map two very different bridge types (hanging/swinging rope bridges and movable swing bridges)"),
  array("2011-10-27", "building", "entrance", "entrance=*", "Cleaning the semantics of the \"building\" tag; entrance=* approved (see Proposed_features/entrance)."),
  array("", "building:type", "*", "building=*", "Proposed features/Building attributes has been abandoned."),
  /*
  // removed at 2010-02-23 because class=free is used for
  // wlan access points nowadays.
  array("2006", "class", "*", "highway=* ", "highway introduced"),
  */
  array("2012-11-28", "color", "*", "colour=*", "Usage of American form color probably mainly due to a bug in JOSM presets."),
  array("2013-10-22", "date_off", "*", "Conditional_restrictions or opening_hours=*", "This is more expressive and more flexible."),
  array("2013-10-22", "date_on", "*", "Conditional_restrictions or opening_hours=*", "This is more expressive and more flexible."),
  array("2013-10-22", "day_off", "*", "Conditional_restrictions or opening_hours=*", "This is more expressive and more flexible."),
  array("2013-10-22", "day_on", "*", "Conditional_restrictions or opening_hours=*", "This is more expressive and more flexible."),
  array("2014-05-01", "drinkable", "*", "drinking_water=*", "There is no need for redundant tags. One key is sufficient."),
  array("2012-09-14", "escalator", "*", "highway=steps + conveying=*", "Proposed_features/Escalators_and_Moving_Walkways"),
  array("2007", "highway", "bridge", "highway=* + bridge=*", "any type of highway can cross a bridge."),
  array("2009-08-1", "highway", "byway", "highway=path or highway=track etc plus designation=byway_open_to_all_traffic or designation=restricted_byway", "More accurate tagging for Public rights of way in England and Wales. "),
  array("2008-10-19", "highway", "cattle_grid", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
  array("2012-01-19", "highway", "ford", "ford=*", ""),
  array("2008-10-19", "highway", "gate", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
  array("2008-06-12", "highway", "incline", "incline=*", "Any type of highway=* (primary, secondary, ...) may have inclined sections tagged separately, this does not create a new type of highway"),
  array("2008-06-12", "highway", "incline_steep", "incline=*", "Any type of highway=* (primary, secondary, ...) may have inclined sections tagged separately, this does not create a new type of highway"),
  array("2007", "highway", "minor", "highway=* (whatever fits: tertiary, unclassified, service, ...)", ""),
  array("2014-09-21", "highway", "no", "No tags", "Taken from Category:Tag_descriptions_with_status_'deprecated'. See DE:Tag:highway=no."),
  array("2008-10-19", "highway", "stile", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
  array("2008-10-19", "highway", "toll_booth", "barrier=*", "Their 'concept' better fits in this new key and highway gets a bit uncluttered.(voted)"),
  array("2008-06-12", "highway", "viaduct", "bridge=*"),
  array("2008-03-19", "highway", "unsurfaced", "highway=*+surface=unpaved or highway=track", "any highway classification can be unsurfaced."),
  array("2008-01-10", "historic", "icon", "?", "no description, usage completely unknown"),
  array("2008-01-20", "historic", "museum", "tourism=museum ", "better fits into toplevel tags"),
  array("2014-10-19", "historic_name", "*", "old_name=* or name=*", "Taken from Category:Key_descriptions_with_status_'deprecated'."),
  array("2013-10-22", "hour_off", "*", "Conditional_restrictions or opening_hours=*", "This is more expressive and more flexible."),
  array("2013-10-22", "hour_on", "*", "Conditional_restrictions or opening_hours=*", "This is more expressive and more flexible."),
  array("", "landuse", "farm", "landuse=farmland or landuse=farmyard", "Taken from Category:Tag_descriptions_with_status_'deprecated'. Originally designed to mark agricultural zones, this tag has confused many contributors, who tagged also buildings with it."),
  array("", "landuse", "field", "landuse=farmland", "Proposed features/agricultural Field#Conclusion?"),
  array("2011-05-09", "landuse", "pond", "natural=water + water=pond", "Even though this tag is widely used, it has been deprecated by Proposed_features/Water_details. Both tagging schemes should persist for some time."),
  array("2007-07-16", "landuse", "wood", "landuse=forest or natural=wood", "Was intended to depend on whether the woodland/forest was 'managed' or 'primaeval', but also see Forest for more discussion about the problems here."),
  array("2016-05-29", "leaf_type", "broad_leaved", "leaf_type=broadleaved", "see http://wiki.openstreetmap.org/wiki/Tag:leaf_type%3Dbroadleaved"),  
  array("2016-05-29", "leaf_type", "broad_leafed", "leaf_type=broadleaved", "see http://wiki.openstreetmap.org/wiki/Tag:leaf_type%3Dbroadleaved"),  
  array("2014-03-04", "leisure", "ski_playground", "piste:type=playground", "Piste_Maps#Pistes and Talk:Piste_Maps#Approval"),
  array("2014-01-27", "leisure", "video_arcade", "leisure=adult_gaming_centre for venues with gambling machines; or leisure=amusement_arcade for venues with pay-to-play games.", "Proposed_features/Gambling"),
  array("2013-05-23", "man_made", "jetty", "man_made=pier", "Tag whose definition remains unclear. man_made=pier is way more widely used."),
  array("2008-05-30", "man_made", "power_fossil", "power=generator and power_source=*"),
  array("2008-05-30", "man_made", "power_hydro", "power=generator and power_source=hydro"),
  array("2008-05-30", "man_made", "power_nuclear", "power=generator and power_source=nuclear"),
  array("2008-05-30", "man_made", "power_wind", "power=generator and power_source=wind"),
  array("2015-03-19", "name:botanical", "*", "species=*", "Taken from Category:Key_descriptions_with_status_'deprecated'."),
  array("2011-10-27", "natural", "land", "type=multipolygon (islands in lakes and rivers) or natural=coastline (islands in the sea)", "Use Relations/Multipolygon for islands in lakes and rivers, natural=coastline for islands in the sea."),
  array("2008-11-18", "natural", "marsh", "natural=wetland, wetland=*"),
  array("2009-08-08", "noexit", "no", "fixme=Continue", "If there is an exit... it should be mapped!"),
  array("2012-12-08", "place_name", "*", "name=*", "Although it made sense in the past, nowadays it completely overlaps with name=*. More info here."),
  /*
  // removed at 2013-3-29 because http://wiki.openstreetmap.org/wiki/Key:power list it as valid
  array("2013-01-24", "power", "station", "power=sub_station", "'power=station' may be confused with 'power station' (= power plant) and is never used as a synonym for substation in English.")
  */
  array("2010-09", "power_rating", "*", "generator:output=*", "Proposed features/generator rationalisation"),
  array("2010-09", "power_source", "*", "generator:source=*", "Proposed features/generator rationalisation"),
  array("2014-10-24", "railway", "preserved", "historic=railway + railway=rail (or whatever type) + railway:preserved=yes", "See OpenRailwayMap/Aktiventreffen_2014_2#Tracks. Clashes with railway=narrow_gauge when such railways are preserved."),
  array("2007-07-16", "railway", "preserved_rail", "railway=preserved ", "the '_rail' suffix is redundant"),
  array("2008-06-12", "railway", "viaduct", "bridge=*"),
  array("2008-05-30", "route", "ncn", "Cycle_routes: http://wiki.openstreetmap.org/index.php/Cycle_routes"),
  array("2014-01-27", "shop", "betting", "shop=bookmaker or shop=lottery See also Tag:shop=betting", "Proposed_features/Gambling"),
  array("2010-06-06", "shop", "fish", "shop=seafood (see also Tag:shop=fish)", "Proposed_features/seafood_shop"),
  array("2010-06-06", "shop", "fishmonger", "shop=seafood", "Proposed_features/seafood_shop"),
  array("2014-01-27", "shop", "gambling", "amenity=gambling (see also Tag:shop=gambling)", "Proposed_features/Gambling"),
  array("2012-02-11", "shop", "organic", "shop=* + organic=*", "Taken from Category:Tag_descriptions_with_status_'deprecated'. See also Talk:Tag:shop=organic."),
  array("2010-08-25", "sport", "gaelic_football", "sport=gaelic_games + specific tag of gaelic game", "Taken from Category:Tag_descriptions_with_status_'deprecated'."),
  array("2014-05-28", "tourism", "bed_and_breakfast", "tourism=guest_house + guest_house=bed_and_breakfast", "Taken from Category:Tag_descriptions_with_status_'deprecated'"),
  array("2016-05-29", "type", "broad_leaved", "leaf_type=broadleaved", "see http://wiki.openstreetmap.org/wiki/Tag:leaf_type%3Dbroadleaved"), 
  array("2016-05-29", "type", "broad_leafed", "leaf_type=broadleaved", "see http://wiki.openstreetmap.org/wiki/Tag:leaf_type%3Dbroadleaved"), 
  array("2008-03-19", "waterway", "mooring", "mooring=yes ", "More flexibility; allows us to keep original value of waterway=."),
  array("2010-05-10", "waterway", "rapids", "whitewater sports", "Do not make any automated edit for this: read WikiProject Whitewater Maps."),
  array("2008-03-19", "waterway", "waste_disposal", "amenity=waste_disposal ", "Something this general should be in amenity."),
  array("2008-03-21", "waterway", "water_point", "amenity=drinking_water ", "Access to drinking water is not limited to waterways."),
  array("2014-06-06", "wood", "*", "leaf_type=*", "The values wood=coniferous and wood=deciduous are ambiguous. It was approved to use leaf_type=* instead.")
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