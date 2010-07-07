<?php

/*
-------------------------------------
-- finding ways that are not connected to the rest of the map
-------------------------------------

thesis: any point in the whole map should be connected to any other node
in other words: from any point in the whole map one should be able to reach
any one of well known points that have to be defined (for performance reasons:
at least one point on every continent).
This check includes even small islands because ferries are considered to be
highways. So it is not neccessary to define starting points on every island.

algorithm: starting in a few nodes find ways connected to given nodes
now find nodes that are member of these ways.
do this until no more nodes/ways are found
any way that now is not member of the list of found ways is an island (not connected)

*/



// these are way_ids picked randomly in central locations
// ways are chosen that seem to be "well-connected" (motorways typically)
$islands = array(
	'europe' => array(
		'Kufstein, Austria' => 4433559,
		'Ponta Delgada, Azores' => 26644602,
		'Bruxelles, Belgium' => 15371932,
		'Minsk, Belarus' => 25455453,
		'Sarajevo, Bosnia and Herzegowina' => 10644674,
		'Split, Croatia' => 25567008,
		'Nicosia, Cyprus' => 4746551,
		'Praha, Czech Republic' => 26167667,
		'Kopenhagen, Denmark' => 5056358,
		'Tallinn, Estonia' => 4554198,
		'Torshavn, Faröer' => 4967431,
		'Berlin, Germany' => 13853652,
		'Düsseldorf, Germany' => 4394506,
		'Frankfurt aM, Germany' => 25119827,
		'Paderborn, Germany' => 30724055,
		'Athens, Greece' => 14292261,
		'Lahti, Finland' => 24318266,
		'Paris, France' => 26503463,
		'Lyon, France' => 4360392,
		'London, Great Britain' => 2499066,
		'Budapest, Hungaria' => 34923072,
		'Reykjavik, Iceland' => 22529614,
		'Roma, Italy' => 28604181,
		'Riga, Latvia' => 38788862,
		'Vilnius, Lithuania' => 4914187,
		'Amsterdam, Netherlands' => 7382660,
		'Oslo, Norway' => 4394237,
		'Warszawa, Poland' => 4990561,
		'Madrid, Spain' => 4680727,
		'Palma de Majorca' => 32694069,
		'Majorca' => 5123287,
		'Stockholm, Sweden' => 39068318,
		'Bern, Switzerland' => 23584688,
		'Kiew, Ukraine' => 4375099,
	),
	'australia' => array(
		'Melbourne' => 20275086,
		'Sydney' => 5152283
	),
	'africa' => array(
		'Ruanda' => 25830659,
		'Lagos, Nigeria' => 13644714,
		'Santa Cruz de Tenerife' => 25458412,
		'Pretoria, South Africa' => 26990144,
		'Marrakesh, Morocco' => 26140716,
		'Tunis, Tunisia' => 26278273,
		'Cairo, Egypt' => 25866550,
		'Antananarivo, Madagascar' => 28916012,
		'Nairobi, Kenya' => 4742016,
		'Saint-Denis, La Reunion' => 31130327,
		'Port Lois, Mauritius' => 22821395,
		'Kinshasa, Kongo' => 4450237,
		'Addis Abeba, Ethiopia' => 8104263,
		'Abijan, Ivory Coast' => 30613353,
		'Mogadishu, Somalia' => 5069961
	),
	'asia' => array(
		'Kuala Lumpur, Malaysia' => 24405048,
		'Baghdad, Iraq' => 4075154,
		'Damascus, Syria' => 28653226,
		'Dubai, U.A.E.' => 24151186,
		'New Delhi, India' => 5873630,
		'Chelyabinsk, Russia' => 32731560,
		'Workuta, Russia' => 48360684,
		'Hanoi, Vietnam' => 9656730,
		'Colombo, Sri Lanka' => 24791916,
		'Tokyo, Japan' => 24039781,
		'Higashi-Fukuma, Japan' => 31125866,
		'Sapporo, Japan' => 30705114,
		'Hiroshima, Japan' => 24818479,
		'Taipeh, Taiwan' => 48776359,
		'Singapore' => 49961799,
		'Medan, Sumatra' => 34337328,
		'Dabo, Pulau Singkep' => 31027937,
		'Jakarta, Java' => 28781825,
		'Surabaya, Java' => 28376237,
		'Makassar, Celebes' => 28919403,
		'Dilli, Timor' => 41199461,
		'Lombok' => 35270707,
		'Bali' => 25132045,
		'Bandar Seri Begawan, Borneo' => 46102068,
		'Labuan, Borneo' => 28717158,
		'Davao City, Mindanao, Philippines' => 28851980,
		'Puerto Princesa (Palawan island), Philippines' => 36983719,
		'Masbate (Masbate island), Philippines' =>28257030,
		'Cebu City, Philippines' => 28817565,
		'Talubhan, Philippines' => 31951418,
		'Jin Island, Philippines' => 27480514
	),
	'north america' => array(
		'Seattle, WA' => 4757176,	//47.4648998, -122.2422833
		'Boise, ID' => 23483176,	//43.5895317, -116.2731857
		'Helena, MT' => 37334501,	//46.5973428, -112.0027184
		'Bismarck, ND' => 9737740,	//46.8227032, -100.8320165
		'Madison, WI' => 38460764,	//43.0663626, -89.277777
		'Lansing, MI' => 32729219,	//42.725782, -84.5473576
		'Albany, NY' => 5566703,	//42.650337, -73.748229
		'Boston, MA' => 9126212,	//42.3565072, -71.1842717
		'Salem, OR' => 29164563,	//44.9283728, -122.9901275
		'Cheyenne, WY' => 15736905,	//41.0617686, -104.8754388
		'Pierre, SD' => 9921998,	//44.354112, -100.370616
		'Santa Clara, CA' => 28433666,	//37.2950053, -121.8729502
		'Sacramento, CA' => 10527056,	//38.576462, -121.485926
		'Reno, NV' => 32776300,		//39.5367148, -119.8036587
		'Salt Lake City, UT' => 32079486, //40.7658591, -111.9348698
		'Denver, CO' => 16966227,	//39.7909665, -104.9884513
		'Lincoln, NE' => 14148564,	//40.8204001, -96.9105351
		'Topeka, KS' => 13251159,	//38.8693256, -95.8354975
		'Des Moines, IA' => 34105509,	//41.5953603, -93.6154316
		'Springfield, IL' => 22012674,	//39.8005698, -89.6478647
		'Indianapolis, IN' => 17483144,	//39.773804, -86.142797
		'Hammond, IN' => 51878120,	//41.5863006, -87.4808194
		'Columbus, OH' => 28827020,	//39.958635, -83.01838
		'Williamsport, PA' => 11985456,	//41.2366445, -76.996752
		'Philadelphia, NJ' => 27053603,	//39.982139, -74.797372
		'Louiseville, KY' => 34149669,	//38.2192664, -85.4325223
		'Elizabethtown, KY' => 16200010,//37.691664, -85.861506
		'Cumberland, KY' => 16199924,	//37.000000, -83.000000
		'Charleston, WV' => 15575629,	//38.4298094, -81.8248983
		'Richmond, VA' => 38232904,	//37.536227, -77.4293533
		'Roanoke, VA' => 44017746,	//37.263769, -79.937871
		'Winchester, VA' => 20616715,	//39.183713, -78.164157
		'Phenix, AZ' => 37439180,	//33.4294139, -112.0827328
		'Santa Fe, NM' => 14612892,	//35.502096, -106.248635
		'Houston, TX' => 15446151,	//29.7360998, -95.3685672
		'Lubbock, TX' => 44530340,	//33.594531, -101.855227
		'Oklahoma City, OK' => 14926398,//35.4628604, -97.4875224
		'Little Rock, AR' => 38287741,	//34.6403829, -92.4451611
		'Baton Rouge, LA' => 27811078,	//30.4391683, -91.1829017
		'Jackson, MS' => 30493865,	//32.2857821, -90.2153904
		'Atlanta, GA' => 28851747,	//33.691751, -84.402198
		'Raleigh, NC' => 18898636,	//35.755875, -78.657918
		'Asheville, NC' => 43596376,	//35.5884879, -82.5254032
		'Columbia, SC' => 34443968,	//34.0101336, -81.0625124
		'Augusta, SC' => 26944246,	//33.4690182, -81.9588167
		'Tallahassee, FL' => 11049098,	//30.483967, -84.041192
		'Orlando, FL' => 32075497,	//28.4792, -81.44891
		'Hartford, CT' => 22772677,	//41.7617184, -72.706668
		'Nashville, TN' => 49577554,	//36.1490548, -86.7802671
		'Knoxville, TN' => 49826007, 	//35.959582, -83.960007
		'Honolulu, HI' => 32005193,	//21.30547, -157.84595
		'Mauna Loa, HI' => 44485670,
		'Kauai, HI' => 45812082,
		'Kahului, HI' => 44713561,
		'Hilo, HI' => 45622327,
		'New Orleans, LA' => 12689320,	//29.95869, -90.077

		'Mexico City' => 24723212,
		'Panama City' => 29425444,
		'Tuxtla Gutierrez, Mexico' => 38049141,
		'Havanna, Cuba' => 38448366,
		'Ottawa, Canada' => 23220188,
		'Quebec, Canada' => 16229066,
		'Calgary, Canada' => 5386816,
		'Santo Domingo, Dominican Republic' => 28118564,
		'Ile de la Gonave, Haiti' => 49014482,
		'San Juan, Puerto Rico' => 22231517,
		'Isla de Vieques' => 22256504,
		'Virgin Islands 1' => 38291731,
		'Virgin Islands 2' => 25875825,
		'Virgin Islands 3' => 24434624,
		'Saint Croix' => 28298216,
		'Basseterre' => 24762738,
		'All Saints' => 13511960,
		'Baie Mahault' => 27266571,
		'Roseau' => 23042676,
		'Dillon, Matinique' => 31729048,
		'St. Lucia' => 25578440,
		'Saint Vincent' => 25577931,
		'St. George\'s' => 25570824,
		'Tobago' => 15242967,
		'Trinidad' => 37576225
	),
	'south america' => array(
		'Lima, Peru' => 11985527,
		'Fortaleza, Brazil' => 23340440,
		'Bogota, Colombia' => 24061680,
		'Sao Paulo, Brazil' => 4615026,
		'Recife, Brazil' => 30811117,
		'Belem, Brazil' => 25171652,
		'Santiago, Chile' => 15729697,
		'Caracas, Venezuela' => 24009279,
		'Georgetown, Guyana' => 30755813,
		'Pt. Stanley, Falkland Islands' => 14143339,
		'Paramaribo, Suriname' => 4383404,
		'Cayenne, Fr. Guyana' => 34316537
	)
);


// include all ways tagged as highway, route=ferry or railway=platform ( a
// platform may connect roads in rare cases)
// include furthermore ways that are part of a route=ferry relation even
// though the ways themselves are not tagged as ferry
query("DROP TABLE IF EXISTS _tmp_ways", $db1);
query("
	CREATE TABLE _tmp_ways AS
	SELECT wt.way_id FROM way_tags wt WHERE (
		wt.k='highway' OR
		(wt.k='route' AND wt.v='ferry') OR
		(wt.k IN ('railway', 'public_transport') AND wt.v='platform')
	)

	UNION

	SELECT rm.member_id
	FROM relation_members rm
	WHERE rm.member_type='W' AND rm.relation_ID IN (
		SELECT rt.relation_id
		FROM relation_tags rt
		WHERE rt.k='route' AND rt.v='ferry'
	)
", $db1);

query("CREATE INDEX idx_tmp_ways_way_id ON _tmp_ways (way_id)", $db1);
query("ANALYZE _tmp_ways", $db1);


// leave out intermediate-nodes that don't interest anybody:
// just these nodes are important, that are used at least twice
// in way_nodes (aka junctions)
// select nodes of ways (and ferries) used at least twice
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT wn.node_id
	FROM way_nodes wn INNER JOIN _tmp_ways w USING (way_id)
	GROUP BY wn.node_id
	HAVING COUNT(DISTINCT wn.way_id)>1
", $db1);

query("CREATE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);
query("ANALYZE _tmp_junctions", $db1);


// first of all find ways that don't have any connection with
// any other way. (these ways are not covered by the rest of the algorithm)
// in _tmp_junctions we only see nodes that are used at least twice
// not finding a record in _tmp_junctions means the way is a not connected way
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT DISTINCT $error_type, CAST('way' AS type_object_type), w.way_id, 'This way is not connected to the rest of the map', NOW()
	FROM _tmp_ways w
	WHERE NOT EXISTS (

		SELECT wn.node_id FROM
		way_nodes wn INNER JOIN _tmp_junctions j USING (node_id)
		WHERE wn.way_id=w.way_id
	)
", $db1);



// this is our optimized (==reduced) version of way_nodes with junctions only
query("DROP TABLE IF EXISTS _tmp_wn", $db1, false);
query("
	CREATE TABLE _tmp_wn AS
	SELECT wn.way_id, j.node_id
	FROM _tmp_junctions j INNER JOIN way_nodes wn USING (node_id)
", $db1);
query("CREATE INDEX idx_tmp_wn_node_id ON _tmp_wn (node_id)", $db1);
query("CREATE INDEX idx_tmp_wn_way_id ON _tmp_wn (way_id)", $db1);
query("ANALYZE _tmp_wn", $db1);

// store the newly found way ids for the current round
query("DROP TABLE IF EXISTS _tmp_ways_found_now ", $db1, false);
query("
	CREATE TABLE _tmp_ways_found_now (
	way_id bigint NOT NULL default 0,
	PRIMARY KEY (way_id)
	)
", $db1);

// store the way ids that were already known from the last rounds
query("DROP TABLE IF EXISTS _tmp_ways_found_before", $db1, false);
query("
	CREATE TABLE _tmp_ways_found_before (
	way_id bigint NOT NULL default 0,
	PRIMARY KEY (way_id)
	)
", $db1);

// temporary table used for newly found nodes
query("DROP TABLE IF EXISTS _tmp_nodes", $db1, false);
query("
	CREATE TABLE _tmp_nodes (
	node_id bigint NOT NULL default 0
	)
", $db1);
query("CREATE INDEX idx_tmp_nodes_node_id ON _tmp_nodes (node_id)", $db1);


// add starting way_ids that are part of islands
$sql = "INSERT INTO _tmp_ways_found_now (way_id) VALUES ";
foreach ($islands as $island=>$ways) foreach ($ways as $dontcare=>$way) $sql.="($way),";

query(substr($sql, 0, -1), $db1);
query("INSERT INTO _tmp_ways_found_before SELECT way_id FROM _tmp_ways_found_now ", $db1);
$analyze_counter=0;
do {
	// first find nodes that belong to ways found in the last round
	// it is sufficient to only consider ways found during the round before here!
	query("TRUNCATE TABLE _tmp_nodes", $db1, false);
	query("
		INSERT INTO _tmp_nodes (node_id)
		SELECT DISTINCT wn.node_id
		FROM _tmp_ways_found_now w INNER JOIN _tmp_wn wn USING (way_id)
	", $db1, false);
	if (++$analyze_counter % 10 == 0) query("ANALYZE _tmp_nodes", $db1, false);

	// remove ways of last round
	query("TRUNCATE TABLE _tmp_ways_found_now ", $db1, false);

	// insert ways that are connected to nodes found before. these make the starting
	// set for the next round
	$result=query("
		INSERT INTO _tmp_ways_found_now (way_id)
		SELECT DISTINCT wn.way_id
		FROM (_tmp_wn wn INNER JOIN _tmp_nodes n USING (node_id)) LEFT JOIN _tmp_ways_found_before w ON wn.way_id=w.way_id
		WHERE w.way_id IS NULL
	", $db1, false);
	$count=pg_affected_rows($result);
	if ($analyze_counter % 10 == 0) {
		query("ANALYZE _tmp_ways_found_now ", $db1, false);
		query("ANALYZE _tmp_ways_found_before", $db1, false);
	}

	// finally add newly found ways in collector table containing all ways
	query("INSERT INTO _tmp_ways_found_before SELECT way_id FROM _tmp_ways_found_now ", $db1, false);
	echo "found $count additional ways\n";
} while ($count>0);



// any way that exists in way-temp-table but is not member of any island is an error
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, msgid, last_checked)
	SELECT DISTINCT $error_type, CAST('way' AS type_object_type), wn.way_id, 'This way is not connected to the rest of the map', NOW()
	FROM _tmp_wn wn LEFT JOIN _tmp_ways_found_before w USING (way_id)
	WHERE w.way_id IS NULL
", $db1);



query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("DROP TABLE IF EXISTS _tmp_nodes", $db1, false);
query("DROP TABLE IF EXISTS _tmp_junctions", $db1, false);
query("DROP TABLE IF EXISTS _tmp_island_members", $db1, false);
query("DROP TABLE IF EXISTS _tmp_wn", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways_found_now ", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways_found_before", $db1, false);

?>