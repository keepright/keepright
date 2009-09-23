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

        $result=pg_query($link, $sql);

        if ($result===false) {
                $message  = 'Invalid query: ' . pg_result_error($result) . "\n";
                $message .= 'Whole query: ' . $sql . "\n";
                echo($message);
        }

        if ($debug) echo format_time(microtime(true)-$starttime) ."\n";
        return $result;
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
	global $MAIN_DB_NAME;

	// query meta table of all columns for column to add
	return query_firstval("
		SELECT COUNT(column_name)
		FROM information_schema.columns
		WHERE 	table_catalog='$MAIN_DB_NAME'
			AND table_schema='$schema'
			AND table_name='$table'
			AND column_name='$column'
	",$db, false) != 0;
}

// will examine meta data to find out if a column of given name
// already exists and will create one if not
function add_column($table, $column, $type, $db, $schema='') {

	$sch=get_schema($schema);

	if (!column_exists($table, $column, $db, $sch)) {
		query("ALTER TABLE $sch.$table ADD COLUMN $column $type", $db);
	}
}

// will examine meta data to find out if a column of given name
// exists and will drop it if if does
function drop_column($table, $column, $db, $schema='') {

	$sch=get_schema($schema);

	if (column_exists($table, $column, $db, $sch)) {
		query("ALTER TABLE $sch.$table DROP COLUMN $column", $db);
	}
}

// return $schema if present or configured MAIN_SCHEMA_NAME parameter
function get_schema($schema) {
	global $db_params, $db_postfix;

	if ($schema=='')
		return strtolower($db_params[$db_postfix]['MAIN_SCHEMA_NAME']);
	else
		return strtolower($schema);
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

}


function drop_postgres_functions($db) {
	query("DROP FUNCTION IF EXISTS deg_rad(ang double precision)", $db, false);
	query("DROP FUNCTION IF EXISTS merc_x(lon double precision)", $db, false);
	query("DROP FUNCTION IF EXISTS merc_y(lat1 double precision)", $db, false);
	query("DROP FUNCTION IF EXISTS array_to_rows(myarray ANYARRAY)", $db, false);
	query("DROP AGGREGATE IF EXISTS array_accum(anyelement)", $db, false);
	query("DROP AGGREGATE IF EXISTS group_concat(text)", $db, false);
	query("DROP FUNCTION IF EXISTS _group_concat(text, text)", $db, false);
}


// finds the spot of a relation in the map by retrieving all objects
// referenced by the relation and its members (which may be relations too)
// and calculating the center of gravity of all nodes
// return value is an array like this: return array('lat'=>17, 'lon'=>3);
function locate_relation($id, $db1) {
	// temp. table for storing members of the relation
	query("DROP TABLE IF EXISTS _tmp_m", $db1, false);
	query("
		CREATE TABLE _tmp_m (
			member_type smallint NOT NULL,
			id bigint NOT NULL,
			PRIMARY KEY(member_type, id)
		)
	", $db1, false);
	add_insert_ignore_rule('_tmp_m', array('member_type', 'id'), $db1);

	// add starting relation
	query("INSERT INTO _tmp_m (member_type, id) VALUES(3, $id)", $db1, false);

	// recursively find sub-relations and their members referenced by the relation
	do {
		$result=query("
			INSERT INTO _tmp_m (member_type, id)
			SELECT DISTINCT m.member_type, m.member_id
			FROM relation_members m INNER JOIN _tmp_m tm ON m.relation_id=tm.id
			WHERE tm.member_type=3
		", $db1, false);
	} while (pg_affected_rows($result)>0);

	// for any way find the nodes it contains
	$result=query("
		INSERT INTO _tmp_m (member_type, id)
		SELECT DISTINCT 1, wn.node_id
		FROM way_nodes wn INNER JOIN _tmp_m tm ON wn.way_id=tm.id
		WHERE tm.member_type=2
	", $db1, false);

	// now we've got a list of nodes. Let's calculate teir coordinates' average
	$result=query("
		SELECT SUM(n.lat) AS la, SUM(n.lon) AS lo, COUNT(tm.id) AS cnt
		FROM _tmp_m tm INNER JOIN nodes n ON n.id=tm.id
		WHERE tm.member_type=1
	", $db1, false);
	$row=pg_fetch_array($result, NULL, PGSQL_ASSOC);

	if ($row && $row['cnt']<>0)
		$r = array('lat' => $row['la']/$row['cnt'], 'lon' => $row['lo']/$row['cnt']);
	else
		$r = array('lat' => 0, 'lon' => 0);

	pg_free_result($result);
	query("DROP TABLE IF EXISTS _tmp_m", $db1, false);
	return $r;
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
?>