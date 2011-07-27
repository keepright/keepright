<?php
require_once('helpers.inc.php');
require_once('rolling-curl/RollingCurl.php');

//
//  OpenStreetMap website tag validator.  Using heuristics, flag websites
//  that (no longer) seem to match their element.
//
//  References
//      http://wiki.openstreetmap.org/wiki/Key:website
//      http://wiki.openstreetmap.org/wiki/Key:url
//
//  Requirements
//	http://www.php.net/manual/en/book.curl.php (sudo apt-get install php5-curl)
//
//  Ideas/TODO
//      Run in parallel with http://code.google.com/p/rolling-curl/
//	    Process colons (e.g. "website:*");
//	    Process semicolons (e.g. "amenity=cafe;bar");
//      Follow meta-refresh redirects properly
//
//  Tips for OSM editors
//      1) Simplify URL's to make them more robust:
//          Bad:  www.foo.com/home_page.php?a=b
//          Good: www.foo.com/
//      2) Check for extra spaces or junk in the OSM tags.
//      3) If you can't get a match any other way, add to OSM a
//      "website:searchstring" tag with some text from the existing valid website.
//
//  Author: Bryce Nesbitt, May 2011
//


// this script has two ways of execution:
// * with a filename as command line parameter (for testing):
//	treat the file name as osm planet file data source
// otherwise:
// * with standard command line parameters (for running inside keepright):
//	run with data from the database


// these tags may contain URLs
$checkable_tags = array('website','url','website:mobile','contact:website');

// these tags may contain text which can validate the website matches the osm element:
// two flavors: fixed strings or regexes can be used
$keys_to_search_fixed = array('name','alt_name','website:searchstring','phone',
			  'operator','addr:street','frequency');

$keys_to_search_regex = array('name:[a-z]{2}');


// never try to match these URLs
// these are regexes and they are applied in case insensitive manner automatically!
$whitelist = array(
	'^http://www.internationalboundarycommission.org/coordinates/',	// match beginning of string
	'^http://ancien-geodesie.ign.fr/',				// match beginning of string
	'.pdf$'								// matching pdf files not useful
);

// used for identifying domainsquatting
$squat_strings = array(
	"http://www.acquirethisname.com",
	"__changoPartnerId='parkedcom'",
);



$curlopt = array(
    CURLOPT_USERAGENT       => 'KeepRightBot/0.1 (KeepRight OpenStreetMap Checker; http://keepright.ipax.at)',
    CURLOPT_HTTPHEADER      => array('Accept-Language: en'),
    CURLOPT_FOLLOWLOCATION  => true,
    CURLOPT_AUTOREFERER     => true,
    CURLOPT_MAXREDIRS       => 50,
);

// ****************************************************************************************************** //
$debug  = 0;
$w = '[\s\S]*?'; //ungreedy wildcard - matches anything
$z = '[\h\v]*?'; //ungreedy wildcard - matches whitespace only


// prepare regexes for domainsquatting search
$cnt = count($squat_strings);
for ($i = 0; $i < $cnt; $i++) {
	$squat_strings[$i] = preg_quote($squat_strings[$i],"/");
}


if ($argc>=2 && is_readable($argv[1])) {

	exit (run_standalone());

} else {

	$tables = array('node'=>'node_tags', 'way'=>'way_tags', 'relation'=>'relation_tags');

	foreach ($tables as $object_type=>$table) {
		run_keepright($db1, $db2, $object_type, $table);
	}
}



function run_keepright($db1, $db2, $object_type, $table) {
	global $error_type, $checkable_tags, $whitelist, $error_count, $curlopt;


	echo "checking on $table...\n";
	$urls_queued=0;
	$error_count=0;

	$rc = new RollingCurl("run_keepright_callback");
	$rc->options = $curlopt;
	$rc->window_size = 20;		// number of concurrent URLs to open

	// first find objects with URL tags and exclude whitelisted URLs
	$result1=query("
		SELECT {$object_type}_id, MAX(v) AS url
		FROM $table
		WHERE k IN ('" . implode("', '", $checkable_tags) . "') AND
		NOT (v ~* '" . implode("' OR v ~* '", $whitelist) . "')
		GROUP BY {$object_type}_id
	", $db1);




	while ($row1=pg_fetch_array($result1, NULL, PGSQL_ASSOC)) {


		$obj=array('id'=>$row1[$object_type . '_id'], 'object_type'=>$object_type);

		// second: find all tags of those objects
		$result2=query("SELECT k, v FROM $table
			WHERE {$object_type}_id=" . $row1[$object_type . '_id'], $db2, false);

		while ($row2=pg_fetch_array($result2, NULL, PGSQL_ASSOC)) {
			$obj[$row2['k']]=$row2['v'];
		}
		pg_free_result($result2);


		// third: match them!
		$urls_queued++;
		echo "queueing URL " . $row1['url'] . "\n";
		queueURL($rc, $obj, $row1['url']);


	}
	pg_free_result($result1);

	#   Execute web requets queued above
	if ($urls_queued) $rc->execute();

	echo "matched $urls_queued URLs with $error_count errors.\n";
}


function run_standalone() {
	global $argc, $argv, $checkable_tags, $curlopt;

	$urls_queued=0;

	//  Command line parsing
	$target_id = null;
	if($argc > 1) {
		$planet_file = $argv[1];
	} else {
		print "Usage: [planet file] <OSM id number\n";
		return(5);
	}
	if($argc > 2) {
		$target_id = $argv[2];
	}

	//
	//  Stream a planet file or planet file subset.
	//  Call fetchcompare_website_tag() for any checkable elements.
	//
	//  IDEAS
	//	  * Process colons (e.g. "website:*");
	//	  * Process semicolons (e.g. "amenity=cafe;bar");
	//
	$reader = new XMLReader();
	$reader->open($planet_file);	// Would be nice to stream bz2 or pbf files here
	$element = array();

	$rc = new RollingCurl("run_standalone_callback");
	$rc->options = $curlopt;
	$rc->window_size = 20;		// number of concurrent URLs to open

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
							if(whitelisted($element[$tag])) {
								echo "skipping whitelisted URL {$element[$tag]} on ID $element[id]\n";
							} else {
								echo "queueing URL " . $element[$tag] . "\n";
								queueURL($rc, $element, $element[$tag]);
								$urls_queued++;
							}
						}
					}
			}
			$element = array();	// Clear out collection bucket
			break;
		}
	}

	if ($urls_queued) $rc->execute();
	return 0;
}



// returns true if the given URL is whitelisted
function whitelisted($URL) {
	global $whitelist;

	foreach ($whitelist as $pattern) {
		if (preg_match('@' . $pattern . '@i', $URL)) return true;
	}

	return false;
}


// push an element onto the request queue
function queueURL(&$rc, $element, $url) {

	// Normalize given URL.  Per spec, default to http:// if no protocol given.
	$url = trim($url);
	if(!preg_match("|.*?://|i",$url)) {
		$url = "http://".$url;
	}

	// Queue for later
	//print "Queue $url on ID $element[id]\n";
	$request = new RollingCurlRequest($url);
	$request->callback_data = $element;
	$rc->add($request);
}



// handle http response in standalone mode
function run_standalone_callback($response, $info, $request) {
	if($info['http_code'] < 200 || $info['http_code'] > 299) {
		print_r(array('type'=>1, 'The URL ($1) cannot be opened (HTTP status code $2)', $request->url, $info['http_code']));
	return;
	}
	$rv=fuzzy_compare($response, $request->callback_data, $request->url);
	print_r($rv);
}


// handle http response in keepright mode
function run_keepright_callback($response, $info, $request) {
	global $db2, $error_type, $error_count;

	if($info['http_code'] < 200 || $info['http_code'] > 299) {
		print_r(array('type'=>1, 'The URL ($1) cannot be opened (HTTP status code $2)', $request->url, $info['http_code']));
	return;
	}
	$obj = $request->callback_data;
	$ret=fuzzy_compare($response, $obj, $request->url);


	if ($ret !== null) {
		$error_count++;

		// avoid apos crash the SQL-string
		$msgid=pg_escape_string($db2, $ret[0]);
		$txt1=pg_escape_string($db2, $ret[1]);
		$txt2=pg_escape_string($db2, $ret[2]);

		query("
			INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
			VALUES ($error_type + " . $ret['type'] . ", '" . $obj['object_type'] . "', " . $obj['id'] . ", '$msgid', '$txt1', '$txt2', NOW())
		", $db2, false);

		echo "error on URL " . $request->url . "\n";
		print_r($obj);
		echo "result:\n";
		print_r($ret);

	}

	print_r($rv);
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

function fuzzy_compare($response, $osm_element, $http_eurl) {
	global $keys_to_search_fixed, $keys_to_search_regex, $w, $z, $debug, $squat_strings;
	$searchedfor = "";

	// Try to get our copy of the page match the encoding of the OSM tags
	$match = null;
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
	$temp = join($squat_strings,'|');
	if(preg_match("/$temp/", $response, $matches)) {
		return(array('type'=>2, 'Possible domain squatting: $1. Suspicious text is: "$2"', $http_eurl, $matches[0]));
	}

	//
	// Fuzzy search full text of page
	// Our goal is to find something -- anything -- that matches between the OSM
	// tags and the actual page content.
	//
	$searchedfor='';
	foreach($keys_to_search_fixed as $key) {
		if(isset($osm_element[$key])) {
			$result = match($response, $osm_element[$key]);
//echo " searching $key=" . $osm_element[$key] . " results $searchedfor\n";
			if ($result===null) return null; else $searchedfor .= $result;
		}
	}
	// do the same with regex-searchstrings
	$keylist = array_keys($osm_element);
	foreach($keys_to_search_regex as $key) {

		foreach($keylist as $current_key) {		// match regex with every key of $element

			if (preg_match('@' . $key . '@i', $current_key)) {
				$result = match($response, $osm_element[$current_key]);
//echo " searching $current_key=" . $osm_element[$current_key] . " results $searchedfor\n";
				if ($result===null) return null; else $searchedfor .= $result;			}
		}
	}


	// Fall through with failure to match
	return array('type'=>3, 'Content of the URL ($1) did not contain these keywords: ($2)', $http_eurl, $searchedfor);
}


// $haystack is the html text of the webpage, $needle is a keyword that is to find
// return null on match, return a list of variations tried otherwise
function match($haystack, $needle) {
	global $z;

	// Check the value as given
	$value_straight   = $needle;
	$searchedfor = "✔".$value_straight;
	$temp = preg_quote($value_straight,'|');
	$temp = preg_replace('|\s|',"$z",$temp);
	if(preg_match("|$temp|i", $haystack)) {
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
		if(preg_match("|$temp|i", $haystack)) {
			return(null);   // Match!
		}
	}

	// Remove ' and check
	$value_apostrophe = str_replace("'",'',$value_straight);
	if( $value_straight !== $value_apostrophe ) {
		$searchedfor .= "✔".$value_apostrophe;
		$temp = preg_quote($value_apostrophe,'|');
		$temp = preg_replace('|\s|',"$z",$temp);
		if(preg_match("|$temp|i", $haystack)) {
			return(null);   // Match!
		}
	}

	return $searchedfor;
}

?>