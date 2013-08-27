<?php


function lon2x($lon) {
	return round(($lon + 180 ) * 65535 / 360);
}

function lat2y($lat) {
	return round(($lat + 90) * 65535 / 180);
}

function x2lon($x) {
	return ($x*360/65535) - 180;
}

function y2lat($y) {
	return ($y*180/65535)-90;
}

function query($sql, &$link, $debug=true) {
        if ($debug) {
                echo "\n\n" . rtrim(preg_replace('/(\s)\s+/', '$1', $sql)) . "\n";
                $starttime=microtime(true);
        }

	// one cannot EXPLAIN DDL-type SQL-statements
/*        if ($debug && !preg_match("/CREATE|ALTER|DROP|TRUNCATE|ANALYZE/i", $sql)) {
		$result=pg_query($link, "EXPLAIN $sql");
		print_pg_result($result);
		pg_free_result($result);
	}
*/
        $result=pg_query($link, $sql);

        if ($result===false) {
                $message  = 'Invalid query: ' . pg_result_error($result) . "\n";
                $message .= 'Whole query: ' . $sql . "\n";
                echo($message);
        }

        if ($debug) {
		echo format_time(microtime(true)-$starttime) ."\n";

		if (!preg_match("/CREATE|ALTER|DROP|TRUNCATE|ANALYZE/i", $sql))
			echo pg_affected_rows($result) . " rows affected.\n";
	}
        return $result;
}


// quotes text to remove html special characters and
// apos to build a string that can be safely INSERTed to the DB
function quote($db, $evil) {

	return pg_escape_string($db, htmlspecialchars($evil, ENT_QUOTES));

}


// query_firstval() will execute given query and return just the first
// value of the first row. This helps executing queries like
// SELECT COUNT(*) FROM..., SELECT MAX(...)
// for example:
// $row_count = query_firstval('SELECT COUNT(*) FROM table WHERE k=1', $db1);
function query_firstval($sql, $link, $debug=true) {
	$r=query($sql, $link, $debug);

	if ($row = pg_fetch_array($r)) $result=$row[0]; else $result=null;
	pg_free_result($r);

	return $result;
}




// there is no "CREATE ... IF NOT EXISTS" in Postgres.
// so look up the meta-tables instead...
function table_exists($db, $tablename, $schema='') {

	$sch=get_schema($schema);

	return query_firstval("
		SELECT COUNT(*)
		FROM pg_tables
		WHERE schemaname='$sch' AND tablename='$tablename'
	", $db, false) != 0;

}


// there is no "CREATE ... IF NOT EXISTS" in Postgres.
// so look up the meta-tables instead...
function type_exists($db, $typename, $schema='') {

	$sch=get_schema($schema);

	return query_firstval("
		SELECT COUNT(*)
		FROM pg_type INNER JOIN pg_namespace ON (pg_namespace.oid=pg_type.typnamespace) WHERE typname='$typename' AND nspname='$sch'
 	", $db, false) != 0;

}

// there is no "CREATE ... IF NOT EXISTS" in Postgres.
// so look up the meta-tables instead...
function index_exists($db, $indexname, $schema='') {

	$sch=get_schema($schema);

	return query_firstval("
		SELECT COUNT(*)
		FROM pg_indexes WHERE indexname='$indexname' AND schemaname='$sch'
 	", $db, false) != 0;

}

// will examine meta data to find out if a column of given name exists
function column_exists($table, $column, $db, $schema) {
	global $config;

	// query meta table of all columns for column to add
	return query_firstval("
		SELECT COUNT(column_name)
		FROM information_schema.columns
		WHERE 	table_catalog='" . $config['db']['database'] . "'
			AND table_schema='$schema'
			AND table_name='$table'
			AND column_name='$column'
	",$db, false) != 0;
}

// will examine meta data to find out if a column of given name
// already exists and will create one if not
function add_column($table, $column, $type, $db, $schema='', $debug=true) {

	$sch=get_schema($schema);

	if (!column_exists($table, $column, $db, $sch)) {
		query("ALTER TABLE $sch.$table ADD COLUMN $column $type", $db, $debug);
	}
}

// will examine meta data to find out if a column of given name
// exists and will drop it if if does
function drop_column($table, $column, $db, $schema='', $debug=true) {

	$sch=get_schema($schema);

	if (column_exists($table, $column, $db, $sch)) {
		query("ALTER TABLE $sch.$table DROP COLUMN $column", $db, $debug);
	}
}

// make sure a column has a specific data type
function set_column_type($table, $column, $type, $db, $schema='') {
	global $config;
	$sch=get_schema($schema);

	// query meta table of all columns for column datatype
	if (query_firstval("
		SELECT data_type
		FROM information_schema.columns
		WHERE 	table_catalog='" . $config['db']['database'] . "'
			AND table_schema='$sch'
			AND table_name='$table'
			AND column_name='$column'
	",$db, false) !== $type) {

		query("ALTER TABLE $sch.$table ALTER COLUMN $column TYPE $type", $db);

	}
}


// return $schema if present or global schema variable given on command line
function get_schema($schema_guess) {
	global $schema;

	if ($schema_guess=='')
		return 'schema' . strtolower($schema);
	else
		return strtolower($schema_guess);
}


// create a rule that checks on each INSERT-event
// if a record with identical primary key already exists
// $primary may be just one string denominating the p.key field
// or an array of field names if the primary key has more than one column
function add_insert_ignore_rule($table, $primary, $db) {

	$crit = "";
	if (is_array($primary)) {
		foreach ($primary as $p)
			$crit .= " $p = NEW.$p AND ";
		$crit = substr($crit, 0, strlen($crit)-4);
	} else
		$crit = " $primary = NEW.$primary ";

	$rulename = strtr($table, '.', '_');

	query("
                CREATE OR REPLACE RULE insert_ignore_$rulename AS
			ON INSERT TO $table WHERE EXISTS (
			SELECT 1 FROM $table
			WHERE $crit
		) DO INSTEAD NOTHING
	",$db, false);
}




/*
http://wiki.openstreetmap.org/index.php/Mercator
Php Code by Erhan Baris 19:19, 01.09.2007

START

*/
$r_major = 6378137.0;
$r_minor = 6356752.3142;

function deg_rad($ang)
{
	return (float)((float)$ang * (float)(M_PI / 180.0));
}

function rad_deg($ang)
{
	return (float)((float)$ang * (float)(180.0 / M_PI));
}

function merc_x($lon)
{
	global $r_major;;
	return (float)($r_major * deg_rad($lon));
}

function merc_y($lat)
{
	global $r_major, $r_minor;
	if ($lat > 89.5) $lat = 89.5;
	if ($lat < -89.5) $lat = -89.5;
	$temp = $r_minor / $r_major;
	$es = 1.0 - ($temp * $temp);
	$eccent = sqrt($es);
	$phi = deg_rad($lat);
	$sinphi = sin($phi);
	$con = $eccent * $sinphi;
	$com = 0.5 * $eccent;
	$con = pow(((1.0-$con)/(1.0+$con)), $com);
	$ts = tan(0.5 * ((M_PI*0.5) - $phi))/$con;
	$y = 0 - $r_major * log($ts);
	return $y;
}

function merc($x,$y) {
    return array('x'=>merc_x($x),'y'=>merc_y($y));
}

/*
http://wiki.openstreetmap.org/index.php/Mercator
Php Code by Erhan Baris 19:19, 01.09.2007

END

*/


/*
http://wiki.openstreetmap.org/index.php/Mercator
C# Implementation by Florian Müller, based on the C code published above, 14:50, 20.6.2008

START
*/

function merc_lon($x) {
	global $r_major;
	return (float)(rad_deg($x) / $r_major);
}


function merc_lat($y){
	global $r_minor, $r_major;

	$ts = exp(-(float)($y) / $r_major);
	$phi = (M_PI/2.0) - 2 * atan($ts);
	$dphi = 1.0;
	$PI_2 = M_PI / 2.0;
	$i = 0;
	$ratio = $r_minor/$r_major;
	$eccent = sqrt(1.0 - ($ratio * $ratio));
	$com = 0.5 * $eccent;
	while((abs($dphi) > 0.000000001) && ($i < 15)) {
		$con = $eccent * sin($phi);
		$dphi = $PI_2 - 2 * atan($ts * pow((1.0 - $con) / (1.0 + $con), $com)) - $phi;
		$phi += $dphi;
		$i++;
	}
	return rad_deg($phi);
}


/*

END

*/


function create_postgres_functions($db) {
	drop_postgres_functions($db);

	query("
		CREATE FUNCTION deg_rad (ang double precision) RETURNS double precision AS $$
			BEGIN
				RETURN ang * PI() / 180.0;
			END;
		$$ LANGUAGE plpgsql IMMUTABLE;
	", $db, false);

	query("
		CREATE FUNCTION merc_x (lon double precision) RETURNS double precision AS $$
			BEGIN
				RETURN 6378137.0 * deg_rad(lon);
			END;
		$$ LANGUAGE plpgsql IMMUTABLE;
	", $db, false);

	query("
		CREATE FUNCTION merc_y (lat1 double precision) RETURNS double precision AS $$

			DECLARE
				lat double precision;
				r_major double precision;
				r_minor double precision;
				eccent double precision;
				phi double precision;
				con double precision;
				com double precision;

			BEGIN

				lat := lat1;
				IF lat1 > 89.5 THEN
					lat := 89.5;
				END IF;
				IF lat1 < -89.5 THEN
					lat := -89.5;
				END IF;
				r_major := 6378137.0;
				r_minor := 6356752.3142;
				eccent := SQRT(1.0 - POW(r_minor / r_major, 2.0));
				phi := deg_rad(lat);
				con := eccent * sin(phi);
				com := 0.5 * eccent;
				con := POW(((1.0-con)/(1.0+con)), com);
				RETURN 0.0 - r_major * LN(TAN(0.5 * ((PI()*0.5) - phi))/con);
			END;
		$$ LANGUAGE plpgsql IMMUTABLE;
	", $db, false);


	// this is taken out oy mysqlcompat http://pgfoundry.org/projects/mysqlcompat/
	query("
		-- GROUP_CONCAT()
		-- Note: only supports the comma separator
		-- Note: For DISTINCT and ORDER BY a subquery is required
		CREATE OR REPLACE FUNCTION _group_concat(text, text)
		RETURNS text AS $$
			SELECT CASE
				WHEN $2 IS NULL THEN $1
				WHEN $1 IS NULL THEN $2
				ELSE $1 operator(pg_catalog.||) ',' operator(pg_catalog.||) $2
			END
		$$ IMMUTABLE LANGUAGE SQL;
	", $db, false);

	query("
		CREATE AGGREGATE group_concat (
			BASETYPE = text,
			SFUNC = _group_concat,
			STYPE = text
		);
	", $db, false);

	// taken out of file:///usr/share/doc/postgresql-doc-8.3/html/xaggr.html
	// this allows you to do array_accum(column) group by key
	// to get something like
	// k	accum
	// 1	{alpha, beta, delta}
	// 2	{gamma, epsilon}
	query("
		CREATE AGGREGATE array_accum (anyelement)
		(
			sfunc = array_append,
			stype = anyarray,
			initcond = '{}'
		);
	", $db, false);

	// posted on postgres docs forum: http://archives.postgresql.org//pgsql-novice/2005-07/msg00035.php
	// this function will convert array values into rows
	// select array_to_rows(ARRAY[1,2,3]);
	query("
		CREATE OR REPLACE FUNCTION array_to_rows(myarray ANYARRAY) RETURNS SETOF
		ANYELEMENT AS $$
		BEGIN
			FOR j IN 1..ARRAY_UPPER(myarray,1) LOOP
				RETURN NEXT myarray[j];
			END LOOP;
			RETURN;
		END;
		$$ LANGUAGE 'plpgsql';
	", $db, false);


	// always nice to have: XOR
	query("
		CREATE OR REPLACE FUNCTION XOR (boolean, boolean)
		RETURNS boolean as $$
		BEGIN
			RETURN ($1 and not $2) or ($2 and not $1);
		END
		$$ LANGUAGE 'plpgsql'
	", $db, false);


	// according to php's htmlspecialchars() replace the most important
	// html special characters with html entities to produce valid html
	query("
		CREATE OR REPLACE FUNCTION htmlspecialchars (text)
		RETURNS text as $$
		BEGIN
			RETURN REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($1, '&', '&amp;'), '''', '&#039;'), '\"', '&quot;'), '<', '&lt;'), '>', '&gt;');
		END
		$$ LANGUAGE 'plpgsql'
	", $db, false);



}


function drop_postgres_functions($db) {
	query('DROP FUNCTION IF EXISTS deg_rad(ang double precision)', $db, false);
	query('DROP FUNCTION IF EXISTS merc_x(lon double precision)', $db, false);
	query('DROP FUNCTION IF EXISTS merc_y(lat1 double precision)', $db, false);
	query('DROP FUNCTION IF EXISTS array_to_rows(myarray ANYARRAY)', $db, false);
	query('DROP AGGREGATE IF EXISTS array_accum(anyelement)', $db, false);
	query('DROP AGGREGATE IF EXISTS group_concat(text)', $db, false);
	query('DROP FUNCTION IF EXISTS _group_concat(text, text)', $db, false);
	query('DROP FUNCTION IF EXISTS XOR (boolean, boolean)', $db, false);
	query('DROP FUNCTION IF EXISTS htmlspecialchars (boolean, boolean)', $db, false);
}


// finds the spot of a relation in the map by retrieving the coordinates
// of the first node if a node is member of the relation,
// ...of the first way if a way is member
// recursively if only relations are member of this relation
// until a node or way is found in any relation
// return value is an array like this: return array('lat'=>17, 'lon'=>3);
function locate_relation($id, $db1, $depth=0) {

	// emergency brake for recursion
	if ($depth>100) {
		echo "locate_relation($id) had to pull emergency brake after 100 recursions.\n";
		return array('lat' => 0, 'lon' =>0);
	}

	// try to find a node
	$result=query("
		SELECT n.lat, n.lon
		FROM relation_members m INNER JOIN nodes n ON m.member_id=n.id
		WHERE m.relation_id=$id AND m.member_type='N'
		ORDER BY m.sequence_id
		LIMIT 1
	", $db1, false);
	$row=pg_fetch_array($result, NULL, PGSQL_ASSOC);

	if ($row && $row['lat']<>0) {
		$r = array('lat' => $row['lat'], 'lon' => $row['lon']);
		pg_free_result($result);
		return $r;
	}

	// try to find the first node of the first way
	$result=query("
		SELECT wn.lat, wn.lon
		FROM relation_members m INNER JOIN way_nodes wn ON m.member_id=wn.way_id
		WHERE m.relation_id=$id AND m.member_type='W'
		ORDER BY m.sequence_id, wn.sequence_id
		LIMIT 1
	", $db1, false);
	$row=pg_fetch_array($result, NULL, PGSQL_ASSOC);

	if ($row && $row['lat']<>0) {
		$r = array('lat' => $row['lat'], 'lon' => $row['lon']);
		pg_free_result($result);
		return $r;
	}

	// recurse into the next relation that is member of this relation
	$depth++;
	$result=query("
		SELECT m.member_id AS id
		FROM relation_members m
		WHERE m.relation_id=$id AND m.member_type='R'
		ORDER BY m.sequence_id
		LIMIT 1
	", $db1, false);
	$row=pg_fetch_array($result, NULL, PGSQL_ASSOC);

	if ($row && $row['id']<>0) {
		$r = $row['id'];
		pg_free_result($result);
		return locate_relation($r, $db1, $depth);
	}

	return array('lat' => 0, 'lon' =>0);
}


// given a table containing some data about ways
// you want to have the layer value for each row added in the table.
// just provide the table name, the name of the way_id column and
// the name of the layer column. This function does the rest.
// Declare the layer column this way: layer text DEFAULT '0'
function find_layer_values($table, $way_id_column, $layer_column, $db) {

	find_bridge_or_tunnel($table, $way_id_column, $layer_column, $db);

	// fetch level tag and overwrite defaults.
	// level is used for indoor mapping
	query("
		UPDATE $table c
		SET $layer_column=t.v
		FROM way_tags t
		WHERE t.way_id=c.$way_id_column AND t.k='level'
	", $db);

	// fetch layer tag and overwrite defaults including level tag
	query("
		UPDATE $table c
		SET $layer_column=t.v
		FROM way_tags t
		WHERE t.way_id=c.$way_id_column AND t.k='layer'
	", $db);
}

// in contrary to the function above this one will only identify
// bridges and tunnels but discard layer tags
// this is useful in cases one cannot depend on valid layer tags
// Declare the layer column this way: layer text DEFAULT '0'
function find_bridge_or_tunnel($table, $way_id_column, $layer_column, $db) {

	// set default layers:
	// bridges have layer +1 (if no layer tag is given)
	// tunnels have layer -1 (if no layer tag is given)
	// anything else has layer 0 (if no layer tag is given)
	// this is default in table definition
	query("
		UPDATE $table c
		SET $layer_column='1'
		FROM way_tags t
		WHERE t.way_id=c.$way_id_column AND
		t.k='bridge' AND t.v NOT IN ('no', 'false', '0')
	", $db);
	query("
		UPDATE $table c
		SET $layer_column='-1'
		FROM way_tags t
		WHERE t.way_id=c.$way_id_column AND
		t.k='tunnel' AND t.v NOT IN ('no', 'false', '0')
	", $db);

}


// creates a new table _tmp_one_ways containing one way streets
// explicitly and implicitly tagged (impl. eg: motorways)
// optionally a table containing way_ids may be provided
// (column name containing way_ids must be provided)
// to limit the resultset to just these ways
// retrieving first/last node ids and locations may be switched off
function find_oneways($db1, $way_table='', $include_node_locations=true) {

	query("DROP TABLE IF EXISTS _tmp_one_ways", $db1);
	query("
		CREATE TABLE _tmp_one_ways (
		way_id bigint NOT NULL,
		reversed boolean DEFAULT false, " .
		(
		$include_node_locations ?
		"	first_node_id bigint,
			last_node_id bigint,
			first_node_lat double precision,
			first_node_lon double precision,
			last_node_lat double precision,
			last_node_lon double precision,
		"
		: '') . "
		PRIMARY KEY (way_id)
		)
	", $db1);


	// fetch all one-way tagged ways
	// that are ways with oneway=yes/true/1/reverse/-1
	// and all motorways (tagged implicitly)
	// and all *_links that don't have a oneway=no/false/0 tag
	query("
		INSERT INTO _tmp_one_ways (way_id)
		SELECT wt.way_id
		FROM way_tags wt " . (strlen($way_table)>0 ?
			"INNER JOIN $way_table USING (way_id) "
			: '') . "
		WHERE (wt.k='oneway' AND wt.v IN ('yes', 'true', '1', 'reverse', '-1')) OR
			(wt.k='junction' AND wt.v = 'roundabout') OR
			(wt.k='highway' AND wt.v IN ('motorway', 'motorway_link', 'trunk_link',
			'primary_link', 'secondary_link'))
		GROUP BY wt.way_id
	", $db1);


	// implicitly oneway-tagged ways may be tagged non-oneway here
	// mostly applicable to motorway_link, trunk_link, primary_link, secondary_link
	query("
		DELETE FROM _tmp_one_ways
		WHERE way_id IN (
			SELECT way_id
			FROM way_tags tmp
			WHERE tmp.k='oneway' AND tmp.v IN ('no', 'false', '0')
		)
	", $db1);


	query("
		UPDATE _tmp_one_ways AS c
		SET reversed=true
		FROM way_tags tmp
		WHERE tmp.way_id=c.way_id AND
		tmp.k='oneway' AND tmp.v IN ('reverse', '-1')
	", $db1);

	if ($include_node_locations) {

		// find id of first and last node as well as coordinates of first and last node
		query("
			UPDATE _tmp_one_ways AS c
			SET first_node_id=w.first_node_id,
			first_node_lat=w.first_node_lat,
			first_node_lon=w.first_node_lon,
			last_node_id=w.last_node_id,
			last_node_lat=w.last_node_lat,
			last_node_lon=w.last_node_lon
			FROM ways AS w
			WHERE NOT c.reversed AND w.id=c.way_id
		", $db1);

		// oneways tagged with oneway=reverse or oneway=-1 are oneways
		// running in opposite direction (for some reason it isn't
		// possible to change orientation of the way so this tag was chosen)
		// assign first and last nodes the other way round:
		query("
			UPDATE _tmp_one_ways AS c
			SET first_node_id=w.last_node_id,
			last_node_id=w.first_node_id,
			first_node_lat=w.last_node_lat,
			first_node_lon=w.last_node_lon,
			last_node_lat=w.first_node_lat,
			last_node_lon=w.first_node_lon
			FROM ways AS w
			WHERE c.reversed AND w.id=c.way_id
		", $db1);

		query("CREATE INDEX idx_tmp_one_ways_first_node_id ON _tmp_one_ways (first_node_id)", $db1, false);
		query("CREATE INDEX idx_tmp_one_ways_last_node_id ON _tmp_one_ways (last_node_id)", $db1, false);
	}
	query("ANALYZE _tmp_one_ways", $db1);

}


// logs a message to stdout if loglevel is appropriate
function logger($message, $loglevel=KR_INFO) {
	global $config;

	if ($loglevel & $config['loglevel']) echo $message . "\n";

}


// returns the operating system the script runs on
// need to consider special requirements eg for
// building command line calls
// possible values: Linux, Windows NT
function platform() {
	return php_uname('s');
}


// run shell command and check errorlevel
// prepend $label in any error message
function shellcmd($cmd, $label='', $exit_on_error=true) {
	logger($cmd, KR_COMMANDS);
	system($cmd, $errorlevel);
	if ($errorlevel) {
		logger("$label exit with errorlevel $errorlevel", KR_ERROR);
		if ($exit_on_error) exit(1);
	}

	return $errorlevel;
}


// return an array containing all possible values inside the tag value
// split by ";". verbose ";" have to be doubled ";;"
// http://wiki.openstreetmap.org/wiki/Semi-colon_value_separator
function split_tag($value) {

	if (strpos($value, ';') === false) {// shortcut for the common case: no semicolon at all

		return array($value);

	} else {
		$tmp = str_replace(';;', '<semicolon>', $value);	// save any verbose ;
		$list = explode(';', $tmp);
		$list = str_replace('<semicolon>', ';', $list);		// restore after splitting

		return $list;
	}
}


// gets a time value in seconds and writes it in s, min, h
// according to its amount
function format_time($t) {
	if ($t<60) {
		return sprintf("%01.2fs", $t);						// seconds
	} elseif ($t<3600) {
		return sprintf("%01.0fm %01.0fs", floor($t/60), $t % 60);		// minutes
	} else
		return sprintf("%01.0fh %01.0fm", floor($t/3600), ($t % 3600)/60);	// hours
}



function print_index_usage($db) {
	global $schema;

	$result=query("SELECT idstat.relname AS tblname, indexrelname AS idxname,
		idstat.idx_scan AS times_used,
		pg_size_pretty(pg_relation_size(idstat.relid)) AS tblsize, pg_size_pretty(pg_relation_size(indexrelid)) AS idxsize,
		n_tup_upd + n_tup_ins + n_tup_del as writes

		FROM pg_stat_user_indexes AS idstat JOIN pg_indexes ON
			(indexrelname = indexname AND idstat.schemaname = pg_indexes.schemaname)
		JOIN pg_stat_user_tables AS tabstat ON idstat.relid = tabstat.relid

		WHERE indexdef !~* 'unique' AND
		idstat.schemaname='schema$schema'
		ORDER BY idstat.relname, indexrelname;
	", $db, false);

	echo "index usage:\n\n";
	print_pg_result($result);
	pg_free_result($result);
}




// print contents of a psql-resultset to the console
// don't forget to pg_free_result your resultset afterwards!
function print_pg_result($result) {

	$colnum = pg_num_fields($result);
	$rownum = pg_num_rows($result);
	$colwidths=array();
	$colnames=array();
	$fmtstrings=array();

	// determine names of columns
	for ($col=0; $col<$colnum; $col++) {
		$colnames[$col]=pg_field_name($result, $col);
		$colwidths[$col]=strlen($colnames[$col]);
	}

	// determine widths of columns
	for ($col=0; $col<$colnum; $col++)
		for ($row=0; $row<$rownum; $row++) {
			$width = pg_field_prtlen($result, $row, $col);
			if ($colwidths[$col] < $width)
				$colwidths[$col] = $width;
		}

	// echo header line
	for ($col=0; $col<$colnum; $col++) {
		// format content left aligned
		$fmtstrings[$col] = '|%-' . $colwidths[$col] . 's';
		printf($fmtstrings[$col], $colnames[$col]);
	}
	echo "|\n";

	// echo data
	while ($row=pg_fetch_array($result, NULL, PGSQL_NUM)) {

		for ($col=0; $col<$colnum; $col++)
			printf($fmtstrings[$col], $row[$col]);
		echo "|\n";
	}
}



// any precondition that is needed for keepright to run
// shall be checked here and fixed if applicable
function check_prerequisites() {
	global $config;
	$ret=0;

	// check if osmosis is an executable
	// in windows one cannot check for executability, only for existence
	if ((platform()=='Linux' && !is_executable($config['osmosis_bin'])) ||
		(platform()=='Windows NT' && !is_readable($config['osmosis_bin']))) {
		logger('osmosis was not found or is not executable', KR_ERROR);
		$ret=1;
	}

	// check if base_dir exists
	if (!is_dir($config['base_dir'])) {
		logger('keepright base_dir not found. please check your config file', KR_ERROR);
		$ret=1;
	}

	// check if temp_dir exists
	if (!is_dir($config['temp_dir'])) {
		logger('keepright temp_dir not found. please check your config file', KR_ERROR);
		$ret=1;
	}


	return $ret;
}



// create the osmosis authfile containing db credentials for osmosis to access postgres-DB
function create_authfile() {
	global $config;

	$authfile = $config['planet_dir'] . 'osmosis_auth';
	$config['authfile'] = $authfile;

	// files created with file_put_contents() have liberal permissions
	file_put_contents($authfile, 'some boring content');

	// cut permissions to read and write for owner, nothing for anybody else
	chmod($authfile, 0600);

	file_put_contents($authfile,
		'host=' . $config['db']['host'] . "\n" .
		'port=' . $config['db']['port'] . "\n" .
		'database=' . $config['db']['database'] . "\n" .
		'user=' . $config['db']['user'] . "\n" .
		'password=' . $config['db']['password'] . "\n" .
		"dbType=postgresql\n"
	);
}


function connectstring($schema='') {
	global $config;

	$connectstring='host=' . $config['db']['host'] . ' port=' . $config['db']['port'] . ' dbname=' . $config['db']['database'] . ' user=' . $config['db']['user'] . ' password=' . $config['db']['password'];

	// append the schema name to the connect string to make any connection
	// use the given schema automatically
	if ($schema!=='') {
		$connectstring.=" options='--search_path=schema$schema,public'";
	} else {
		$connectstring.=" options='--search_path=public'";
	}

	return $connectstring;
}

?>