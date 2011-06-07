<?php
require_once('helpers.inc.php');

//
//  OpenStreetMap website tag validator.  Using heuristics, flag websites
//  that (no longer) seem to match their element.
//
//  References
//      http://wiki.openstreetmap.org/wiki/Key:website
//      http://wiki.openstreetmap.org/wiki/Key:url
//
//  Requirements
//	http://www.php.net/manual/en/book.curl.php
//
//  Author: Bryce Nesbitt, May 2011
//

// this script needs cURL. If it doesn't work, maybe you have to install
// a package like php5-curl


// these tags may contain URLs
$checkable_tags = array('website','url','website:mobile','contact:website');

// Fuzzy search full text of page for matching words.
// try to find the content of these tags on the website to ensure authenticity
$keys_to_search = array('name','website:searchstring','phone','alt_name',
			  'operator','addr:street','frequency');


// this script has two ways of execution:
// * with a filename as command line parameter (for testing):
//	treat the file name as osm planet file data source
// otherwise:
// * wit standard command line parameters (for running inside keepright):
//	run with data from the database



if ($argc>=2 && is_readable($argv[1])) {

	exit (run_standalone());

} else {

	$tables = array('node'=>'node_tags', 'way'=>'way_tags', 'relation'=>'relation_tags');

	foreach ($tables as $object_type=>$table) {
		run_keepright($db1, $db2, $object_type, $table);
	}
}



function run_keepright($db1, $db2, $object_type, $table) {
	global $error_type, $checkable_tags;


	echo "checking on $table...\n";
	$urls_checked=0;
	$errors=0;

	// first find objects with URL tags
	$result1=query("SELECT {$object_type}_id, k, v FROM $table
		WHERE k IN ('" . implode("', '", $checkable_tags) . "')", $db1, false);

	while ($row1=pg_fetch_array($result1, NULL, PGSQL_ASSOC)) {


		$obj=array(id=>$row1[$object_type . '_id']);

		// second: find all tags of those objects
		$result2=query("SELECT k, v FROM $table
			WHERE {$object_type}_id=" . $row1[$object_type . '_id'], $db2, false);

		while ($row2=pg_fetch_array($result2, NULL, PGSQL_ASSOC)) {
			$obj[$row2['k']]=$row2['v'];
		}
		pg_free_result($result2);


		// third: match them!
		$urls_checked++;
		echo "checking URL " . $row1['v'] . "\n";
		$ret=fetchcompare_website_tag($obj, $row1['v']);
		if ($ret !== null) {
			$errors++;

			// avoid apos crash the SQL-string
			$msgid=pg_escape_string($db2, $ret[0]);
			$txt1=pg_escape_string($db2, $ret[1]);
			$txt2=pg_escape_string($db2, $ret[2]);

			query("
				INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
				VALUES ($error_type, '$object_type', " . $obj['id'] . ", '$msgid', '$txt1', '$txt2', NOW())
			", $db2, false);

			echo "error on URL " . $row1['v'] . "\n";
			print_r($obj);
			echo "result:\n";
			print_r($ret);

		}

	}
	pg_free_result($result1);

	echo "matched $urls_checked URLs with $errors errors.\n";
}


function run_standalone() {
	global $argc, $argv;


	//  Command line parsing
	$target_id = null;
	if($argc > 1) {
		$planet_file = $argv[1];
	} else {
		print "Usage: [planet file] <OSM id number\n";
		return(5);
	}
	if($argc > 2) {
		$target_id   = $argv[2];
	}

	//
	//  Stream a planet file or planet file subset.
	//  Call fetchcompare_website_tag() for any checkable elements.
	//
	//  IDEAS
	//	  * Process colons (e.g. "website:*");
	//	  * Process semicolons (e.g. "amenity=cafe;bar");
	//
	$checkable_tags = array('website','url','website:mobile','contact:website');
	$reader = new XMLReader();
	$reader->open($planet_file);	// Would be nice to stream bz2 files here 
	$element = array();
	while ($reader->read()) {
		switch ($reader->nodeType) {

		// Collect all key/value pairs as we stream
		case (XMLREADER::ELEMENT):
			switch( $reader->localName ) {
				case "node":
				case "way":
					$element['id']=$reader->getAttribute("id");
					break;
				case "tag":
					$element[$reader->getAttribute("k")]=$reader->getAttribute("v");
					break;
				}
			break;

		// As each end tag is hit, process the key/value pairs collected above
		case (XMLREADER::END_ELEMENT):
			switch( $reader->localName ) {
				case "node":
				case "way":
					// Skip features if we're skipping
					if( $target_id && $target_id != $element['id'] ) {
						$element = array();
						break;
					}

					// Process element
					foreach( $checkable_tags as $tag ) {
						if( isset($element[$tag]) ) {
							$rv=fetchcompare_website_tag($element, $element[$tag]);
							if($rv) {
								print "$rv\n";
								print_r($element);
							} else {
								print "Checked $element[id]: $element[$tag]\n";
							}
						}
					}
					$element = array();	// Clear out collection bucket
					break;
			}
			break;
		}
	}
	return 0;
}


//  *******************************************************************
//
//  INTRO
//      Load the given website and search for evidience it maches the given
//      OpenStreetMap element.  Process tags such as "name", "operator",
//      "phone" seeking some fuzzy evidence of a match.
//
//  INPUTS
//          $osm_element - OpenStreetMap element as php associative array.
//                         Place the OSM element ID in key $osm_element['id'].
//          $url         - url to search
//
//  RESULTS
//      Null, or a humnan readable text string explaining the non-match.
//
//  IDEAS FOR EXTENSION:
//      * Handle framesets. Yes, people still use them.
//      * Fuzzy match phone numbers (the hard matching rarely works).
//        (510)-555-1234 should match 510.555.1235
//      * Validate wikipedia tags http://taginfo.openstreetmap.de/keys/wikipedia#values
//      * Catch PHP warnings so regex issues can be tracked down.  See
//      http://verysimple.com/2010/11/02/catching-php-errors-warnings-and-notices/
//      * Work harder to avoid explired domain hijack pages.
//      * Use an Accept-Language header that matches the mapped region.
//
function fetchcompare_website_tag($osm_element, $url_under_test)
{
	global $keys_to_search;
	$w = '[\s\S]*?'; //ungreedy wildcard - matches anything
	$z = '[\h\v]*?'; //ungreedy wildcard - matches whitespace only
	$searchedfor = "";

	// Normalize given URL.  Per spec, default to http:// if no protocol given.
	$url = trim($url_under_test);
	if(!preg_match("|.*?://|i",$url)) {
		$url = "http://".$url;
	}

	// Fetch URL using curl into string $response
	$ch = curl_init();
	$curlopt = array(
		CURLOPT_URL             => $url,
		CURLOPT_TIMEOUT         => 5,

		CURLOPT_RETURNTRANSFER  => true,
		CURLOPT_FOLLOWLOCATION  => true,
		CURLOPT_AUTOREFERER     => true,
		CURLOPT_MAXREDIRS       => 50,
		CURLOPT_HEADER          => false,

		CURLOPT_USERAGENT =>
		'KeepRightBot/0.1 (KeepRight OpenStreetMap Checker; http://keepright.ipax.at)',
		CURLOPT_HTTPHEADER => array('Accept-Language: en')
	);
	curl_setopt_array($ch, $curlopt);
	$response = curl_exec($ch);
	if($response === false) {
		return(array('The URL ($1) cannot be opened ($2)', $url_under_test, curl_error($ch)));
		//return("Error $osm_element[id]: $url ".curl_error($ch));
	}
	$http_status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$http_eurl	= curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	$http_encoding= curl_getinfo($ch, CURLINFO_CONTENT_TYPE);

	if(false) {
		print "Curl debug start $http_eurl:\n";
		print_r(curl_getinfo($ch));
		print "Body strlen=".strlen($response)." mb_strlen=".
			   mb_strlen($response).":\n$response\nCurl debug end\n\n";
	}
	curl_close($ch);

	// Only accept success
	if($http_status < 200 || $http_status > 299) {
		return(array('The URL ($1) cannot be opened (HTTP status code $2)', $url_under_test, $http_status));
		//return("Error $osm_element[id]: $http_eurl gave HTTP Code $http_status");
	}

	// Follow relevant http-equiv="refresh" meta tags.  For example:
	// <meta http-equiv="refresh" content="0;url= Site/Welcome.html" />
	// <meta http-equiv="refresh" content="1200;url=http://www.slavonski-brod.hr/">
	//
	// TODO: this parsing could be more robust
	//	   consider http://simplehtmldom.sourceforge.net/
	//	   We could then search by structure, rather than
	//	   just string manipulation.
	if(preg_match("/meta${z}http-equiv$z=$z\"refresh\".*content$z=$z\".*url$z=(.*)\"/i", $response,$match)) {
		$temp = trim($match[1]);
		if(preg_match("|http://|",$temp)) {
			$newurl = $temp;
		} else {
			$newurl = $url.'/'.$temp;
		}
		if(!( $newurl == $http_eurl )) {
			return(fetchcompare_website_tag($osm_element, $newurl));
			}
		}

	// Try to get our copy of the page match the encoding of the OSM tags
	if(preg_match("/meta${z}http-equiv$z=$z\"content-type\".*content$z=$z\".*?charset$z=$z([\w-]*)/i", $response,$match)) {
		$http_encoding = strtolower($match[1]);
		if($http_encoding !== 'utf-8') {
			$response = iconv($http_encoding,'UTF-8//IGNORE',$response);
		}
	}
	$response = html_entity_decode($response,ENT_NOQUOTES,'UTF-8');

	//
	// Heuristics to flag probable domain squatting
	//
	$squat_strings = array(
		"http://www.acquirethisname.com",
		"__changoPartnerId='parkedcom'",
	);
	$cnt = count($squat_strings);
	for ($i = 0; $i < $cnt; $i++) {
		$squat_strings[$i] = preg_quote($squat_strings[$i],"/");
	}
	$temp = join($squat_strings,'|');
	if(preg_match("/$temp/", $response, $matches)) {
		return(array('Possible domain squatting: $1. Suspicious text is: "$2"', $http_eurl, $matches[0]));
		//return "Possible domain squatting: $osm_element[id] $http_eurl.  Suspicious text is: \"$matches[0].\"";
	}


	//
	// Fuzzy search full text of page
	// Our goal is to find something -- anything -- that matches between the OSM
	// tags and the actual page content.
	//
	foreach($keys_to_search as $key) {
		if(isset($osm_element[$key])) {


			// Check the value as given
			$value_straight   = $osm_element[$key];
			$searchedfor .= "✔".$value_straight;
			$temp = preg_quote($value_straight,'|');
			$temp = preg_replace('|\s|',"$z",$temp);
			if(preg_match("|$temp|i", $response)) {
				return(null);   // Match!
			}

			// Strip out diacriticals.  Though, php's defective iconv makes this hard.
			// Ideally it would convert like so:
			//	  grüßen<200e>!
			//	  grussen!
			$value_stripped   = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value_straight);
			$value_stripped   = str_replace("?",'',$value_stripped); 
			if( $value_stripped !== $value_straight ) {
				$searchedfor .= "✔".$value_stripped;
				$temp = preg_quote($value_stripped,'|');
				$temp = preg_replace('|\s|',"$z",$temp);
				if(preg_match("|$temp|i", $response)) {
					return(null);   // Match!
				}
			}

			// Remove ' and check
			$value_apostrophe = str_replace("'",'',$value_straight);
			if( $value_straight !== $value_apostrophe ) {
				$searchedfor .= "✔".$value_apostrophe;
				$temp = preg_quote($value_apostrophe,'|');
				$temp = preg_replace('|\s|',"$z",$temp);
				if(preg_match("|$temp|i", $response)) {
					return(null);   // Match!
				}
			}
		}
	}

	// Fall through with failure to match
	//$searchedfor = substr($searchedfor,1);
	//echo "No match at element $osm_element[id] $http_eurl with encoding $http_encoding. Checks made: \"$searchedfor\" from tags $keys_to_search\n";

	return array('Content of the URL ($1) could not be matched with tags on the element ($2)', $http_eurl, $searchedfor);

	}

?>