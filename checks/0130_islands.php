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
		'Sarajevo, Bosnia and Herzegowina' => 10644674,
		'Nicosia, Cyprus' => 4746551,
		'Praha, Czech Republic' => 26167667,
		'Kopenhagen, Denmark' => 5056358,
		'Torshavn, Faröer' => 4967431,
		'Berlin, Germany' => 13853652,
		'Düsseldorf, Germany' => 4394506,
		'Athens, Greece' => 14292261,
		'Lahti, Finland' => 24318266,
		'Paris, France' => 26503463,
		'London, Great Britain' => 2499066,
		'Budapest, Hungaria' => 34923072,
		'Reykjavik, Iceland' => 22529614,
		'Roma, Italy' => 28604181,
		'Riga, Latvia' => 38788862,
		'Amsterdam, Netherlands' => 7382660,
		'Oslo, Norway' => 4394237,
		'Warszawa, Poland' => 4990561,
		'Madrid, Spain' => 4680727,
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
		'Antananarivo, Madagascar' => 27352405,
		'Nairobi, Kenya' => 4742016,
		'Saint-Denis, La Reunion' => 31130327,
		'Port Lois, Mauritius' => 22821395,
		'Kinshasa, Kongo' => 4450237,
		'Addis Abeba, Ethiopia' => 8104263,
		'Abijan, Ivory Coast' => 30613353,
		'Mogadishu, Somalia' => 5069961
	),
	'asia' => array(
		'Manila, Philippines' => 4794399,
		'Kuala Lumpur, Malaysia' => 5074927,
		'Baghdad, Iraq' => 4075154,
		'Damascus, Syria' => 28653226,
		'New Delhi, India' => 5873630,
		'Chelyabinsk, Russia' => 32731560,
		'Workuta, Russia' => 31838171,
		'Hanoi, Vietnam' => 9656730,
		'Colombo, Sri Lanka' => 24791916,
		'Tokyo, Japan' => 24039781,
		'Higashi-Fukuma, Japan' => 369681624,
		'Sapporo, Japan' => 30705114,
		'Taipeh, Taiwan' => 187243326,
		'Singapore' => 133745551,
		'Medan, Sumatra' => 34337328,
		'Dabo, Pulau Singkep' => 31027937,
		'Jakarta, Java' => 28781825,
		'Surabaya, Java' => 28376237,
		'Makassar, Celebes' => 28919403,
		'Dilli, Timor' => 24240157,
		'Lombok' => 35270707,
		'Bali' => 25132045,
		'Bandar Seri Begawan, Borneo' => 19296686,
		'Labuan, Borneo' => 28717158,
		'Davao City, Mindanao, Philippines' => 372702884,
		'Davao City, Mindanao, Philippines' => 28851980,
		'Puerto Princesa (Palawan island), Philippines' => 36983719,
		'Masbate (Masbate island), Philippines' =>28257030,
		'Cebu City, Philippines' => 28817565,
		'Talubhan, Philippines' => 31951418,
		'Jin Island, Philippines' => 27480514
	),
	'north america' => array(
		'Seattle, WA' => 4757176,
		'Boise, ID' => 23483176,
		'Helena, MT' => 37334501,
		'Bismarck, ND' => 9737740,
		'Saint Paul, MN' => 18210903,
		'Madison, WI' => 38460764,
		'Lansing, MI' => 32729219,
		'Albany, NY' => 5566703,
		'Boston, MA' => 9126212,
		'Salem, OR' => 29164563,
		'Cheyenne, WY' => 15736905,
		'Pierre, SD' => 9921998,
		'Santa Clara, CA' => 28433666,
		'Reno, NV' => 32776300,
		'Salt Lake City, UT' => 32079486,
		'Denver, CO' => 16966227,
		'Lincoln, NE' => 14148564,
		'Topeka, KS' => 13251159,
		'Des Moines, IA' => 34105509,
		'Springfield, IL' => 22012674,
		'Indianapolis, IN' => 17483144,
		'Columbus, OH' => 28827020,
		'Pittsburgh, PA' => 24859994,
		'Philadelphia, NJ' => 27053604,
		'Louiseville, KY' => 34149669,
		'Charleston, WV' => 15575629,
		'Richmond, VA' => 38232904,
		'Washington, DC' => 29497657,
		'Phenix, AZ' => 37439180,
		'Santa Fe, NM' => 14612892,
		'Houston, TX' => 15446151,
		'Oklahoma City, OK' => 14926398,
		'Little Rock, AR' => 38287741,
		'Baton Rouge, LA' => 27811078,
		'Memphis, TN' => 19627507,
		'Jackson, MS' => 30493865,
		'Montgomery, AL' => 7934589,
		'Atlanta, GA' => 28851747,
		'Raleigh, NC' => 18898636,
		'Columbia, SC' => 34443968,
		'Tallahassee, FL' => 11049098,

		'Mexico City' => 24723212,
		'Havanna, Cuba' => 38448366,
		'Ottawa, Canada' => 23220188,
		'Santo Domingo, Dominican Republic' => 28118564,
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
		'Dillon' => 25861959,
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
		'Maceijo, Brazil' => 28639929,
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



// leave out intermediate-nodes that don't interest anybody:
// just these nodes are important, that are used at least twice
// in way_nodes (aka junctions)
// select nodes of ways (and ferries) used at least twice
query("DROP TABLE IF EXISTS _tmp_junctions", $db1);
query("
	CREATE TABLE _tmp_junctions AS
	SELECT node_id
	FROM way_nodes wn
	WHERE EXISTS (
		SELECT * FROM way_tags t WHERE t.way_id=wn.way_id AND (t.k='highway' OR (t.k='route' AND t.v='ferry') OR (t.k='railway' AND t.v='platform'))
	)
	GROUP BY node_id
	HAVING COUNT(DISTINCT way_id)>1
", $db1);
query("CREATE INDEX idx_tmp_junctions_node_id ON _tmp_junctions (node_id)", $db1);
query("ANALYZE _tmp_junctions", $db1);

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

// _tmp_island_members will hold the elitair nodes that belong to an island
query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("
	CREATE TABLE _tmp_ways (
	way_id bigint NOT NULL default 0,
	PRIMARY KEY (way_id)
	)
", $db1);
add_insert_ignore_rule('_tmp_ways', 'way_id', $db1);

query("DROP TABLE IF EXISTS _tmp_ways2", $db1, false);
query("
	CREATE TABLE _tmp_ways2 (
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
$sql = "INSERT INTO _tmp_ways (way_id) VALUES ";
foreach ($islands as $island=>$ways) foreach ($ways as $dontcare=>$way) $sql.="($way),";

query(substr($sql, 0, -1), $db1);
query("INSERT INTO _tmp_ways2 SELECT way_id FROM _tmp_ways", $db1);
$analyze_counter=0;
do {
	// first find nodes that belong to ways found in the last round
	query("TRUNCATE TABLE _tmp_nodes", $db1, false);
	query("
		INSERT INTO _tmp_nodes (node_id)
		SELECT DISTINCT wn.node_id
		FROM _tmp_ways w INNER JOIN _tmp_wn wn USING (way_id)
	", $db1, false);
	if (++$analyze_counter % 10 == 0) query("ANALYZE _tmp_nodes", $db1, false);

	// remove ways of last round
	query("TRUNCATE TABLE _tmp_ways", $db1, false);

	// insert ways that are connected to nodes found before. these make the starting
	// set for the next round
	$result=query("
		INSERT INTO _tmp_ways (way_id)
		SELECT DISTINCT wn.way_id
		FROM (_tmp_wn wn INNER JOIN _tmp_nodes n USING (node_id)) LEFT JOIN _tmp_ways2 w ON wn.way_id=w.way_id
		WHERE w.way_id IS NULL
	", $db1, false);
	$count=pg_affected_rows($result);
	if ($analyze_counter % 10 == 0) {
		query("ANALYZE _tmp_ways", $db1, false);
		query("ANALYZE _tmp_ways2", $db1, false);
	}

	// remember any newly found way in separate table
	query("INSERT INTO _tmp_ways2 SELECT way_id FROM _tmp_ways", $db1, false);
	echo "found $count additional ways\n";
} while ($count>0);



// any way that exists in way-temp-table but is not member of any island is an error
query("
	INSERT INTO _tmp_errors (error_type, object_type, object_id, description, last_checked)
	SELECT DISTINCT $error_type, CAST('way' AS type_object_type), wn.way_id, 'This way is not connected to the rest of the map', NOW()
	FROM _tmp_wn wn LEFT JOIN _tmp_ways2 w USING (way_id)
	WHERE w.way_id IS NULL
", $db1);



query("DROP TABLE IF EXISTS _tmp_nodes", $db1, false);
query("DROP TABLE IF EXISTS _tmp_junctions", $db1, false);
query("DROP TABLE IF EXISTS _tmp_island_members", $db1, false);
query("DROP TABLE IF EXISTS _tmp_wn", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways", $db1, false);
query("DROP TABLE IF EXISTS _tmp_ways2", $db1, false);

?>