<?php

/*
-------------------------------------
-- roundabouts
-------------------------------------

in wrong direction
not closed loop
*/


// roundabout_parts are ways tagged as junction=roundabout
// keep in mind that a roundabout may be built from multiple
// way segments!
query("DROP TABLE IF EXISTS _tmp_roundabout_parts", $db1);
query("
	CREATE TABLE _tmp_roundabout_parts (
		part int,
		sequence_id int,
		way_id bigint NOT NULL,
		first_node_id bigint,
		last_node_id bigint,
		direction int default 1
	)
", $db1);

query("
	INSERT INTO _tmp_roundabout_parts (way_id)
	SELECT way_id
	FROM way_tags t
	WHERE t.k='junction' and t.v='roundabout'
", $db1);

query("CREATE INDEX idx_tmp_roundabout_parts_way_id ON _tmp_roundabout_parts (way_id)", $db1, false);
query("ANALYZE _tmp_roundabout_parts", $db1, false);


// direction==1 means "normal"; direction==0 means reversed
query("
	UPDATE _tmp_roundabout_parts
	SET direction=0
	FROM way_tags t
	WHERE t.way_id=_tmp_roundabout_parts.way_id AND t.k='oneway' AND t.v='-1'
", $db1);

// find the first and last node ids for each segment
query("
	UPDATE _tmp_roundabout_parts
	SET first_node_id=CASE WHEN direction=0 THEN w.last_node_id ELSE w.first_node_id END,
	last_node_id=CASE WHEN direction=0 THEN w.first_node_id ELSE w.last_node_id END
	FROM ways w
	WHERE w.id=_tmp_roundabout_parts.way_id
", $db1);

query("CREATE INDEX idx_tmp_roundabout_parts_first_node_id ON _tmp_roundabout_parts (first_node_id)", $db1, false);
query("CREATE INDEX idx_tmp_roundabout_parts_last_node_id ON _tmp_roundabout_parts (last_node_id)", $db1, false);
query("ANALYZE _tmp_roundabout_parts", $db1, false);


query("DROP SEQUENCE IF EXISTS _tmp_rcounter", $db1, false);
query("CREATE SEQUENCE _tmp_rcounter", $db1);

// mark already closed-loop ways
query("
	UPDATE _tmp_roundabout_parts
	SET part=nextval('_tmp_rcounter'), sequence_id=0
	WHERE first_node_id=last_node_id
", $db1);



query("CREATE INDEX idx_tmp_roundabout_parts_part_sequence_id ON _tmp_roundabout_parts (part, sequence_id)", $db1, false);
query("ANALYZE _tmp_roundabout_parts", $db1, false);


// join the other parts of roundabouts
// for details about this section have a look at prepare_countries.php!
$part=query_firstval("SELECT nextval('_tmp_rcounter')", $db1);
do {
	// pick a "random" part of each boundary as starting way.
	// let's take the lowest way id
	$result=query("
		UPDATE _tmp_roundabout_parts c
		SET part=$part, sequence_id=0
		WHERE c.part IS NULL AND c.way_id=(
			SELECT MIN(tmp.way_id)
			FROM _tmp_roundabout_parts tmp
			WHERE tmp.part IS NULL
		)
	", $db1, false);
	$count_newpart=pg_affected_rows($result);

	$loop=1;
	do {

		// 1) find next way id in sequence in forward direction
		$result=query("
			UPDATE _tmp_roundabout_parts T1
			SET part=$part, sequence_id=$loop
			FROM _tmp_roundabout_parts T0
			WHERE T1.sequence_id IS NULL AND T0.sequence_id=$loop-1 AND T1.first_node_id=T0.last_node_id
		", $db1, false);
		$count_forward=pg_affected_rows($result);


		// 3) find next way id in sequence in backward direction
		$result=query("
			UPDATE _tmp_roundabout_parts T1
			SET part=$part, sequence_id=-$loop
			FROM _tmp_roundabout_parts T0
			WHERE T1.sequence_id IS NULL AND T0.sequence_id=-$loop+1 AND
			T1.last_node_id=T0.first_node_id
		", $db1, false);
		$count_backward=pg_affected_rows($result);

		//echo "found $count_backward in backward direction\n";
		//echo "found $count_forward in forward direction\n";

		$loop++;
	} while ($count_forward+$count_backward>0);

	$part++;
	//echo "  $part: $count_newpart\n";

} while ($count_newpart>0);

query("ANALYZE _tmp_roundabout_parts", $db1, false);



// closed-loops:
// the first node of the first segment and the last node of the last
// segment must be the same
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type+1, CAST('way' AS type_object_type),
	first.way_id, 'This way is part of a roundabout but is not closed-loop. (Flares should not be tagged as roundabout.)', NOW()
	FROM _tmp_roundabout_parts first, _tmp_roundabout_parts last
	WHERE first.part=last.part AND first.sequence_id = (
		SELECT MIN(t1.sequence_id)
		FROM _tmp_roundabout_parts t1
		WHERE t1.part=first.part
	) AND last.sequence_id = (
		SELECT MAX(t2.sequence_id)
		FROM _tmp_roundabout_parts t2
		WHERE t2.part=last.part
	) AND first.first_node_id<>last.last_node_id
", $db1);


/*

Finding the rotating direction of a roundabout works like this:
* find the center of gravty (C) of the roundabout (ie. calculate the
  average of all coordinates)
* pick any two adjacent points out of the roundabout (A and B) and have a
  look at the angle between them:
  a positive angle means counter-clockwise, a negative angle means
  clockwise rotation.

let a and b be two vectors:


             * B
            /
           / \   gamma                 ___---* A
          /    \                 ___---
         /       \         ___---
        /          \ ___---
       /       ___---
      /  ___---
     / --
   C*-----------------------------------------> x


the angle between a and b (gamma) is

             | a x b |     ax * by  -  ay * bx
sin gamma = ----------- = ---------------------
             |a| * |b|          |a| * |b|

we're just interested in the sign of gamma. That means, we only need to
evaluate the sign of the numerator of the right side.

*/


// one line for each roundabout; plus the center of gravity for each
query("DROP TABLE IF EXISTS _tmp_roundabouts", $db1, false);
query("
	CREATE TABLE _tmp_roundabouts AS
	SELECT rp.part, SUM(wn.y)/COUNT(wn.node_id) AS Cy,
		SUM(wn.x)/COUNT(wn.node_id) AS Cx, false AS clockwise,
	true AS right_hand_country
	FROM _tmp_roundabout_parts rp INNER JOIN way_nodes wn USING (way_id)
	GROUP BY rp.part
", $db1);


query("
	UPDATE _tmp_roundabouts r
	SET clockwise=true
	FROM (_tmp_roundabout_parts rp INNER JOIN way_nodes wn1 ON (wn1.way_id=rp.way_id)) INNER JOIN way_nodes wn2 ON (wn2.way_id=rp.way_id)
	WHERE rp.part=r.part AND rp.sequence_id=0 AND wn1.sequence_id=0 AND wn2.sequence_id=1
	AND (wn1.x-r.Cx)*(wn2.y-r.Cy) - (wn1.y-r.Cy)*(wn2.x-r.Cx) < 0
", $db1);


// watch out! all geoms are in x/y coordinates, not lat/lon!
// these countries are left-hand-driving countries
query("
	UPDATE _tmp_roundabouts r
	SET right_hand_country=false
	FROM _tmp_boundaries b
	WHERE b.admin_level IN ('1', '2') AND b.name IN ('Anguilla', 'Antigua and Barbuda', 'Australia', 'Bahamas', 'Bangladesh', 'Barbados', 'Bhutan', 'Botswana', 'Brunei', 'Cayman Islands', 'Cyprus', 'Dominica', 'Falkland Islands', 'Fiji', 'Grenada', 'Guernsey', 'Guyana', 'Hong Kong', 'India', 'Indonesia', 'Ireland', 'Isle of Man', 'Jamaica', 'Japan', 'Jersey', 'Kenya', 'Kiribati', 'Lesotho', 'Macau', 'Malawi', 'Malaysia', 'Maldives', 'Malta', 'Mauritius', 'Montserrat', 'Mozambique', 'Namibia', 'Nauru', 'Nepal', 'New Zealand', 'Pakistan', 'Papua New Guinea', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Seychelles', 'Singapore', 'Solomon Islands', 'South Africa', 'Sri Lanka', 'Suriname', 'Swaziland', 'Tanzania', 'Thailand', 'Tonga', 'Trinidad and Tobago', 'Tuvalu', 'Uganda', 'United Kingdom', 'British Virgin Islands', 'U.S. Virgin Islands', 'Virgin Islands', 'Zambia', 'Zimbabwe')
	AND ST_Within(ST_PointFromText ('POINT(' || r.Cx || ' ' || r.Cy || ')', 4326), b.geom)

", $db1);

// fix UK boundary
query("
	UPDATE _tmp_roundabouts r
	SET right_hand_country=false
	WHERE ST_Within(
		ST_PointFromText ('POINT(' || r.Cx || ' ' || r.Cy || ')', 4326),
		ST_GeomFromText ('POLYGON((-200375 6242596, -222639 6372180, 322827 6676758, 389618 7079082, 0 8821377, -2738459 7268959, -200375 6242596))', 4326)
	)
", $db1);


// right_hand_country and clockwise is wrong
// left_hand_country and counter-clockwise is wrong
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT $error_type+2, CAST('way' AS type_object_type),
	rp.way_id, 'If this roundabout is in a country with ' ||
	CASE WHEN right_hand_country THEN 'right' ELSE 'left' END ||
	'-hand traffic then its orientation goes the wrong way around.', NOW()

	FROM _tmp_roundabouts r INNER JOIN _tmp_roundabout_parts rp USING (part)
	WHERE rp.sequence_id=0 AND right_hand_country=clockwise
", $db1);


query("DROP TABLE IF EXISTS _tmp_roundabouts", $db1, false);
query("DROP TABLE IF EXISTS _tmp_roundabout_parts", $db1, false);
query("DROP SEQUENCE IF EXISTS _tmp_rcounter", $db1, false);

?>