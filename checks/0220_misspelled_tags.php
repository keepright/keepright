<?php


/*
thesis: the majority is always right.
look for keys (and values) that are used very seldom (eg. less than 1/100000 of the whole number of tags)
and that are very similar to a well-known key (and value)
a well-known key (and value) is used more than 1/100000 of the whole number of tags times

in case of values this does make sense for tags with a limited number of different values only,
especially not for the name tag. This is handeled automatically: For the name tag no regular values
will be found so any name value will be ok
*/

// * uppercase keys are bad
// * characters used as fake namespace separator: |>
// * colons (:) at the end of a key are bad

global $false_positives, $never_complain_about, $force_irregular, $force_regular, $overrules;

// list of key or value parts that sound very similar but are something completely different
// please keep values in ascending order!
$false_positives = array(
	array('AB Kreisstraßen', 'AN Kreisstraßen', 'ANs Kreisstraßen', 'BA Kreisstraßen', 'BAs Kreisstraßen', 'BT Kreisstraßen', 'FÜ Kreisstraßen', 'KC Kreisstraßen', 'KG Kreisstraßen', 'KT Kreisstraßen', 'KU Kreisstraßen', 'N Kreisstraßen', 'SC Kreisstraßen', 'SW Kreisstraßen', 'SWs Kreisstraßen', 'WÜ Kreisstraßen', 'WÜs Kreisstraßen'),
	array('AND_a', 'AND_c', 'AND_f', 'AND_gf', 'AND_i', 'AND_o', 'AND_r', 'AND_w'),
	array('AND_nosr_p', 'AND_nosr_r'),
	array('area', 'arena'),
	array('beach', 'bench'),
	array('block', 'clock', 'lock'),
	array('biking', 'hiking'),
	array('Birke', 'Birne'),
	array('bump', 'hump'),
	array('cafe', 'cape', 'cave'),
	array('charge', 'change'),
	array('color', 'colour'),
	array('county', 'country'),
	array('county_code', 'country_code'),
	array('count', 'mount'),
	array('customer', 'customers'),		// both are widely used
	array('date', 'gate'),
	array('DE:rural', 'DK:rural'),
	array('DE:urban', 'DK:urban'),
	array('derail', 'detail', 'retail'),
	array('detention', 'retention'),	// both are valid values for key=basin
	array('diet', 'dist'),
	array('disable', 'disabled'),
	array('dock', 'lock'),
	array('door', 'moor'),
	array('drain', 'train'),
	array('Eiche', 'Esche'),
	array('EIIR', 'EVIIR', 'EVIIIR'),
	array('email', 'e-mail', 'mail'),
	array('fenced', 'fence'),
	array('food', 'ford', 'fork', 'foot', 'wood'),
	array('function', 'junction'),
	array('game', 'name'),
	array('garage', 'garages'),
	array('glass', 'grass'),
	array('gold', 'golf'),
	array('gone', 'zone'),
	array('good', 'goods', 'wood'),
	array('GR', 'VR'),
	array('gray', 'grey'),
	array('Grade I', 'Grade II'),
	array('Grade II*', 'Grade II'),
	array('hall', 'mall', 'wall'),
	array('height', 'weight'),
	array('hires', 'wires'),
	array('hotel', 'hostel', 'motel'),
	array('house', 'horse'),
	array('http', 'https'),
	array('Iraq', 'Iran'),
	array('lamp', 'ramp'),
	array('land', 'sand'),
	array('lane', 'line'),
	array('lanes', 'lines'),
	array('lawyer', 'layer'),
	array('icn_ref', 'lcn_ref', 'lwn_ref', 'loc_ref', 'ncn_ref', 'nwn_ref', 'rcn_ref', 'rwn_ref'),
	array('icn_name', 'lcn_name', 'lwn_name', 'loc_name', 'lock_name', 'ncn_name', 'nwn_name', 'rcn_name', 'rwn_name'),
	array('j-bar', 't-bar'),
	array('kebab', 'kebap'),
	array('lane', 'lanes'),
	array('Lecce', 'Lecco'),
	array('left', 'lift'),
	array('length', 'lengths'),
	array('levels', 'level'),
	array('light', 'right'),
	array('line', 'lines', 'link', 'mine', 'wine'),
	array('lock', 'rock'),
	array('lock_ref', 'loc_ref'),
	array('make', 'male', 'sale'),
	array('marked', 'marker', 'market'),
	array('marked_trail_red', 'marked_trail_ref'),
	array('marking', 'parking'),
	array('maxheight', 'maxweight'),
	array('min_weight', 'min_height'),
	array('next', 'text'),
	array('note', 'notes'),
	array('number', 'numbers'),
	array('power', 'tower'),
	array('reg_name', 'ref_name'),
	array('rail', 'trail'),
	array('roof', 'room', 'rooms'),
	array('route', 'routes'),
	array('salb', 'sale', 'salt'),
	array('service', 'services'),
	array('short', 'sport'),
	array('ship', 'show', 'shop', 'stop'),
	array('Sign at NE', 'Sign at E'),
	array('Sign at NW', 'Sign at W'),
	array('Sign at SE', 'Sign at E'),
	array('Sign at SW', 'Sign at W'),
	array('stair', 'stairs', 'star', 'stars'),
	array('start', 'stars'),
	array('sub_station','substation'),
	array('tell', 'toll'),
	array('tracks', 'trucks'),
	array('trail', 'train'),
	array('water_power', 'water_tower'),
	array('wikimedia', 'wikipedia'),
	array('wiki', 'wifi')
);



// keys that lead to diverse values. It doesn't ever make sense
// to compare these keys' values with each other.
// Most of these keys have numbers, dates or times as values. Comparing them is useless.
// this string will be used directly in an SQL WHERE clause,
// so you have to properly escape apos and adhere to the syntax convention!
$never_complain_about = "
	prefix LIKE '_Acres_:=%' OR
	prefix LIKE '_CODE_:=%' OR
	prefix LIKE '_HOJA_:=%' OR
	prefix LIKE '_REFCAT_:=%' OR
	prefix LIKE 'addr:alternatenumber:=%' OR
	prefix LIKE 'addr:conscriptionnumber:=%' OR
	prefix LIKE 'addr:full:=%' OR
	prefix LIKE 'addr:housenumber:=%' OR
	prefix LIKE 'addr:postcode:=%' OR
	prefix LIKE 'aims-id:=%' OR
	prefix LIKE 'AND_nosr_r:=%' OR
	prefix LIKE 'AND_a_nosr_r:=%' OR
	prefix LIKE 'atm_ref:=%' OR
	prefix LIKE 'bridge_ref:=%' OR
	prefix LIKE 'bus_routes:=%' OR
	prefix LIKE 'canvec:uuid:=%' OR
	prefix LIKE 'capacity:=%' OR
	prefix LIKE 'capacity:persons:=%' OR
	prefix LIKE 'code:=%' OR
	prefix LIKE 'collection_times:=%' OR
	prefix LIKE 'color:=%' OR
	prefix LIKE 'colour:=%' OR
	prefix LIKE 'comment:=%' OR
	prefix LIKE 'created_by:=%' OR
	prefix LIKE 'dcgis:propid:=%' OR
	prefix LIKE 'distance:=%' OR
	prefix LIKE 'fdot:ref:=%' OR
	prefix LIKE 'FDOT_ref:=%' OR
	prefix LIKE 'fixme:=%' OR
	prefix LIKE 'Fixme:=%' OR
	prefix LIKE 'FIXME:=%' OR
	prefix LIKE 'garmin_type:=%' OR
	prefix LIKE 'generator:model:=%' OR
	prefix LIKE 'generator:output:electricity:=%' OR
	prefix LIKE 'generator:output:hot_water:=%' OR
	prefix LIKE 'generator:output:hot_air:=%' OR
	prefix LIKE 'generator:output:cold_water:=%' OR
	prefix LIKE 'generator:output:cold_air:=%' OR
	prefix LIKE 'generator:output:compressed_air:=%' OR
	prefix LIKE 'generator:output:steam:=%' OR
	prefix LIKE 'generator:output:vacuum:=%' OR
	prefix LIKE 'generator:output:battery_charging:=%' OR
	prefix LIKE 'gns_classification:=%' OR
	prefix LIKE 'gns:category:=%' OR
	prefix LIKE 'gns:dsg:=%' OR
	prefix LIKE 'gns:DSG:=%' OR
	prefix LIKE 'GNS:dsg_code:=%' OR
	prefix LIKE 'gns:MGRS:=%' OR
	prefix LIKE 'grades:=%' OR
	prefix LIKE 'history:=%' OR
	prefix LIKE 'id:db_shelter:=%' OR
	prefix LIKE 'image:=%' OR
	prefix LIKE 'ims:frequency:=%' OR
	prefix LIKE 'int_ref:=%' OR
	prefix LIKE 'IOM_project_DRR:form_number:=%' OR
	prefix LIKE 'isced:level:=%' OR
	prefix LIKE 'kern:Comb_Zn:=%' OR
	prefix LIKE 'line:=%' OR
	prefix LIKE 'lines:=%' OR
	prefix LIKE 'loc_ref:=%' OR
	prefix LIKE 'massgis:BASE_MAP:=%' OR
	prefix LIKE 'massgis:MANAGR_ABR:=%' OR
	prefix LIKE 'massgis:OWNER_ABRV:=%' OR
	prefix LIKE 'massgis:PROJ_ID:=%' OR
	prefix LIKE 'massgis:SOURCE_MAP:=%' OR
	prefix LIKE 'maxheight:=%' OR
	prefix LIKE 'maxwidth:=%' OR
	prefix LIKE 'maxspeed:=%' OR
	prefix LIKE 'maxspeed:conditional:=%' OR
	prefix LIKE 'maxspeed:lanes:=%' OR
	prefix LIKE 'MGRS:=%' OR
	prefix LIKE 'minspeed:=%' OR
	prefix LIKE 'minspeed:conditional:=%' OR
	prefix LIKE 'minspeed:lanes:=%' OR
	prefix LIKE 'MP_TYPE:=%' OR
	prefix LIKE 'ncn_ref:=%' OR
	prefix LIKE 'nhd:fdate:=%' OR
	prefix LIKE 'note:=%' OR
	prefix LIKE 'old_ref:=%' OR
	prefix LIKE 'opening_hours:=%' OR
	prefix LIKE 'power_rating:=%' OR
	prefix LIKE 'phone:=%' OR
	prefix LIKE 'nat_ref:=%' OR
	prefix LIKE 'nhd-shp:fdate:=%' OR
	prefix LIKE 'OCUPANTES:=%' OR
	prefix LIKE 'old_ref_legislative:=%' OR
	prefix LIKE 'osak:identifier:=%' OR
	prefix LIKE 'P_S_COMM:=%' OR
	prefix LIKE 'pcode:=%' OR
	prefix LIKE 'PFM:garmin_type:=%' OR
	prefix LIKE 'photo_url:=%' OR
	prefix LIKE 'ref:%' OR
	prefix LIKE 'ref:isil:=%' OR
	prefix LIKE 'ref_catastral:=%' OR
	prefix LIKE 'ref_no:=%' OR
	prefix LIKE 'ref_num:=%' OR
	prefix LIKE 'reg_ref:=%' OR
	prefix LIKE 'roof:color:=%' OR
	prefix LIKE 'roof:colour:=%' OR
	prefix LIKE 'route_ref:=%' OR
	prefix LIKE 'seats:=%' OR
	prefix LIKE 'source:=%' OR
  prefix LIKE 'source:%' OR
	(prefix LIKE 'source:name:=%' AND k LIKE 'Orange County plat book%') OR
	(prefix LIKE 'source:old_name:=%' AND k LIKE 'Orange County plat book%') OR
	prefix LIKE 'source_ref:=%' OR
	prefix LIKE 'source_ref:bicycle:=%' OR
	prefix LIKE 'source_ref:cycleway:=%' OR
	prefix LIKE 'source_ref:hgv:=%' OR
	prefix LIKE 'source_ref:name:=%' OR
	prefix LIKE 'source_ref:maxheight:=%' OR
	prefix LIKE 'source_ref:maxspeed:=%' OR
	prefix LIKE 'source_ref:maxspeed:towing:=%' OR
	prefix LIKE 'source_ref:maxspeed:advisory:=%' OR
	prefix LIKE 'sourcedb:id:=%' OR
	prefix LIKE 'statscan:rbuid:=%' OR
	prefix LIKE 'strassen-nrw:abs:=%' OR
	prefix LIKE 'tiger:cfcc:=%' OR
	prefix LIKE 'tiger:source:=%' OR
	prefix LIKE 'tracktype:=%' OR
	prefix LIKE 'traffic_sign:=%' OR
	prefix LIKE 'ts_codigo:=%' OR
	prefix LIKE 'UNIDAD_MAN:=%' OR
	prefix LIKE 'waterway:kilometer:=%' OR
	prefix LIKE 'website:=%' OR
	prefix LIKE 'width:=%' OR
	prefix LIKE 'zhb_code:=%'
";



// sometimes the misspelled tags come more often than the regular tag
// use these lists to correct these cases.
// please note the special notation:
// always specify the complete key starting from the beginning until the level
// you want to match. Consider the equals sign a level on its own in the 
// hierarchy and delimit it with a colon just like all other levels

$force_irregular = array(
	'access:conditionnal',
	'building:=:appartments',
	'building:=:appartment',
	'building:=:apartment',
	'building:=:farm_auxillary',
	'building:=:hanger',
	'gague:',
	'usability:skate:=:excelent',
	'name:botanical:=:Cupressus sempervires',
	'note_:'
);

$force_regular = array(
	'access:conditional',
	'addr:province:=:British Columbia',
	'brand:=:Agip',
	'brand:=:Esso',
	'brand:=:Metano',
	'brand:=:Shell',
	'brand:=:Tamoil',
	'brand:=:Total',
	'building:=:apartments',
	'building:=:hangar',
	'building:=:hotel',
	'gauge:',
	'geometry_source_type:=:Walking Papers/Misson GPS',
	'gnis:county_name:=:Cheyboygan',
	'lengths:',
	'lengths:left:',
	'lengths:right:',
	'man_made:=:cutline',
	'name:botanical:=:Cupressus sempervirens',
	'usability:skate:=:excellent',
);

// for known typos with more than one character wrong use this:
// bad prefix, bad key, right prefix, right key
$overrules = array (
	array('building:=', 'farm_auxcillary', 'building:=', 'farm_auxiliary')
);


check_tags("node");
check_tags("way");
check_tags("relation");




// This is a completely different story:
// look for tags where the key is "key"
$tables = array('node'=>'node_tags', 'way'=>'way_tags', 'relation'=>'relation_tags');

// this loop will execute similar queries for all three *_tags tables
foreach ($tables as $object_type=>$table) {

	query("
		INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
		SELECT $error_type+1, '$object_type', {$object_type}_id, 'The key of this ${object_type}''s tag is ''key'': $2', '${object_type}', array_to_string(array(
			SELECT '\"' || COALESCE(k,'') || '=' || COALESCE(v,'') || '\"'
			FROM $table AS tmp
			WHERE tmp.{$object_type}_id=t.{$object_type}_id AND (tmp.k='key')
		), ', '), NOW()

		FROM $table t
		WHERE k='key'
		GROUP BY {$object_type}_id
	", $db1);
}
// (end of completely different story)






function check_tags($item) {
global $error_type, $false_positives, $db1, $db2;
	query("DROP TABLE IF EXISTS _tmp_tags", $db1, false);
	query("
		CREATE TABLE _tmp_tags(
			k text NOT NULL,
			keylist text[],
			v text,
			k_orig text,
			v_orig text,
			tag_count bigint
		)
	", $db1, false);
	query("CREATE INDEX idx_tmp_tags ON _tmp_tags (keylist)", $db1, false);

	// split key names by each colon into an array and append the value as if it belonged to the key
	// replace any number with 0 (completely remove numbers from keys to make eg. "name2" and "name" the same.
	// Searching for differences on numbers in values makes no sense.
	// regex matches numbers plus optionally some characters if they are followed by another number
	// need to remember the key and value as it once was to be able to find the keys later on

	// please note that "dollar quoting" ($$some string$$) is used to separate the regex strings
	// in order to avoid duplicating backslashes. This is PostgreSQL syntax.
	query("
		INSERT INTO _tmp_tags(k, keylist, v, k_orig, v_orig, tag_count)
		SELECT k, regexp_split_to_array(k, ':') || ARRAY['='] || regexp_split_to_array(v, ':') || ARRAY[''],
			v, k_orig, v_orig, COUNT(id) as tag_count
		FROM (
			SELECT regexp_replace(k, $$[0-9]+([ \\.+/\\(\\)-]+[0-9]+)*$$, '', 'g') AS k,
			regexp_replace(v, $$[0-9]+([ \\.+/\\(\\)-]+[0-9]+)*$$, '0', 'g') AS v,
			T.k AS k_orig, T.v as v_orig, ${item}_id AS id
			FROM ${item}_tags T
		) AS tags
		WHERE LENGTH(k)>3
		GROUP BY k, v, k_orig, v_orig
	", $db1);
	query("ANALYZE _tmp_tags", $db1, false);

	// collection of bad k,v pairs that will be joined with *_tags to identify object ids
	query("DROP TABLE IF EXISTS _tmp_bad_tags", $db1, false);
	query("
		CREATE TABLE _tmp_bad_tags(
			k text NOT NULL,
			v text,
			wrong_tag text,
			right_tag text
		)
	", $db1, false);
	query("CREATE INDEX idx_tmp_bad_tags ON _tmp_bad_tags (k, v)", $db1, false);


	for ($keylen=1; $keylen<6; $keylen++) {

		logger("------     $item -- $keylen");
		query("DROP TABLE IF EXISTS _tmp_keys", $db1, false);
		query("
			CREATE TABLE _tmp_keys(
				prefix text NOT NULL,
				k text NOT NULL,
				tag_count bigint,
				UNIQUE (prefix, k)
			)
		", $db1, false);


		query("TRUNCATE TABLE _tmp_keys", $db1, false);

		// select the prefix (the first $keylen parts of the key name) plus the $keylength's part extra
		// if that part has at least 4 and at max. 50 chars of length
		query("
			INSERT INTO _tmp_keys (prefix, k, tag_count)
			SELECT array_to_string(keylist[1:$keylen-1], ':') as prefix, keylist[$keylen] as postfix, SUM(tag_count)
			FROM _tmp_tags
			WHERE array_upper(keylist, 1)>=$keylen AND
			LENGTH(keylist[$keylen]) BETWEEN 4 AND 50
			GROUP BY prefix, postfix
		", $db1);
		query("ANALYZE _tmp_keys", $db1, false);

		$offending_keys = find_offending_keys($db1, $item, $keylen);
		//print_r($offending_keys);


		foreach ($offending_keys as $irreg_prefix=>$irreg_keys) foreach($irreg_keys as $irreg_key=>$reg_keys){

			// skip known false-positives
			if (found_in($reg_keys[1], $irreg_key, $false_positives)) {
				//echo "$item -- false positive -- '$irreg_key' looks like '$reg_key'\n";
				continue;
			}

			logger("$item '$irreg_prefix:$irreg_key' looks like '${reg_keys[0]}:${reg_keys[1]}'");

			// find all original tags, where the modified tag version is the offending irregular key
			// different original tags fall into the same modified tag by regexing, this the way back
			query("
				INSERT INTO _tmp_bad_tags (k, v, wrong_tag, right_tag)
				SELECT DISTINCT k_orig, v_orig,
				'\"" . pg_escape_string($db1, $irreg_key) . "\"', '\"" . pg_escape_string($db1, $reg_keys[1]) . "\"'
				FROM _tmp_tags
				WHERE keylist[1:$keylen] = ARRAY[" . (strlen($irreg_prefix)>0 ?  "'".str_replace(':', "','", pg_escape_string($db1, $irreg_prefix)) . "'," : '') . " '" . pg_escape_string($db1, $irreg_key) . "']
			", $db1, false);

		}
	}

	// now find out the object ids, where bad tags were used
	query("
		INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, txt2, txt3, txt4, txt5, last_checked)
		SELECT DISTINCT $error_type, CAST('$item' AS type_object_type), t.${item}_id,
		'This $1 is tagged ''$2=$3'' where $4 looks like $5', '$item', htmlspecialchars(t.k), htmlspecialchars(t.v), htmlspecialchars(bt.wrong_tag), htmlspecialchars(bt.right_tag), NOW()
		FROM ${item}_tags t INNER JOIN _tmp_bad_tags bt USING (k, v)
	", $db1);

}

// looks for needles in haystack of stacks.
// haystack is an array of arrays. Needles are searched in the values of each sub-array
// function will return true if both needles are found in the same sub-array
function found_in($needle1, $needle2, $haystack) {
	if (!is_array($haystack)) return false;

	foreach ($haystack as $stack)
		if (in_array($needle1, $stack) && in_array($needle2, $stack))
			return true;

	return false;
}

// find keys that are rarely used and that are very similar to keys that are used very often
function find_offending_keys($db1, $item, $keylen) {
global $never_complain_about, $force_irregular, $force_regular, $overrules;

	//find regular tags (i.e. tags that are used very frequently, currently at least 1/100000 of the whole number of tags)
	$tag_count_limit = query_firstval("SELECT SUM(tag_count) FROM _tmp_keys", $db1, false) / 100000;
	if ($tag_count_limit<50) $tag_count_limit=50;
	logger("tag count limit is $tag_count_limit");

	// tags like eg. the name tag do have many different value options. These many options
	// may be close together but this is no error or at least there would be many false-positives.
	// So we ignore key prefixes with many different values
	$count = query_firstval("SELECT COUNT(*) FROM _tmp_keys", $db1, false);
	$tag_diversity_limit = sqrt($count);
	logger("count is $count, tag diversity limit is $tag_diversity_limit");

	$result=query("
		SELECT prefix, k, tag_count
		FROM _tmp_keys
		WHERE (
			prefix NOT IN (
				SELECT prefix
				FROM _tmp_keys
				GROUP BY prefix
				HAVING COUNT(k)>$tag_diversity_limit
			)
			AND NOT ($never_complain_about)
		) OR prefix IS NULL OR prefix=''
	", $db1, false);

	while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

		if (in_array($row['prefix'] .':'. $row['k'], $force_irregular)) {	// force to irreg?
			$irregulars[] = array($row['prefix'], $row['k']);

		} else if (in_array($row['prefix'] .':'. $row['k'], $force_regular)) {// force to regular?
			$regulars[] = array($row['prefix'], $row['k']);

		} else if ($row['tag_count']>=$tag_count_limit) {	// let numbers decide
			$regulars[] = array($row['prefix'], $row['k']);
		} else
			$irregulars[] = array($row['prefix'], $row['k']);
	}
	pg_free_result($result);

	//echo "REGULARS:\n"; print_r($regulars);
	//echo "IR-REGULARS:\n"; print_r($irregulars);
	$offending_keys=array();
	// compare each pair of irregular and regulars.
	// remember a pair if the difference is exactly one character
	if (is_array($irregulars) && is_array($regulars)) {
		foreach ($irregulars as $irreg_key) {

			// lookup overrules
			foreach ($overrules as $o) {
				list($bad_prefix, $bad_key, $right_prefix, $right_key)=$o;

				if ($irreg_key[0]==$bad_prefix && $irreg_key[1]==$bad_key) {
					$offending_keys[$irreg_key[0]][$irreg_key[1]]=array($right_prefix, $right_key);
					continue 2;		// skip next block
				}					
			}

			// without overrules: lookup similar regulars for the irregular key (one character difference)
			foreach ($regulars as $reg_key) {
				// identical prefix plus exactly one character differently...
				if (($irreg_key[0]==$reg_key[0]) && levenshtein($irreg_key[1], $reg_key[1])<=1) {
					//printf ("%30s - %s\n", $irreg_key, $reg_key);
					$offending_keys[$irreg_key[0]][$irreg_key[1]]=array($reg_key[0], $reg_key[1]);
				}
			}
		}
	}
	//echo "OFFENDING KEYS:\n"; print_r($offending_keys);
	return $offending_keys;
}


print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_tags", $db1, false);
query("DROP TABLE IF EXISTS _tmp_keys", $db1, false);
query("DROP TABLE IF EXISTS _tmp_bad_tags", $db1, false);

?>
