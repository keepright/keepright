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

// list of key or value parts that sound very similar but are something completely different
// please keep values in ascending order!
$false_positives = array(
	array('AND_a', 'AND_c', 'AND_f', 'AND_gf', 'AND_i', 'AND_o', 'AND_r', 'AND_w'),
	array('AND_nosr_p', 'AND_nosr_r'),
	array('area', 'arena'),
	array('beach', 'bench'),
	array('block', 'lock'),
	array('biking', 'hiking'),
	array('Birke', 'Birne'),
	array('bump', 'hump'),
	array('cafe', 'cave'),
	array('color', 'colour'),
	array('county', 'country'),
	array('DE:rural', 'DK:rural'),
	array('DE:urban', 'DK:urban'),
	array('derail', 'detail', 'retail'),
	array('door', 'moor'),
	array('Eiche', 'Esche'),
	array('EIIR', 'EVIIR', 'EVIIIR'),
	array('email', 'e-mail', 'mail'),
	array('food', 'ford', 'foot', 'wood'),
	array('function', 'junction'),
	array('game', 'name'),
	array('garage', 'garages'),
	array('good', 'wood'),
	array('GR', 'VR'),
	array('hall', 'mall', 'wall'),
	array('height', 'weight'),
	array('hires', 'wires'),
	array('hotel', 'hostel', 'motel'),
	array('land', 'sand'),
	array('lanes', 'lines'),
	array('lawyer', 'layer'),
	array('icn_ref', 'lcn_ref', 'lwn_ref', 'loc_ref', 'ncn_ref', 'nwn_ref', 'rcn_ref', 'rwn_ref'),
	array('icn_name', 'lcn_name', 'lwn_name', 'loc_name', 'ncn_name', 'nwn_name', 'rcn_name', 'rwn_name'),
	array('j-bar', 't-bar'),
	array('kebab', 'kebap'),
	array('left', 'lift'),
	array('levels', 'level'),
	array('light', 'right'),
	array('line', 'link', 'mine', 'wine'),
	array('marked', 'marker', 'market'),
	array('marked_trail_red', 'marked_trail_ref'),
	array('maxheight', 'maxweight'),
	array('next', 'text'),
	array('note', 'notes'),
	array('number', 'numbers'),
	array('power', 'tower'),
	array('rail', 'trail'),
	array('salb', 'sale', 'salt'),
	array('service', 'services'),
	array('short', 'sport'),
	array('ship', 'show', 'shop', 'stop'),
	array('tell', 'toll'),
	array('trail', 'train'),
	array('water_power', 'water_tower'),
	array('wikimedia', 'wikipedia'),
);

// keys that lead to diverse values. It doesn't ever make sense
// to compare these keys' values with each other.
// Most of these keys have numbers, dates or times as values. Comparing them is useless.
// this string will be used in an SQL 'column NOT IN (...)' clause,
// so you have to properly escape apos!
// there is already an exception for any 'tiger'-prefixed tag in action so you needn't add one here
$never_complain_about = "(
	'addr:alternatenumber:=',
	'addr:conscriptionnumber:=',
	'addr:full:=',
	'addr:housenumber:=',
	'aims-id:=',
	'AND_nosr_r:=',
	'AND_a_nosr_r:=',
	'atm_ref:=',
	'canvec:uuid:=',
	'collection_times:=',
	'bridge_ref:=',
	'bus_routes:=',
	'created_by:=',
	'distance:=',
	'FDOT_ref:=',
	'garmin_type:=',
	'generator:output:electricity:=',
	'generator:output:hot_water:=',
	'generator:output:hot_air:=',
	'generator:output:cold_water:=',
	'generator:output:cold_air:=',
	'generator:output:compressed_air:=',
	'generator:output:steam:=',
	'generator:output:vacuum:=',
	'generator:output:battery_charging:=',
	'gns_classification:=',
	'gns:category:=',
	'gns:dsg:=',
	'gns:DSG:=',
	'GNS:dsg_code:=',
	'gns:MGRS:=',
	'grades:=',
	'history:=',
	'image:=',
	'ims:frequency:=',
	'int_ref:=',
	'line:=',
	'lines:=',
	'massgis:BASE_MAP:=',
	'massgis:MANAGR_ABR:=',
	'massgis:OWNER_ABRV:=',
	'massgis:PROJ_ID:=',
	'massgis:SOURCE_MAP:=',
	'maxspeed:=',
	'MGRS:=',
	'MP_TYPE:=',
	'opening_hours:=',
	'power_rating:=',
	'phone:=',
	'nat_ref:=',
	'nhd-shp:fdate:=',
	'OCUPANTES:=',
	'osak:identifier:=',
	'PFM:garmin_type:=',
	'ref:isil:=',
	'ref_no:=',
	'ref_num:=',
	'route_ref:=',
	'source:=',
	'source_ref:=',
	'source_ref:name:=',
	'statscan:rbuid:=',
	'strassen-nrw:abs:=',
	'UNIDAD_MAN:=',
	'website:='
)";



// sometimes the misspelled tags come more often than the regular tag
// use these lists to correct these cases.
// please note the special notation!

$force_irregular = array(
	'usability:skate:=excelent',
	'note_',
	'Public'
);

$force_regular = array(
	'brand:=Esso',
	'brand:=Shell',
	'brand:=Tamoil',
	'brand:=Total',
	'usability:skate:=excellent',
	'geometry_source_type:=Walking Papers/Misson GPS'
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
		SELECT k, regexp_split_to_array(k, ':') || ARRAY['=', v, ''], v, k_orig, v_orig, COUNT(id) as tag_count
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

		echo "---------------------------------------------------\n$item -- $keylen\n";
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

			echo "$item '$irreg_prefix:$irreg_key' looks like '${reg_keys[0]}:${reg_keys[1]}'\n";

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
		'This $1 is tagged ''$2=$3'' where $4 looks like $5', '$item', t.k, t.v, bt.wrong_tag, bt.right_tag, NOW()
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
global $never_complain_about, $force_irregular, $force_regular;

	//find regular tags (i.e. tags that are used very frequently, currently at least 1/100000 of the whole number of tags)
	$tag_count_limit = query_firstval("SELECT SUM(tag_count) FROM _tmp_keys", $db1, false) / 100000;
	if ($tag_count_limit<50) $tag_count_limit=50;
	echo "tag count limit is $tag_count_limit\n";

	// tags like eg. the name tag do have many different value options. These many options
	// may be close together but this is no error or at least there would be many false-positives.
	// So we ignore key prefixes with many different values
	$count = query_firstval("SELECT COUNT(*) FROM _tmp_keys", $db1, false);
	$tag_diversity_limit = sqrt($count);
	echo "count is $count, tag diversity limit is $tag_diversity_limit\n";

	$result=query("
		SELECT prefix, k, tag_count
		FROM _tmp_keys
		WHERE (prefix NOT IN (
			SELECT prefix
			FROM _tmp_keys
			GROUP BY prefix
			HAVING COUNT(k)>$tag_diversity_limit
		)
		AND prefix NOT IN $never_complain_about
		AND prefix NOT LIKE 'tiger:%'
		) OR prefix IS NULL or prefix=''
	", $db1, false);

	while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {

		if (in_array($row['prefix'] . $row['k'], $force_irregular)) {	// force to irreg?
			$irregulars[] = array($row['prefix'], $row['k']);

		} else if (in_array($row['prefix'] . $row['k'], $force_regular)) {// force to regular?
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
			foreach ($regulars as $reg_key) {
				// identical prefix plus exactly one character differently...
				if (($irreg_key[0]==$reg_key[0]) && levenshtein($irreg_key[1], $reg_key[1])<=1) {
					//printf ("%30s - %s\n", $irreg_key, $reg_key);
					$offending_keys[$irreg_key[0]][$irreg_key[1]]=array($reg_key[0], $reg_key[1]);
				}
			}
		}
	}
	return $offending_keys;
}


print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_tags", $db1, false);
query("DROP TABLE IF EXISTS _tmp_keys", $db1, false);
query("DROP TABLE IF EXISTS _tmp_bad_tags", $db1, false);

?>