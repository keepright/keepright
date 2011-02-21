<?php

/*
-------------------------------------
-- roundabouts
-------------------------------------

in wrong direction
not closed loop
not enough roads connected (normally 3)
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
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT DISTINCT $error_type+1, CAST('way' AS type_object_type),
	first.way_id, 'This way is part of a roundabout but is not closed-loop. (split carriageways approaching a roundabout should not be tagged as roundabout)', NOW()
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
* find the center of gravity (C) of the roundabout (ie. calculate the
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
	true AS right_hand_country, false as mini_roundabout
	FROM _tmp_roundabout_parts rp INNER JOIN way_nodes wn USING (way_id)
	GROUP BY rp.part
", $db1);


// determine direction of roundabout
query("
	UPDATE _tmp_roundabouts r
	SET clockwise=true
	FROM (_tmp_roundabout_parts rp INNER JOIN way_nodes wn1 ON (wn1.way_id=rp.way_id)) INNER JOIN way_nodes wn2 ON (wn2.way_id=rp.way_id)
	WHERE rp.part=r.part AND rp.sequence_id=0 AND wn1.sequence_id=0 AND wn2.sequence_id=1
	AND (wn1.x-r.Cx)*(wn2.y-r.Cy) - (wn1.y-r.Cy)*(wn2.x-r.Cx) < 0
", $db1);


// add mini_roundabouts.
// direction can be clockwise or anticlockwise. anticlockwise is default and needn't be tagged
query("
	INSERT INTO _tmp_roundabouts (part, Cx, Cy, clockwise, right_hand_country, mini_roundabout)
	SELECT -1*n.id, n.x, n.y,
		EXISTS (
			SELECT nt.node_id
			FROM node_tags nt
			WHERE nt.node_id=n.id AND k='direction' AND v='clockwise'
		),
		true, true

	FROM nodes n
	WHERE id IN (
		SELECT node_id
		FROM node_tags nt
		WHERE k='highway' AND v='mini_roundabout'
	)
", $db1);




// determine traffic mode of country

// watch out! all geoms are in x/y coordinates, not lat/lon!
// these countries are left-hand-driving countries
query("
	UPDATE _tmp_roundabouts r
	SET right_hand_country=false
	FROM _tmp_boundaries b
	WHERE b.admin_level IN ('1', '2') AND b.name IN ('Anguilla', 'Antigua and Barbuda', 'Australia', 'Bahamas', 'Bangladesh', 'Barbados', 'Bhutan', 'Botswana', 'Brunei', 'Cayman Islands', 'Cyprus', 'Dominica', 'Falkland Islands', 'Fiji', 'Grenada', 'Guernsey', 'Guyana', 'Hong Kong', 'India', 'Indonesia', 'Ireland', 'Isle of Man', 'Jamaica', 'Japan', 'Jersey', 'Kenya', 'Kiribati', 'Lesotho', 'Macau', 'Malawi', 'Malaysia', 'Maldives', 'Malta', 'Mauritius', 'Montserrat', 'Mozambique', 'Namibia', 'Nauru', 'Nepal', 'New Zealand', 'Pakistan', 'Papua New Guinea', 'Papua Niugini', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Vincent and the Grenadines', 'Seychelles', 'Singapore', 'Solomon Islands', 'South Africa', 'Sri Lanka', 'Suriname', 'Swaziland', 'Tanzania', 'Thailand', 'Tonga', 'Trinidad and Tobago', 'Tuvalu', 'Uganda', 'United Kingdom', 'British Virgin Islands', 'U.S. Virgin Islands', 'Virgin Islands', 'Zambia', 'Zimbabwe')
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

// fix boundary of south African countries from South Africa up to Kenya
// lat/lon: -17 10, -40 10, -40 40, -20 40, -15.5 41.75, -1.9 41.75, -0.9 41, 2.76 40.95, 3.9 41.9, 3.9 41.1, 4.2 40.7, 3.8 39.9, 3.37 39.5, 3.55 38.1, 4.43 36.78, 4.43 36, 4.6 35.86, 4.56 34.4, 3.73 33.53, 3.47 30.9, 2.41 30.72, 2.06 31.29, 0.79 29.97, -1.41 29.58, -1.45 30.06, -1.1 30.41, -1.67 30.8, -2.41 30.81, -2.46 30.54, -2.95 30.46, -3.16 30.81, -4.52 29.67, -6.27 29.45, -8.28 30.72, -8.58 28.94, -9.32 28.46, -10.75 28.63, -11.61 28.28, -12.43 29.02, -12.47 29.46, -12.13 29.78, -13.37 29.78, -13.41 29.03, -11.96 27.36, -11.65 25.29, -11.22 25.29, -11.44 24.32, -11.1 24.32, -10.92 23.97, -13.03 23.93, -13.07 21.95, -16.25 21.95, -17.69 23.4, -18.07 20.94, -17.86 18.83, -17.44 18.35, -17.48 13.91, -17.06 13.38, -17 10
 query("
	UPDATE _tmp_roundabouts r
	SET right_hand_country=false
	WHERE ST_Within(
		ST_PointFromText ('POINT(' || r.Cx || ' ' || r.Cy || ')', 4326),
		ST_GeomFromText ('POLYGON((1113195 -1908339, 1113195 -4838471, 4452780 -4838471, 4452780 -2258424, 4647589 -1735479, 4647589 -210130, 4564099 -99521, 4558533 305305, 4664287 431578, 4575231 431578, 4530703 464834, 4441648 420495, 4397120 372853, 4241273 392793, 4094331 490339, 4007502 490339, 3991917 509196, 3829390 504759, 3732543 412738, 3439772 383931, 3419735 266564, 3483187 227833, 3336245 87356, 3292831 -155926, 3346264 -160350, 3385226 -121639, 3428640 -184686, 3429754 -266564, 3399697 -272097, 3390792 -326340, 3429754 -349594, 3302849 -500322, 3278359 -694707, 3419735 -918801, 3221586 -952341, 3168153 -1035188, 3187077 -1195803, 3148115 -1292762, 3230492 -1385493, 3279472 -1390024, 3315094 -1351534, 3315094 -1492161, 3231605 -1496709, 3045701 -1332307, 2815270 -1297279, 2815270 -1248756, 2707290 -1273572, 2707290 -1235228, 2668328 -1214947, 2663875 -1453532, 2443463 -1458074, 2443463 -1821742, 2604876 -1988318, 2331030 -2032497, 2096146 -2008070, 2042713 -1959305, 1548454 -1963944, 1489455 -1915282, 1113195 -1908339))', 4326)
	)
", $db1);


// fix boundary of Guyana and Surinam
// lat/lon: -59.83 8.36, -60.73 7.47, -60.32 6.98, -61.22 6.55, -61.37 5.91, -60.75 5.20, -59.98 5.07, -60.13 4.51, -59.55 3.92, -59.89 2.35, -58.81 1.18, -57.08 1.98, -55.91 1.85, -56.03 2.47, -54.93 2.55, -54.57 2.32, -53.97 3.55, -54.48 4.39, -54.41 5.07, -53.77 6.20, -59.83 8.36,
query("
	UPDATE _tmp_roundabouts r
	SET right_hand_country=false
	WHERE ST_Within(
		ST_PointFromText ('POINT(' || r.Cx || ' ' || r.Cy || ')', 4326),
		ST_GeomFromText ('POLYGON((-6660245 927743, -6760433 828371, -6714792 773750, -6814979 725865, -6831677 654671, -6762659 575788, -6676943 561354, -6693641 499213, -6629076 433794, -6666924 259923, -6546699 130487, -6354117 218981, -6223873 204598, -6237231 273204, -6114780 282059, -6074705 256603, -6007913 392793, -6064686 485903, -6056893 561354, -5985649 686920, -6660245 927743))', 4326)
	)
", $db1);


// fix boundary of India and Pakistan
// lat/lon: 61.20 24.18,62.21 26.40,63.16 26.64,63.29 27.19,62.81 27.27,62.81 28.28,61.53 28.86,60.83 29.81,62.54 29.36,66.28 29.85,66.76 31.18,67.68 31.40,69.35 31.85,69.53 33.07,70.23 33.33,69.93 33.95,71.02 33.99,71.59 35.29,71.20 36.01,72.30 36.75,75.29 36.93,78.01 35.29,79.28 32.56,78.45 32.44,78.89 31.40,81.22 30.00,81.66 30.38,86.45 27.89,88.82 27.97,89.00 27.23,89.79 28.12,92.25 27.73,94.71 29.24,95.46 29.07,96.12 29.43,96.56 28.39,97.32 28.21,96.92 27.40,95.08 26.44,95.02 25.71,94.60 25.16,94.73 25.06,94.16 23.84,93.35 25.04,93.17 22.22,92.60 21.96,92.60 21.28,92.25 21.36,92.36 20.23,91.62 3.29,68.91 3.29
query("
	UPDATE _tmp_roundabouts r
	SET right_hand_country=false
	WHERE ST_Within(
		ST_PointFromText ('POINT(' || r.Cx || ' ' || r.Cy || ')', 4326),
		ST_GeomFromText ('POLYGON((6812753 2757862, 6925186 3029714, 7030939 3059413, 7045411 3127710, 6991977 3137673, 6991977 3264082, 6849488 3337220, 6771565 3457913, 6961921 3400601, 7378256 3463020, 7431689 3634029, 7534103 3662547, 7720007 3721091, 7740044 3881285, 7817968 3915712, 7784572 3998228, 7905910 4003573, 7969362 4178676, 7925948 4276875, 8048399 4378750, 8381244 4403679, 8684033 4178676, 8825409 3814052, 8733014 3798289, 8781995 3662547, 9041369 3482189, 9090350 3530883, 9623570 3215130, 9887397 3225157, 9907435 3132691, 9995377 3243977, 10269223 3195098, 10543069 3385361, 10626559 3363802, 10700029 3409499, 10749010 3277922, 10833613 3255282, 10789085 3153877, 10584257 3034660, 10577578 2944677, 10530824 2877250, 10545295 2865023, 10481843 2716659, 10391674 2862579, 10371637 2521808, 10308185 2490751, 10308185 2409796, 10269223 2419301, 10281468 2285529, 10199092 363992, 7671026 363992, 6812753 2757862))', 4326)
	)
", $db1);


// fix boundary of Thailand and South-East Asia
// lat/lon: 97.70 9.18,98.80 10.50,98.76 10.63,99.64 11.78,99.11 13.02,99.13 13.84,98.21 14.90,98.23 15.20,98.56 15.37,98.65 16.51,97.33 18.54,97.77 18.58,97.90 19.66,99.04 19.95,100.06 20.38,100.56 20.11,100.43 19.58,101.26 19.49,101.00 17.49,102.12 18.14,102.98 17.93,103.37 18.39,104.03 18.18,104.78 17.39,104.87 16.27,105.51 15.64,105.28 14.20,103.09 14.26,102.38 13.45,102.91 11.56,102.12 10.80,104.92 7.20,119.16 7.73,119.70 3.00,129.30 5.60,141.78 3.51,165.42 -5.88,156.11 -18.73,179.75 -34.75,179.75 -52.00,89.00 -52.00,89.00 15,95.00 14,97.70 9.18
query("
	UPDATE _tmp_roundabouts r
	SET right_hand_country=false
	WHERE ST_Within(
		ST_PointFromText ('POINT(' || r.Cx || ' ' || r.Cy || ')', 4326),
		ST_GeomFromText ('POLYGON((10875914 1019501, 10998366 1167671, 10993913 1182297, 11091874 1311963, 11032875 1452397, 11035101 1545651, 10932687 1666698, 10934914 1701064, 10971649 1720560, 10981668 1851724, 10834726 2087273, 10883707 2091942, 10898178 2218428, 11025082 2252536, 11138628 2303229, 11194288 2271382, 11179816 2209029, 11272212 2198462, 11243269 1965104, 11367946 2040645, 11463681 2016209, 11507096 2069775, 11580567 2045303, 11664056 1953507, 11674075 1824047, 11745319 1751557, 11719716 1586697, 11475926 1593544, 11396889 1501258, 11455889 1287117, 11367946 1201432, 11679641 798267, 13264831 857379, 13324943 331877, 14393610 620217, 15782877 388362, 18414470 -651336, 17378086 -2109460, 20009678 -4105604, 20009678 -6766432, 9907435 -6766432, 9907435 1678148, 10575352 1563886, 10875914 1019501))', 4326)
	)
", $db1);

// create error records

// right_hand_country and clockwise is wrong
// left_hand_country and counter-clockwise is wrong

// first for large roundabouts
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type+2, CAST('way' AS type_object_type),
	rp.way_id, 'If this roundabout is in a country with ' ||
	CASE WHEN right_hand_country THEN 'right' ELSE 'left' END ||
	'-hand traffic then its orientation goes the wrong way around', NOW()

	FROM _tmp_roundabouts r INNER JOIN _tmp_roundabout_parts rp USING (part)
	WHERE rp.sequence_id=0 AND right_hand_country=clockwise AND NOT mini_roundabout
", $db1);

// second for mini_roundabouts
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT $error_type+2, CAST('node' AS type_object_type),
	-1*r.part, 'If this mini_roundabout is in a country with ' ||
	CASE WHEN right_hand_country THEN 'right' ELSE 'left' END ||
	'-hand traffic then its orientation goes the wrong way around', NOW()
	FROM _tmp_roundabouts r
	WHERE right_hand_country=clockwise AND mini_roundabout
", $db1);






query("DROP TABLE IF EXISTS _tmp_roundabout_nodes", $db1, false);
query("
	CREATE TABLE _tmp_roundabout_nodes AS
	SELECT DISTINCT rp.part, wn.node_id
	FROM _tmp_roundabout_parts rp INNER JOIN way_nodes wn USING (way_id)
", $db1);



// find nodes belonging to roundabout_parts.
// find any way connected to these nodes that isn't part of the roundabout
// they have to be at least three.
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, txt1, last_checked)
	SELECT $error_type+3, CAST('way' AS type_object_type),
	MIN(way_id), 'This roundabout has only $1 other roads connected. Roundabouts typically have three.', er.cnt, NOW()
	FROM (

		SELECT rn.part, COUNT(wn.way_id) as cnt
		FROM _tmp_roundabout_nodes rn INNER JOIN way_nodes wn USING (node_id)
		WHERE wn.way_id NOT IN (
			SELECT DISTINCT way_id
			FROM _tmp_roundabout_parts tmp
			WHERE tmp.part=rn.part
		)
		GROUP BY rn.part
		HAVING COUNT(wn.way_id)<3

	) AS er INNER JOIN _tmp_roundabout_parts USING (part)
	GROUP BY er.part, er.cnt
", $db1);




print_index_usage($db1);

query("DROP TABLE IF EXISTS _tmp_roundabouts", $db1, false);
query("DROP TABLE IF EXISTS _tmp_roundabout_parts", $db1, false);
query("DROP TABLE IF EXISTS _tmp_roundabout_nodes", $db1, false);
query("DROP SEQUENCE IF EXISTS _tmp_rcounter", $db1, false);

?>
