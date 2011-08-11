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
//  Ideas
//	    Process colons (e.g. "website:*");
//	    Process semicolons (e.g. "amenity=cafe;bar");
//
//  URGENT IDEAS TODO
//      Follow meta-refresh redirects properly. In the handler re-queue the page if you see:
//          <meta http-equiv="refresh"...>
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
$checkable_tags = array(
	'website',
	'url',
	'website:mobile',
	'contact:website'
);

// these tags may contain text which can validate the website matches the osm element:
// two flavors: fixed strings or regexes can be used
$keys_to_search_fixed = array(
	'name',
	'alt_name',
	'website:searchstring',
	'phone',
	'operator',
	#'addr:street',  # matches common prefixes too easily
	'frequency',
);
$keys_to_search_regex = array(
	'name:[a-z]{2}',
);

// never try to match these URLs
// these are regexes and they are applied in case insensitive manner automatically!
// Supports regex
$whitelist = array(
	'^http://www.internationalboundarycommission.org/coordinates/',
	'^http://ancien-geodesie.ign.fr/',
	'.pdf$',							// PDF matching not useful... yet
	'^http://disneyland.disney.go.com/',
    );

// used for identifying probable domain squatting or hijack.
// Straight strings, no regex.
$squat_strings = array(
	"http://www.acquirethisname.com",
	"http://dsnextgen.com/?a_id=",
	"http://www.domainbrokeronline.com/rd.php",
	"http://www.dsnextgen.com/",
	"http://domainbrokers.com/index.php?page=offer",
	"/static/template/qing/images/qing.ico",    # http://domainbrokers.com/
	"http://images.sitesense-oo.com/images/template/",
	"__changoPartnerId='parkedcom'",
	"The DreamHost customer who owns this domain has parked their website.",    # Dreamhost
	"Buy This Domain",                          # Generic
	"/_static/img/ND_new_logo_small.jpg",       # namedrive.com
    );



$curlopt = array(
	CURLOPT_URL             => '',		// need to specify at least an empty URL here, otherwise nothing will be fetched
	CURLOPT_USERAGENT	=> 'KeepRightBot/0.2 (KeepRight OpenStreetMap Checker; http://keepright.ipax.at)',
	CURLOPT_HTTPHEADER	=> array('Accept-Language: en'),
	CURLOPT_HEADER          => false,	// don't include the http header in the result
	CURLOPT_FOLLOWLOCATION	=> true,
	CURLOPT_AUTOREFERER	=> true,
	CURLOPT_RETURNTRANSFER	=> true,	// don't echo http response, instead return it as string
	CURLOPT_MAXREDIRS	=> 50,		// follow up to x http redirects

 	CURLOPT_SSL_VERIFYPEER	=> false,	// don't care about missing or outdated ssl certificates
 	CURLOPT_SSL_VERIFYHOST	=> 1,

	CURLOPT_TIMEOUT		=> 45           // Returns 0 if it takes too long
);


if ($HTTP_PROXY_ENABLED) {
	$curlopt[CURLOPT_PROXY]			= $HTTP_PROXY;
	$curlopt[CURLOPT_PROXYAUTH]		= 'CURLAUTH_BASIC';
	$curlopt[CURLOPT_PROXYUSERPWD]		= $HTTP_PROXY_USER . ':' . $HTTP_PROXY_PWD;
	$curlopt[CURLOPT_HTTPPROXYTUNNEL]	= true;
}




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
	$urlstats=array();

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
		$list = split_tag($row1['url']);	// inside the tag value there could be multiple values separated by ";"
		foreach ($list as $url) {

			$urls_queued++;
			echo "queueing URL $url\n";
			$urlstats[$url]++;
			queueURL($rc, $obj, $url);
		}

	}
	pg_free_result($result1);

	#   Execute web requets queued above
	if ($urls_queued) $rc->execute();

	echo "matched $urls_queued URLs with $error_count errors.\n";
	echo "top 10 urls matched:\n";
	arsort($urlstats);
	$i=1;
	foreach ($urlstats as $url=>$count) {
		echo "$count\t$url\n";
		if ($i++>9) break;
	}
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
	process_meta_refresh($response, $request->callback_data, $request->url);
	$rv=fuzzy_compare($response, $request->callback_data, $request->url);
	print_r($rv);
}


// handle http response in keepright mode
function run_keepright_callback($response, $info, $request) {
	global $db2, $error_type, $error_count;

	$obj = $request->callback_data;
	echo "callback for " . $request->url . "\n";

	if($info['http_code'] == 0) {
		echo "The URL (" . $request->url . ") cannot be opened (HTTP status code " . $info['http_code'] . ")\n";
	return;
        }
	else if($info['http_code'] < 200 || $info['http_code'] > 299) {
		echo "The URL (" . $request->url . ") cannot be opened (HTTP status code " . $info['http_code'] . ")\n";

		$error_count++;
		$txt1=pg_escape_string($db2, $request->url);
		$txt2=pg_escape_string($db2, $info['http_code']);
		query("
			INSERT INTO _tmp_errors(error_type, object_type, object_id, msgid, txt1, txt2, last_checked)
			VALUES ($error_type + 1, '" . $obj['object_type'] . "', " . $obj['id'] . ", 'The URL ($1) cannot be opened (HTTP status code $2)', '$txt1', '$txt2', NOW())
		", $db2, false);

	return;
	}


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

function process_meta_refresh($response, $osm_element, $http_eurl) {
	global $z;

	if(preg_match("/meta${z}http-equiv$z=$z\"refresh\"/i", $response,$match)) {
		# TODO
		# TODO
		# TODO
		# TODO
		print "Warning: http-equiv refresh found $http_eurl\n";
	}
}

// $haystack is the html text of the webpage, $needle is a keyword that is to find
// return null on match, return a list of variations tried otherwise
function match($haystack, $needle) {
	global $z;

	$searchedfor = "";

	## Exact match? If only...
	$searchedfor .= "✔".$needle;
	if( stripos( $haystack, $needle ) ) {
		return(null);   // Match!
	}

	##
	## Now pass if ANY word in the needle is in the haystack.
	## For English excluding words under 4 characters works,
	## avoiding "and", "or", "bar", "&" and other common words and symbols.
	##
	if(!( $temp = match_any($haystack,$needle) )) {
		return(null);   // Match!
	}
	$searchedfor .= $temp;

	// Strip out diacriticals and search again.
	// Though, php's defective iconv makes this hard.
	// Ideally it would convert like so:
	//	  grüßen<200e>!
	//	  grussen!
	$needle2 = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $needle);
	$needle2 = str_replace("?",'',$needle2);
	if( $needle2 !== $needle ) {
		if(!( $temp = match_any($haystack,$needle2) )) {
			return(null);   // Match!
		}
		$searchedfor .= $temp;
	}

	#   Let's try the match without punctuation
	#           Rooney's == Rooneys
	#           Case-Shiller == CaseShiller
	$haystack2 = $haystack;
	$needle2   = preg_replace("/\p{P}/",'',$needle);
	if( $needle2 !== $needle ) {
	if(!( $temp = match_any($haystack2,$needle2) )) {
			return(null);   // Match!
		}
		$searchedfor .= $temp;
	}

	#   Let's try the match with punctuation converted to spaces:
	#           Case-Shiller == Case Shiller
	$haystack3  = preg_replace("/\p{P}/",' ',$haystack);
	$needle3    = $needle2;
	if( $needle3 !== $needle ) {
	if(!( $temp = match_any($haystack3,$needle2) )) {
			return(null);   // Match!
		}
		$searchedfor .= $temp;
	}

	#   Let's try the match with punctuation converted to spaces:
	#       510-558-8770 == 510.558.8770 == +1 (510) 558-8770
	#   TODO

	return $searchedfor;
}

##
## We pass if ANY word in the needle is in the haystack
##
function match_any($haystack, $needle)
{
	$searchedfor = "";
	$words = preg_split("/\s+/",$needle);
	foreach($words as $word) {
		if (strlen($word) < 4)    {continue;}   # Except short words
		if ($word == "test")      {continue;}   # Except "test"

		$searchedfor .= "✔".$word;
		if( stripos( $haystack, $word ) ) {
			return(null);
		}
	}
	return $searchedfor;
}

?>
