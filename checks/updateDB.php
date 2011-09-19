<?php


require('prepare_helpertables.php');
require('prepare_countries.php');



// updateDB asserts a populated database ready for running checks
function updateDB($schema) {

	planet_update($schema);

	emptyDB($schema);

	postprocess_datafiles();
	loadDB($schema);

	prepare_helpertables($schema);

	prepare_countries($schema);

}


function emptyDB($schema) {
	global $config;

	$db = pg_pconnect(connectstring($schema));

	query('TRUNCATE relation_tags', $db);
	query('TRUNCATE relation_members', $db);
	query('TRUNCATE relations', $db);
	query('TRUNCATE way_nodes', $db);
	query('TRUNCATE way_tags', $db);
	query('TRUNCATE ways', $db);
	query('TRUNCATE node_tags', $db);
	query('TRUNCATE nodes', $db);
	//query('TRUNCATE users', $db);

	pg_close($db);
}



// postprocessing on tab-separated data files created by osmosis before they
// can be inserted in postgres
// manually join table way_nodes and nodes to add node coordinates to way_nodes2
// manually join table ways with nodes to add node coordinates of first and last node to ways
function postprocess_datafiles() {
	global $config;
	$OSMOSIS_OUTPUT_DIRECTORY=$config['temp_dir'];
	$SORTOPTIONS='--temporary-directory="' . $config['temp_dir'] . '"';

	$cmd=
"cd \"$OSMOSIS_OUTPUT_DIRECTORY\"
echo \"`date` * joining way_nodes and node coordinates\"
sort $SORTOPTIONS -n -k 2 way_nodes.txt > way_nodes_sorted.txt
rm way_nodes.txt
sort $SORTOPTIONS -n -k 1 nodes.txt > nodes_sorted.txt
rm nodes.txt
join -t \"	\" -e NULL -a 1 -1 2 -o 1.1,0,1.3,2.5,2.6,2.7,2.8 way_nodes_sorted.txt nodes_sorted.txt > way_nodes2.txt
rm way_nodes_sorted.txt

echo \"`date` * joining ways with coordinates of first and last node\"
sort $SORTOPTIONS -t \"	\" -n -k 4 ways.txt > ways_sorted.txt
rm ways.txt
join -t \"	\" -e NULL -a 1 -1 4 -o 1.1,1.2,1.3,0,1.5,2.5,2.6,2.7,2.8,1.6 ways_sorted.txt nodes_sorted.txt > ways2.txt
sort $SORTOPTIONS -t \"	\" -n -k 5 ways2.txt > ways_sorted.txt
rm ways2.txt
join -t \"	\" -e NULL -1 5 -o 1.1,1.2,1.3,1.4,0,1.6,1.7,1.8,1.9,2.5,2.6,2.7,2.8,1.10 ways_sorted.txt nodes_sorted.txt > ways.txt
rm ways_sorted.txt
";

	logger($cmd, KR_COMMANDS);
	system($cmd, $errorlevel);
	if ($errorlevel) {
		logger("postprocess_datafiles: exit with errorlevel $errorlevel", KR_ERROR);
		exit(1);
	}

}


function loadDB($schema) {
	global $config;

	$db = pg_pconnect(connectstring($schema));

	// execute load data script but replace temp-path for data files created by osmosis
	$sql = file_get_contents($config['base_dir'].'planet/pgsql_simple_load.sql');
	$sql = str_replace('%TEMPDIR%', $config['temp_dir'], $sql);

	query($sql, $db);

	pg_close($db);


	// eventually delete data files to save disk space
        if (!$config['keep_database_after_processing']) {

		$cmd='cd "' . $config['temp_dir'] . '" && ' .
			'rm nodes_sorted.txt node_tags.txt relation_members.txt relations.txt relation_tags.txt users.txt way_nodes2.txt ways.txt way_tags.txt';

		logger($cmd, KR_COMMANDS);
		system($cmd, $errorlevel);
		if ($errorlevel) {
			logger('loadDB: error on deleting db source data files', KR_ERROR);
			exit(1);
		}
	}
}

?>