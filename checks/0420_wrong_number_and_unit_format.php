<?php


/*
Checks numeric values with units for correct use.
*/

$tables = array('node', 'way');

// this loop will execute similar queries for all *_tags tables
foreach ($tables as $object_type) {

//If incline uses a numeric value, then the unit has to be degree or per cent. No spaces added between sign and number as well as number and unit
  query("
    INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
    SELECT $error_type, '{$object_type}', {$object_type}_id, 'This $1 is tagged incline=$2 which seems to not use the correct number format. The unit should be per cent or degrees and no spaces should be added', '{$object_type}', b.v, NOW()
    FROM {$object_type}_tags b
    WHERE b.k='incline' AND b.v != '0' AND b.v ~ '\d' AND b.v !~ '^[+-]?\d+(\.\d+)?[\%\°]?$'
  ", $db1);


//height and width can be in units of m,km,miles,feet&inch. Value and unit should be separated by a space, unless feet/inch are used
  query("
    INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, txt3, last_checked)
    SELECT $error_type, '{$object_type}', {$object_type}_id, 'This $1 is tagged $2=$3 which seems to not use the correct number format. The unit should be meter, kilometer, miles or feet/inch. A space should be added between number and unit', '{$object_type}', b.k, b.v, NOW()
    FROM {$object_type}_tags b
    WHERE b.k IN ('height','maxheight','min_height','width','maxwidth','distance','length','maxlength') 
          AND b.v ~ '\d' AND b.v !~ '^[+-]?\d+(\.\d+)?(\s(m|km|mi))?$' AND b.v !~ '^\d+''\d+\\\"$' 
  ", $db1);


//speed can be in units of km/h, mph, knots. Value and unit should be separated by a space
  query("
    INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, txt3, last_checked)
    SELECT $error_type, '{$object_type}', {$object_type}_id, 'This $1 is tagged $2=$3 which seems to not use the correct number format. The unit should be meter, kilometer, miles or feet/inch. A space should be added between number and unit', '{$object_type}', b.k, b.v, NOW()
    FROM {$object_type}_tags b
    WHERE b.k IN ('maxspeed','minspeed') 
          AND b.v ~ '\d' AND b.v !~ '^\d+(\.\d+)?(\s(km/h|mph|knots))?$' 
  ", $db1);

}
?>