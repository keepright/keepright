<?php


/*

-------------------------------------
-- look for double connections and impossible angles
-------------------------------------
*/


/*

part 1:
impossible angles: in driver's direction turning angles of
more than say 160 degrees are impossible:

coming from (1) and turning onto road (2) is impossible.
but coming from (3) and leaving to road (2) is obvioisly a
motorway_link. So oneway-tags (given explicitly or implicitly)
and driving directions do matter!

(2)
----____
        -----_____
                  ----------________
------------------------------------*--------(3)
(1)


more generally speaking: on any join node the angle between highways
must not be less than a given limit. If it is then a turn restriction
or one way tags prohibiting driving the sharp angle must be present.

     A
     |
     |
B----*----E
    /|
   / |
  C  D

driving from road C to D (or other way round) cannot be permitted


part 2:

just as with part 1 the same is applicable to the nodes of a way
(nodes that are _not_ part of junctions)
this is obviously not correct (a way consisting of three nodes A, B, C).
Oneway restrictions and turn restrictions need not be considered here.

C -----_____
            -------___
A ---------------------- B


*/


// include all ways tagged as highway but exclude some ways that are known to be jagged.
// exclude further residential and unclassified highways. They are used in the countryside
// and where driving speed is low so you won't find effective turning restrictions
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways AS
	SELECT DISTINCT wt.way_id
	FROM way_tags wt
	WHERE wt.k='highway' AND
		wt.v NOT IN ('cycleway', 'service', 'track', 'path', 'footway',
		'pedestrian', 'steps' ,'via_ferrata', 'emergency_access_point',
		'unclassified', 'residential')
", $db1);

query("CREATE UNIQUE INDEX idx_tmp_ways_way_id ON _tmp_ways (way_id)", $db1);
query("ANALYZE _tmp_ways", $db1, false);


// consider junctions only
// junction nodes are nodes, that are used at least twice
// in way_nodes but with different way_ids
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT wn.node_id, COUNT(DISTINCT wn.way_id) as waycount
	FROM way_nodes wn INNER JOIN _tmp_ways w USING (way_id)
	GROUP BY wn.node_id
	HAVING COUNT(DISTINCT wn.way_id)>1
", $db1);

query("CREATE UNIQUE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);
query("ANALYZE _tmp_junctions", $db1, false);



// junction partners are the next nodes connected
// directly with the junction node
// record driving direction (if you go in straight or opposite direction)
// when going from junct to other node
query("DROP TABLE IF EXISTS _tmp_jpartners", $db1);
query("
	CREATE TABLE _tmp_jpartners (
		junction_id bigint NOT NULL,
		other_id bigint NOT NULL,
		way_id bigint NOT NULL,
		reversed boolean,
		x double precision,
		y double precision
	)
", $db1);


query("
	INSERT INTO _tmp_jpartners
	SELECT wn.node_id, B.node_id, B.way_id, B.sequence_id=wn.sequence_id-1,
		B.x-wn.x, B.y-wn.y
	FROM (way_nodes wn INNER JOIN _tmp_junctions j USING (node_id)
	INNER JOIN _tmp_ways w USING (way_id))
	INNER JOIN way_nodes B ON B.way_id=w.way_id AND
	(B.sequence_id=wn.sequence_id+1 OR B.sequence_id=wn.sequence_id-1)
", $db1);

query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("CREATE INDEX idx_tmp_jpartners ON _tmp_jpartners (junction_id, other_id)", $db1);
query("ANALYZE _tmp_jpartners", $db1, false);




//
//              * B
//             /
//            / \
//           /    \  gamma             ___---* A
//          /       \            ___---
//         /          \    ___---
//        /          __\---
//       /     ___---
//      /___---
//    C*
// junction node
//
// the angle between a and b (gamma) is
//
//
// a in b = |a|*|b|*cos(gamma) = ax*bx + ay*by
//
//
//               ax * bx  +  ay * by     n
// cos(gamma) = --------------------- = ---
//                    |a| * |b|          d
//
// acos always results in positive values between 0 and pi/2
// the straightest junction possible has 180°:
// remember: a perfectly straight join looks like this:
//
//               C
//   B <---------*-------------------------> A
//
// the vectors pointing in opposite directions so the angle is 180°!
//
//
// we just want to check if gamma is less than limit:
//
// we have: acos(n/d) * 180/pi < limit
//
// this is the same: acos(n/d) < limit * pi/180
//
// eliminate the acos: n/d < cos(limit * pi/180)
// note that the right side is constant
//
// eliminate the fraction
// so we get: n < d * cos(limit * pi/180)
//



query("DROP TABLE IF EXISTS _tmp_sharp_angles", $db1);
query("
	CREATE TABLE _tmp_sharp_angles (
		junction_id bigint NOT NULL,
		first_way_id bigint NOT NULL,
		first_reversed boolean NOT NULL,
		second_way_id bigint NOT NULL,
		second_reversed boolean NOT NULL,
		error_first boolean NOT NULL DEFAULT true,
		error_second boolean NOT NULL DEFAULT true
	)
", $db1);

$angle_limit = cos(20.0 * PI()/180.0);

// error candidates are pairs of ways that have a sharp angle
// further research has to be done to check if driving the
// sharp angle is allowed or not

query("
	INSERT INTO _tmp_sharp_angles (
	junction_id, first_way_id, first_reversed,
		second_way_id, second_reversed)
	SELECT A.junction_id, A.way_id, A.reversed,
		B.way_id, B.reversed
	FROM _tmp_jpartners A, _tmp_jpartners B
	WHERE A.junction_id=B.junction_id AND A.other_id<B.other_id
	AND (A.x*B.x + A.y*B.y) >
		SQRT((A.x^2 + A.y^2)*(B.x^2 + B.y^2)) * ($angle_limit)

", $db1);


query("CREATE INDEX idx_tmp_sharp_angles_first_way_id ON _tmp_sharp_angles (first_way_id)", $db1);
query("CREATE INDEX idx_tmp_sharp_angles_second_way_id ON _tmp_sharp_angles (second_way_id)", $db1);
query("ANALYZE _tmp_sharp_angles", $db1, false);



// exclude pairs of ways that have the same name or ref
// we are looking for real junctions, not points where highways
// split in separate oneways
query("
	DELETE FROM _tmp_sharp_angles
	WHERE EXISTS (
		SELECT 1
		FROM way_tags wt1
		WHERE wt1.way_id=first_way_id AND
			wt1.k IN ('name', 'ref') AND
			EXISTS (
				SELECT 1
				FROM way_tags wt2
				WHERE wt2.way_id=second_way_id AND
					wt1.k=wt2.k AND
					wt1.v=wt2.v
			)
	)

", $db1);



// for every way found before check if it is a oneway
find_oneways($db1, "
	(SELECT first_way_id AS way_id FROM _tmp_sharp_angles
	UNION
	SELECT second_way_id FROM _tmp_sharp_angles
	) AS w "
, false);



//
// mark as dropped where oneways prohibit transistion from first to second sharp-angled way
// have to consider that a) the way may be tagged oneway and reversed=true
// and b) the way may touch the junction node in any of two directions
//
// scenarios:
//
// a) no one-way tags present
//
//          *
//         / \
//        /   \
//      err   err
//
//
// b) just one one-way tag present
//
//
//          *              *                 *            *
//         ^ \            / ^               v \          / v
//        /   \          /   \             /   \        /   \
//      err   OK       OK   err          OK   err     err   OK
//
//
// c) both have one-way tags
//
//          *              *                 *            *
//         ^ ^            v v               ^ v          v ^
//        /   \          /   \             /   \        /   \
//      OK    OK       OK    OK          err   OK     OK   err
//
//
// one can derive two simple rules covering all cases:
// 1) if a oneway points towards the junction node, the other one cannot turn (and is OK)
// 2) if a oneway points away from the junction node, no one can ever come from here and try turning (the way itself is OK)
// ways not covered by 1) or 2) are errors
//


// first way points away, so it is OK
query("
	UPDATE _tmp_sharp_angles
	SET error_first=false
	FROM _tmp_one_ways ow
	WHERE ow.way_id=_tmp_sharp_angles.first_way_id AND
		NOT XOR(ow.reversed, first_reversed)
", $db1);

// second way points away, so it is OK
query("
	UPDATE _tmp_sharp_angles
	SET error_second=false
	FROM _tmp_one_ways ow
	WHERE ow.way_id=_tmp_sharp_angles.second_way_id AND
		NOT XOR(ow.reversed, second_reversed)
", $db1);


// second way points to junction, so first one is OK
query("
	UPDATE _tmp_sharp_angles
	SET error_first=false
	FROM _tmp_one_ways ow
	WHERE ow.way_id=_tmp_sharp_angles.second_way_id AND
		XOR(ow.reversed, second_reversed)
", $db1);

// first way points to junction, so second one is OK
query("
	UPDATE _tmp_sharp_angles
	SET error_second=false
	FROM _tmp_one_ways ow
	WHERE ow.way_id=_tmp_sharp_angles.first_way_id AND
		XOR(ow.reversed, first_reversed)
", $db1);






// now find all turn restrictions that matter here.
// this will further reduce the number of error candidates
// we need a restiction that prevents driving from first to second way
// AND the other way round
query("DROP TABLE IF EXISTS _tmp_restrictions", $db1);
query("
	CREATE TABLE _tmp_restrictions (
		relation_id bigint NOT NULL,
		from_way bigint NOT NULL,
		to_way bigint NOT NULL,
		rtype varchar(4)
	)
", $db1);


// find all turn restrictions
query("
	INSERT INTO _tmp_restrictions
	SELECT rm1.relation_id, rm1.member_id, rm2.member_id
	FROM relation_members rm1, relation_members rm2

	WHERE rm1.relation_id=rm2.relation_id AND
	rm1.relation_id IN (
		SELECT relation_id
		FROM relation_tags rt
		WHERE rt.relation_id=rm1.relation_id AND
			rt.k='type' AND rt.v='restriction'
	) AND
	rm1.member_id<>rm2.member_id AND
	rm1.member_type='W' AND rm2.member_type='W' AND
	rm1.member_role='from' AND rm2.member_role='to'
", $db1);

// fetch type of restriction
query("
	UPDATE _tmp_restrictions
	SET rtype='no'
	WHERE EXISTS (
		SELECT relation_id
		FROM relation_tags rt
		WHERE rt.relation_id=_tmp_restrictions.relation_id AND
			rt.k='restriction' AND rt.v LIKE 'no%'
	)
", $db1);

// fetch type of restriction
query("
	UPDATE _tmp_restrictions
	SET rtype='only'
	WHERE EXISTS (
		SELECT relation_id
		FROM relation_tags rt
		WHERE rt.relation_id=_tmp_restrictions.relation_id AND
			rt.k='restriction' AND rt.v LIKE 'only%'
	)
", $db1);


// the first way is OK if there is a restriction that prevents
// turning from first to second way
query("
	UPDATE _tmp_sharp_angles
	SET error_first=false
	WHERE error_first AND EXISTS (
		SELECT relation_id
		FROM _tmp_restrictions r
		WHERE
		r.from_way=_tmp_sharp_angles.first_way_id AND r.to_way=_tmp_sharp_angles.second_way_id
		AND rtype='no'
	)
", $db1);

// other way round:
// the second way is OK if there is a restriction that prevents
// turning from second to first way
query("
	UPDATE _tmp_sharp_angles
	SET error_second=false
	WHERE error_second AND EXISTS (
		SELECT relation_id
		FROM _tmp_restrictions r
		WHERE
		r.from_way=_tmp_sharp_angles.second_way_id AND r.to_way=_tmp_sharp_angles.first_way_id
		AND rtype='no'
	)
", $db1);

// second type of restriction: "only"
// the first way is OK if there is a restriction that
// allows turning to only a way that is not the second way
query("
	UPDATE _tmp_sharp_angles
	SET error_first=false
	WHERE error_first AND EXISTS (
		SELECT relation_id
		FROM _tmp_restrictions r
		WHERE
		r.from_way=_tmp_sharp_angles.first_way_id AND r.to_way<>_tmp_sharp_angles.second_way_id
		AND rtype='only'
	)
", $db1);

// and again the other way round:
// the second way is OK if there is a restriction that
// allows turning to only a way that is not the first way
query("
	UPDATE _tmp_sharp_angles
	SET error_second=false
	WHERE error_second AND EXISTS (
		SELECT relation_id
		FROM _tmp_restrictions r
		WHERE
		r.from_way=_tmp_sharp_angles.second_way_id AND r.to_way<>_tmp_sharp_angles.first_way_id
		AND rtype='only'
	)
", $db1);



// no restriction present at all. both ways of turning are possible
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
	SELECT $error_type+1, CAST('node' as type_object_type), junction_id, 'ways $1 and $2 join in a very sharp angle here and there is no oneway tag or turn restriction that prevents turning', first_way_id, second_way_id, NOW()
	FROM _tmp_sharp_angles
	WHERE error_first AND error_second
", $db1);


// one restriction present, so turning only possible in one direction
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
	SELECT $error_type+1, CAST('node' as type_object_type), junction_id, 'ways $1 and $2 join in a very sharp angle here and there is no oneway tag or turn restriction that prevents turning from way $1 to $2', first_way_id, second_way_id, NOW()
	FROM _tmp_sharp_angles
	WHERE error_first AND NOT error_second
", $db1);


// one restriction present, so turning only possible in the other direction
query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
	SELECT $error_type+1, CAST('node' as type_object_type), junction_id, 'ways $1 and $2 join in a very sharp angle here and there is no oneway tag or turn restriction that prevents turning from way $2 to $1', first_way_id, second_way_id, NOW()
	FROM _tmp_sharp_angles
	WHERE error_second AND NOT error_first
", $db1);



query("DROP TABLE IF EXISTS _tmp_jpartners", $db1, false);
query("DROP TABLE IF EXISTS _tmp_sharp_angles", $db1, false);
query("DROP TABLE IF EXISTS _tmp_one_ways", $db1, false);
query("DROP TABLE IF EXISTS _tmp_restrictions", $db1, false);





// part 2:
// travel along all ways and find pairs of sharp angles in between the linestrings
// (not only considering junctions)
// angles of approx. 90 degrees are common, so leave some tolerance and set the limit to 100 degrees
// inspect tupels of 4 nodes in order. The typical case leads to two sharp-angled segments in a row
// where the distance between B and C is usually very short, limit the length to 80 meters.
// select ways where the angle AB - BC as well as BC - CD are sharper than 100 degrees
// remember: totally straight means 180 degrees, so the effective limit is 180-100, that is 80 degrees.
//
//  * A
//   \
//    \     C *
//     \     / \
//      \   /   \
//       \ /     \
//      B *       \
//                 \
//                  \
//                   * D


// add some more way types for this check only
query("
	INSERT INTO _tmp_ways
	SELECT DISTINCT wt.way_id
	FROM way_tags wt LEFT JOIN _tmp_ways w USING (way_id)
	WHERE wt.k='highway' AND
		wt.v IN ('unclassified', 'residential') AND
		w.way_id IS NULL
", $db1);


query("DROP TABLE IF EXISTS _tmp_wn", $db1);
// only the way_nodes that are part of _tmp_ways
// order table by way+sequence as needed by the following query
query("
	CREATE TABLE _tmp_wn AS
	SELECT wn.way_id, wn.sequence_id, wn.lat, wn.lon, wn.x, wn.y
	FROM _tmp_ways w INNER JOIN way_nodes wn USING (way_id)
	ORDER BY wn.way_id, wn.sequence_id
", $db1);

query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("CREATE INDEX idx_tmp_wn_pkey ON _tmp_wn (way_id, sequence_id)", $db1);
query("ANALYZE _tmp_wn", $db1, false);


$angle_limit = cos(80.0 * PI()/180.0);
$length_limit = pow(80.0, 2);	// save one square root calculation

query("
	INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, lat, lon, last_checked)
	SELECT DISTINCT $error_type+2, CAST('way' AS type_object_type), A.way_id, 'this way bends in a very sharp angle here', 1e7*B.lat, 1e7*B.lon, NOW()

	FROM _tmp_wn A, _tmp_wn B, _tmp_wn C, _tmp_wn D
	WHERE A.way_id=B.way_id AND A.way_id=C.way_id AND A.way_id=D.way_id
	AND B.sequence_id=1+A.sequence_id AND C.sequence_id=2+A.sequence_id AND D.sequence_id=3+A.sequence_id
	AND ((A.x-B.x)*(C.x-B.x) + (A.y-B.y)*(C.y-B.y)) >
		SQRT(((A.x-B.x)^2 + (A.y-B.y)^2)*((C.x-B.x)^2 + (C.y-B.y)^2)) * ($angle_limit)
	AND ((B.x-C.x)*(D.x-C.x) + (B.y-C.y)*(D.y-C.y)) >
		SQRT(((D.x-C.x)^2 + (D.y-C.y)^2)*((B.x-C.x)^2 + (B.y-C.y)^2)) * ($angle_limit)
	AND (C.x-B.x)^2 + (C.y-B.y)^2 < ($length_limit)
", $db1);

query("DROP TABLE IF EXISTS _tmp_wn", $db1);

?>
