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


$false_positives = array(
	array('county', 'country'),
	array('door', 'moor'),
	array('email', 'e-mail', 'mail'),
	array('fuel_octane_91', 'fuel_octane_95', 'fuel_octane_98'),
	array('food', 'foot'),
	array('grade1', 'grade2', 'grade3'),
	array('height', 'weight'),
	array('hotel', 'motel'),
	array('lanes', 'lines'),
	array('lcn_ref', 'ncn_ref', 'rcn_ref'),
	array('left', 'lift'),
	array('levels', 'level'),
	array('light', 'right'),
	array('line', 'link', 'mine', 'wine'),
	array('marked', 'marker', 'market'),
	array('marked_trail_red', 'marked_trail_ref'),
	array('maxheight', 'maxweight'),
	array('next', 'text'),
	array('number', 'numbers'),
	array('power', 'tower'),
	array('salb', 'sale', 'salt'),
	array('short', 'sport'),
	array('show', 'shop', 'stop'),
	array('tell', 'toll')
);



check_tags("node");
check_tags("way");
check_tags("relation");



function check_tags($item) {
global $error_type, $false_positives, $db1, $db2;
	query("DROP TABLE IF EXISTS _tmp_tags", $db1, false);
	query("
		CREATE TABLE _tmp_tags(
			k text NOT NULL,
			keylist text[],
			v text,
			tag_count bigint,
			UNIQUE (keylist)
		)
	", $db1, false);

	// split key names by each colon into an array and append the value as if it belonged to the key
	// replace any numeric value with dots to make them equal. Searching for differences on numbers makes no sense.
	query("
		INSERT INTO _tmp_tags(k, keylist, v, tag_count)
		SELECT k, regexp_split_to_array(k, ':') || ARRAY['=', CASE WHEN v ~ '^[0-9]{1,9}$' THEN '...' ELSE v END, ''], CASE WHEN v ~ '^[0-9]{1,9}$' THEN '...' ELSE v END, COUNT(${item}_id) as tag_count
		FROM ${item}_tags
		WHERE LENGTH(k)>3
		GROUP BY k, CASE WHEN v ~ '^[0-9]{1,9}$' THEN '...' ELSE v END
	", $db1);
	query("ANALYZE _tmp_tags", $db1, false);


	query("DROP TABLE IF EXISTS _tmp_keys", $db1, false);
	query("
		CREATE TABLE _tmp_keys(
			prefix text NOT NULL,
			k text NOT NULL,
			tag_count bigint,
			UNIQUE (prefix, k)
		)
	", $db1, false);


	for ($keylen=1; $keylen<6; $keylen++) {
		query("TRUNCATE TABLE _tmp_keys", $db1, false);

		// select the prefix (the first $keylen parts of the key name
		// if that last part has at least 4 and at max. 50 chars of length
		query("
			INSERT INTO _tmp_keys (prefix, k, tag_count)
			SELECT array_to_string(keylist[1:$keylen-1], ':') as prefix, keylist[$keylen] as postfix, SUM(tag_count)
			FROM _tmp_tags
			WHERE array_upper(keylist, 1)>=$keylen AND LENGTH(keylist[$keylen]) BETWEEN 4 AND 50
			GROUP BY prefix, postfix
		", $db1);
		query("ANALYZE _tmp_keys", $db1, false);

		$offending_keys = find_offending_keys($db1);
		//print_r($offending_keys);

		foreach ($offending_keys as $irreg_prefix=>$irreg_keys) foreach($irreg_keys as $irreg_key=>$reg_keys){

			if (found_in($reg_keys[1], $irreg_key, $false_positives) && $irreg_key != $reg_keys[1] . '2') {
				//echo "$item -- false positive -- '$irreg_key' looks like '$reg_key'\n";
				continue;
			}

			echo "$item '$irreg_prefix:$irreg_key' looks like '${reg_keys[0]}:${reg_keys[1]}'\n";
			// % and _ may be found in keys so they have to be escaped when used inside LIKE operators!
			query("
				INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
				SELECT DISTINCT $error_type, CAST('$item' AS type_object_type), t.${item}_id,
				'This $item is tagged \"' || t.k || '=' || t.v || '\" and \"" . addslashes($irreg_key) . "\" looks like \"" . addslashes($reg_key[1]) . "\"', NOW()
				FROM ${item}_tags t
				WHERE k || ':=:' || v || ':' LIKE '" . strtr(addslashes($irreg_prefix . ':' . $irreg_key), array('_'=>'\\_', '%'=>'\\%')) . ":%' AND
				k || ':=:' || v || ':' NOT LIKE '" . strtr(addslashes($reg_key[0] . ':' . $reg_key[1]), array('_'=>'\\_', '%'=>'\\%')) . ":%'
			", $db1, false);

		}
	}

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
function find_offending_keys($db1) {

	//find regular tags (i.e. tags that are used very frequently, currently at least 1/100000 of the whole number of tags)
	$tag_count_limit = query_firstval('SELECT SUM(tag_count) FROM _tmp_keys', $db1, false) / 100000;
	if ($tag_count_limit<10) $tag_count_limit=10;
	echo "tag count limit is $tag_count_limit\n";

	$result=query("
		SELECT prefix, k, tag_count
		FROM _tmp_keys
		WHERE prefix NOT LIKE 'name:%'
		AND prefix NOT LIKE 'openGeoDB:sort_name:%'
		AND prefix NOT LIKE 'openGeoDB:name:%'
		AND prefix NOT LIKE 'created_by:%'
		AND prefix NOT LIKE 'history:%'
		AND prefix NOT LIKE 'plan_at:acad_id:%'
	", $db1,false);
	while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
		if ($row['tag_count']>=$tag_count_limit)
			$regulars[] = array($row['prefix'], $row['k']);
		else
			$irregulars[] = array($row['prefix'], $row['k']);
	}
	pg_free_result($result);

	//echo "REGULARS:\n"; print_r($regulars);
	//echo "IR-REGULARS:\n"; print_r($irregulars);
	$offending_keys=array();
	if (is_array($irregulars) && is_array($regulars)) {
		foreach ($irregulars as $irreg_key) {
			//$max_diff = floor(strlen($irreg_key)/6);
			$max_diff=1;
			foreach ($regulars as $reg_key) {
				if (($irreg_key[0]==$reg_key[0]) && levenshtein($irreg_key[1], $reg_key[1])<=$max_diff) {
					//printf ("%30s - %s\n", $irreg_key, $reg_key);
					$offending_keys[$irreg_key[0]][$irreg_key[1]]=array($reg_key[0], $reg_key[1]);
				}
			}
		}
	}
	return $offending_keys;
}


//query("DROP TABLE IF EXISTS _tmp_tags", $db1, false);
//query("DROP TABLE IF EXISTS _tmp_keys", $db1, false);

/*

function lastpart($key) {
	$equals_pos = strpos($key, ':=:');
	$lastcolon_pos = strrpos($key, ':', 1);
	if ($lastcolon_pos==false) $lastcolon_pos=-1;
	if ($lastcolon_pos>$equals_pos) $lastcolon_pos=$equals_pos+3;
	return substr($key, 1+$lastcolon_pos);
}

function check_tags($item) {
global $error_type, $false_positives, $db1, $db2;
	query("DROP TABLE IF EXISTS _tmp_tags", $db1, false);
	query("
		CREATE TABLE _tmp_tags(
			k text NOT NULL,
			keylist text[],
			v text,
			tag_count bigint,
			UNIQUE (keylist)
		)
	", $db1, false);

	// split key names by each colon into an array and append the value as if it belonged to the key
	// replace any numeric value with zero. Searching for differences on numbers makes no sense.
	query("
		INSERT INTO _tmp_tags(k, keylist, v, tag_count)
		SELECT k, regexp_split_to_array(k, ':') || ARRAY['=', CASE WHEN v ~ '^[0-9]{1,9}$' THEN '...' ELSE v END, ''], CASE WHEN v ~ '^[0-9]{1,9}$' THEN '...' ELSE v END, COUNT(${item}_id) as tag_count
		FROM ${item}_tags
		WHERE LENGTH(k)>3
		GROUP BY k, CASE WHEN v ~ '^[0-9]{1,9}$' THEN '...' ELSE v END
	", $db1);
	query("ANALYZE _tmp_tags", $db1, false);


	query("DROP TABLE IF EXISTS _tmp_keys", $db1, false);
	query("
		CREATE TABLE _tmp_keys(
			k text NOT NULL,
			tag_count bigint,
			UNIQUE (k)
		)
	", $db1, false);


	for ($keylen=1; $keylen<6; $keylen++) {
		query("TRUNCATE TABLE _tmp_keys", $db1, false);

		// select the prefix (the first $keylen parts of the key name
		// if that part has at least 4 and at max. 50 chars of length
		query("
			INSERT INTO _tmp_keys (k, tag_count)
			SELECT array_to_string(keylist[1:$keylen], ':') AS prefix, SUM(tag_count)
			FROM _tmp_tags
			WHERE array_upper(keylist, 1)>=$keylen AND LENGTH(keylist[$keylen]) BETWEEN 4 AND 50
			GROUP BY prefix
		", $db1);
		query("ANALYZE _tmp_keys", $db1, false);

		$offending_keys = find_offending_keys($db1);
		//print_r($offending_keys);

		foreach ($offending_keys as $irreg_key=>$reg_key) {
			$reg_key_lastpart = lastpart($reg_key);
			$irreg_key_lastpart = lastpart($irreg_key);

			if (found_in($reg_key_lastpart, $irreg_key_lastpart, $false_positives) && $irreg_key != $reg_key . '2') {
				//echo "$item -- false positive -- '$irreg_key' looks like '$reg_key'\n";
				continue;
			}

			echo "$item '$irreg_key' looks like '$reg_key'\n";
			// % and _ may be found in keys so they have to be escaped when used inside LIKE operators!
			query("
				INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
				SELECT DISTINCT $error_type, CAST('$item' AS type_object_type), t.${item}_id,
				'This $item is tagged \"' || t.k || '=' || t.v || '\" and \"" . addslashes($irreg_key_lastpart) . "\" looks like \"" . addslashes($reg_key_lastpart) . "\"', NOW()
				FROM ${item}_tags t
				WHERE k || ':=:' || v || ':' LIKE '" . strtr(addslashes($irreg_key), array('_'=>'\\_', '%'=>'\\%')) . ":%' AND
				k || ':=:' || v || ':' NOT LIKE '" . strtr(addslashes($reg_key), array('_'=>'\\_', '%'=>'\\%')) . ":%'
			", $db1, false);

		}
	}

}

function lastpart($key) {
	$equals_pos = strpos($key, ':=:');
	$lastcolon_pos = strrpos($key, ':', 1);
	if ($lastcolon_pos==false) $lastcolon_pos=-1;
	if ($lastcolon_pos>$equals_pos) $lastcolon_pos=$equals_pos+3;
	return substr($key, 1+$lastcolon_pos);
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
function find_offending_keys($db1) {

	//find regular tags (i.e. tags that are used very frequently, currently at least 1/100000 of the whole number of tags)
	$tag_count_limit = query_firstval('SELECT SUM(tag_count) FROM _tmp_keys', $db1, false) / 100000;
	if ($tag_count_limit<10) $tag_count_limit=10;
	//echo "tag count limit is $tag_count_limit\n";

	$result=query("
		SELECT k, tag_count
		FROM _tmp_keys
		WHERE k NOT LIKE 'name:=:%'
		AND k NOT LIKE 'openGeoDB:sort_name:=:%'
		AND k NOT LIKE 'openGeoDB:name:=:%'
		AND k NOT LIKE 'created_by:=:%'
		AND k NOT LIKE 'history:=:%'
		AND k NOT LIKE 'plan_at:acad_id:=:%'
	", $db1,false);
	while ($row=pg_fetch_array($result, NULL, PGSQL_ASSOC)) {
		if ($row['tag_count']>=$tag_count_limit)
			$regulars[] = $row['k'];
		else
			$irregulars[] = $row['k'];
	}
	pg_free_result($result);

	//echo "REGULARS:\n"; print_r($regulars);
	//echo "IR-REGULARS:\n"; print_r($irregulars);
	$offending_keys=array();
	if (is_array($irregulars) && is_array($regulars)) {
		foreach ($irregulars as $irreg_key) {
			//$max_diff = floor(strlen($irreg_key)/6);
			$max_diff=1;
			foreach ($regulars as $reg_key) {
				if (levenshtein($irreg_key, $reg_key)<=$max_diff) {
					//printf ("%30s - %s\n", $irreg_key, $reg_key);
					$offending_keys[$irreg_key]=$reg_key;
				}
			}
		}
	}
	return $offending_keys;
}

*/
?>