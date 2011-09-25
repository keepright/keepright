<?php
require_once('helpers.php');
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


global $checkable_tags, $keys_to_search_fixed, $keys_to_search_regex, $whitelist, $curlopt, $w, $z, $debug, $squat_strings, $rc, $db1, $db2;

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
// insert URLs pointing to public transport companies or retailers/food companies
// running multiple stores/restaurants and the URL doesn't point to an individual store
$whitelist = array(
	'.pdf$',							// PDF matching not useful... yet
	'^http://a2wtrail.org/',
	'^http://ancien-geodesie.ign.fr/',
	'^http://caravanclub.se/',
	'^http://cwr.naturalengland.org.uk/Default.aspx?Module=CountryWalkDetails&Site=3065',
	'^http://dcatlas.dcgis.dc.gov/metadata/RecPly.html',
	'^http://disneyland.disney.go.com/',
	'^http://en.wikipedia.org/wiki/A._Soriano_Highway',
	'^http://en.wikipedia.org/wiki/Research_and_Development_Array',
	'^http://fi.wikipedia.org/wiki/Saimaa',
	'^http://fi.wikipedia.org/wiki/Seututie_135',
	'^http://gorod.megafonvolga.ru/?city=2',	'^http://ja.wikipedia.org/wiki/%E5%A4%9A%E6%91%A9%E5%B7%9D%E3%82%B5%E3%82%A4%E3%82%AF%E3%83%AA%E3%83%B3%E3%82%B0%E3%83%AD%E3%83%BC%E3%83%89',
	'^http://kaerntner-linien.at',
	'^http://karlsruher-sonnendaecher.de/kasd/public/sopaI/muelldeponiewest',
	'^http://landkreislauf.de/',
	'^http://nctr.pmel.noaa.gov/Dart/',
	'^http://norfolk-safety-camera.org.uk/index.php?page=fixedinfo',
	'^http://openstreetmap.falco2.de/Bostalsee/Wasserzapfstelle.jpg',
	'^http://paslo.ru',
	'^http://run-walk-innsbruck.at',
	'^http://sbrf.ru/',
	'^http://sites.google.com/site/norwichpolicecctv/',
	'^http://tps.cr.nps.gov/nhl/detail.cfm?ResourceId=618&ResourceType=Structure',
	'^http://vernongreenways.org',
	'^http://wag.at',
	'^http://walking-papers.org/scan.php?id=pbrrgrls',
	'^http://wiki.openstreetmap.org/wiki/Germany:Public_Transport/saarVV',
	'^http://wiki.openstreetmap.org/wiki/WikiProject_Luxembourg/Public_Transport',
	'^http://www.7-eleven.com',
	'^http://www.aarhusbycykel.dk/',
	'^http://www.abuaf.com/directo/index.htm',
	'^www.acoventryway.org.uk',
	'^http://www.acts.it/',
	'^http://www.afa-busbetrieb.ch',
	'^http://www.afzamorana.es/',
	'^http://www.aktivzentrum-bodenmais.de',
	'^http://www.aldi-nord.de',
	'^http://www.aldi-sued.de',
	'^http://www.alpendorf.com',
	'^http://www.altaviacmargentea.net/ospitalita/ripari.shtml',
	'^http://www.amt.genova.it/orari/orari_urbana.asp',
	'^http://www.amt.genova.it/orari/orari.asp',
	'^http://www.aral.de/',
	'^http://www.arco.com',
	'^http://www.atp-spa.it',
	'^http://www.atp-spa.it/cartina.php',
	'^http://www.autopistamadridtoledo.com/',
	'^http://www.baeckerei-dreissig.de',
	'^http://www.baeckerlampe.de/',
	'^www.bahn.de',
	'^(http://)?www.billa.at',
	'^http://www.blueridgeparkway.org/',
	'^www.bridgehead.ca',
	'^http://www.bristolbathrailwaypath.org.uk/',
	'^http://www.brnonakole.cz/',
	'^http://www.bvg.de',
	'^http://www.caffenero.com',
	'^http://www.cambio-carsharing.de/aachen',
	'^http://www.caminaspe.fr/encore-mieux/topo-45-randos-vall%C3%A9e',
	'^http://www.captainslash.com/buriram-chong-chom-and-along',
	'^http://www.captainslash.com/chumphon-to-phetchaburi/',
	'^http://www.captainslash.com/cnx-east-of-mae-khachan',
	'^http://www.captainslash.com/nan-pong-through-the-mountains',
	'^http://www.captainslash.com/nan-the-1333-and-some',
	'^http://www.captainslash.com/si-saket-4-reservoirs-and',
	'^http://www.captainslash.com/si-saket-meandering-through-some',
	'^http://www.captainslash.com/si-saket-tha-tum-on',
	'^http://www.captainslash.com/si-saket-the-emerald-triangle',
	'^http://www.captainslash.com/si-saket-the-lots-of',
	'^http://www.carsharing.at/',
	'^http://www.cg94.fr/transport-voirie/17239-les-routes-du',
	'^http://www.ch-montmorillon.fr',
	'^www.chevron.com',
	'^http://www.chguadiana.es/',
	'^http://www.citybikewien.at',
	'^http://www.confishare.de',
	'^http://www.connecta-parc.de',
	'^http://www.coop.dk/',
	'^http://www.cumbria-railways.co.uk/brampton_railway.html',
	'^http://www.cumbriacc.gov.uk/roads-transport/highways-pavements/roads/road-works/major-projects/cndr.asp',
	'^http://www.cuxland.de/aktuelles/Radwandern.html',
	'^http://www.dec.ny.gov/lands/5970.html',
	'^http://www.dec.ny.gov/lands/8066.html',
	'^http://www.dec.ny.gov/outdoor/7815.html',
	'^http://www.dec.ny.gov/outdoor/8297.html',
	'^http://www.dntoslo.no/',
	'^www.draisinenbahn.de',
	'^http://www.dublinbikes.ie/',
	'^www.dzongkhag.gov.bt',
	'^http://www.edeka.de/',
	'^http://www.ekorosk.fi',
	'^http://www.eldorado.ru/',
	'^http://www.elster-nahverkehr.de',
	'^http://www.energiewende-oberland-gmbh.de/',
	'^http://www.ep-moke.fi/',
	'^http://www.erlebnisbahn.de',
	'^http://www.evrasia.spb.ru/',
	'^http://www.festung-ulm.de/',
	'^www.gemeentewestland.nl',
	'^http://www.geobase.ca/geobase/en/data/admin/cgb/description.html',
	'^http://www.glasgow.gov.uk/en/Residents/Parks_Outdoors/Parks_gardens/queenspark.htm',
	'^http://www.gobiernodecanarias.org/boc/2008/246/001.html',
	'^http://www.grafschaft-bentheim-tourismus.de/radfahren/grafschafter-fietsentour.html',
	'^http://www.grand-rodez.com/fr/guides/annuaires_pratiques/guide_transports.php',
	'^http://www.grandforksgov.com/greenway/index.htm',
	'^http://www.grossglockner.at',
	'^www.haarlemmermeer.nl',
	'^www.hema.nl',
	'^http://www.herr-berge.de',
	'^http://www.hit.de/',
	'^www.hmb-ev.de',
	'^http://www.hofer.at',
	'^www.hohensalzburg.com',
	'^http://www.ilukste.lv/index.php?option=com_content&view=article&id=231&Itemid=609',
	'^http://www.internationalboundarycommission.org/coordinates/',
	'^http://www.invg.de',
	'^http://www.isf-trento.org/?page_id=38',
	'^http://www.ivb.at',
	'^http://www.jiffylube.com',
	'^http://www.kajaaninmoottorikelkkayhdistys.net/',
	'^http://www.kalwaria.pszowska.katowice.opoka.org.pl/',
	'^www.kauhajoenmoottorikelkkailijat.org',
	'^www.kelkkailijat.net',
	'^http://www.klewenalp.ch/',
	'^http://www.kolumbus.no',
	'^http://www.koskilinjat.fi',
	'^http://www.krasnoe-beloe.ru',
	'^http://www.kreissparkasse-diepholz.de',
	'^www.laengholz.ch',
	'^http://www.lamsa.com.br',
	'^http://www.landkreis-demmin.de',
	'^www.lauha.fi',
	'^http://www.leb.ch/',
	'^http://www.lepilote.com/',
	'^http://www.lessentiersdelestrie.qc.ca/',
	'^http://www.levelo-mpm.fr/',
	'^http://www.lovdata.no/all/nl-20090605-035.html',
	'^http://www.maridalensvenner.no/index.php?id=178047',
	'^http://www.maridalensvenner.no/index.php?id=178088',
	'^http://www.maridalensvenner.no/index.php?id=178092',
	'^http://www.mbb-mgn.de',
	'^http://www.mc30.es/',
	'^http://www.mcdonalds.ru/',
	'^http://www.mcrailways.co.uk/',
	'^http://www.mendel-grundmann-gesellschaft.de',
	'^www.metan.by',
	'^http://www.metropolradruhr.de',
	'^www.metroradruhr.de',
	'^http://www.metrostlouis.org/',	'^http://www.metsa.fi/sivustot/metsa/fi/Eraasiatjaretkeily/Moottorikelkkailu/Sivut/Maastoliikenne.aspx',
	'^http://www.mhs.marcellusny.com/MHS_Home/Marcellus_and_Otisco_Lake_Railw.html',
	'^http://www.midttrafik.dk/k%c3%b8replaner/bybus',
	'^http://www.mkedcd.org/DowntownMilwaukee/RiverWalk/index.html',
	'^http://www.mobi-e.pt',
	'^http://www.mobility.ch/',
	'^http://www.mockel-bahn.de',
	'^http://www.mr-sub.de/',
	'^http://www.mrao.cam.ac.uk/telescopes/',
	'^http://www.mvg-mobil.de',
	'^http://www.mvv-muenchen.de',
	'^http://www.nasa.de/',
	'^http://www.naturpark-lueneburger-heide.de',
	'^www.naturparkmeissner.de',
	'^http://www.naturparkschwarzwald.de/sport-erlebnis/mountainbiking/searchtouren/index_html',
	'^http://www.net-plaza.org/KANKO/shinshiro/karuta.html',
	'^http://www.nextbike.de/potsdam.html',
	'^http://www.nextbike.pl',
	'^http://www.nisseringen.dk/',
	'^http://www.northyorkshiremoorsrailway.com/',
	'^http://www.nos-borkum.de/',
	'^http://www.nps.gov/klgo/planyourvisit/chilkoottrail.htm',
	'^http://www.orgp.ru/raspall.html?1',
	'^http://www.ostfalia.de',
	'^http://www.otto.fi/',
	'^http://www.packstation.de',
	'^www.pharmacia.by',
	'^www.pizzaiolo.ca',
	'^www.pohjoiskarjalankelkkaurat.fi',
	'^http://www.postauto.ch',
	'^http://www.pret.com',
	'^www.prokon.net',
	'^www.radverkehrsnetz.nrw.de',
	'^http://www.rideau-info.com/canal/index.html',
	'^http://www.rivieratrasporti.it/ShowOrari.asp',
	'^http://www.rossmann.de',
	'^http://www.rovg.de/php/linienbetreiber.php',
	'^http://www.rvv.de',
	'^http://www.safeway.com',
	'^http://www.sbb.ch/fr/',
	'^http://www.sbrf.ru/',
	'^http://www.schaapskopp.de/eibia/Werksgelaende.shtml',
	'^http://www.schotten.de/freizeit/TouristInfo/Schneebericht.htm',
	'^http://www.sculp.de',
	'^www.secondcup.com',
	'^http://www.sgv-duesseldorf.de/',
	'^http://www.sharis.com',
	'^http://www.shell.de/',
	'^http://www.shell.us',
	'^http://www.skaneleden.se/',
	'^http://www.skyscrapercity.com/showthread.php?t=321518',
	'^http://www.skyscrapercity.com/showthread.php?t=496160',
	'^http://www.smul.sachsen.de/sbs/',
	'^www.spar.at',
	'^http://www.sparkasse-aachen.de/',
	'^http://www.sparkasse-hattingen.de',
	'^http://www.sparkasse-muensterland-ost.de/',
	'^http://www.sparkasse-paderborn.de',
	'^http://www.spbkoreana.ru/',
	'^http://www.spitaljudeteanmures.ro/',
	'^www.sprboracay.com',
	'^www.stadtreinigung-hh.de',
	'^www.starbucks.ca/en-ca',
	'^http://www.starbucks.co.uk',
	'^http://www.stolpersteine-konstanz.de/',
	'^http://www.stolpersteine-lueneburg.de/',
	'^http://www.streetcar.co.uk/',
	'^http://www.sts.qc.ca/',
	'^http://www.studentenwerkbielefeld.de',
	'^http://www.swneumarkt.de/stadtbusse.html',
	'^http://www.t-l.ch/',
	'^http://www.teilauto.net',
	'^http://www.three-brooks.info',
	'^http://www.tobike.it/',
	'^www.tourism-novobrdo.com',
	'^http://www.tpwr.de/',
	'^www.transportstyrelsen.se',
	'^http://www.travelmart.net/philippines/nautical.html',
	'^http://www.trekking.suedtirol.info/',
	'^http://www.umwelt.bremen.de/de/detail.php?gsid=bremen179.c.9329.de',
	'^http://www.usbr.gov/lc/hooverdam/faqs/powerfaq.html',
	'^http://www.vag.de',
	'^www.vasaloppet.se',
	'^http://www.vegvesen.no/Vegprosjekter/tforbindelsen',
	'^www.veloh.lu',
	'^http://www.velopistejcp.com',
	'^http://www.vg-lambrecht.de/vg_lambrecht/Erleben/Freizeitangebote/Kunst%20und%20Kultur/',
	'^http://www.vgo.de/vgo/vgo.nsf/c/Aktuelles,News?open&P1=7FDC38A2D0FB26F3C12572DC004CF128',
	'^http://www.vgs-online.de/',
	'^www.vmobil.at',
	'^http://www.vrbank-coburg.de/',
	'^http://www.vtfishandwildlife.com/access-areas-map.cfm',
	'^http://www.vv.se/norralanken',
	'^http://www.vvt.at',
	'^http://www.waldviertlerbahn.at/',
	'^http://www.wallaseycemetery.co.uk/Cemetery%20Plan.htm',
	'^http://www.wandelzoekpagina.nl/groene_wissels/lijst.php',
	'^http://www.watchtower.org/',
	'^http://www.westgov.com/mainstreet/mainstreet_parking.html',
	'^http://www.winterpark-willingen.info/loipeninfos/',
	'^http://www.yunnanexplorer.com/features/guandu/',
	'^http://www.yunnanexplorer.com/transport/',
	'^http://www.zfa-iserlohn.de/balve/containerstandorte.asp',
	'^https://www.sparkasse-rottal-inn.de/',
	'^http://zditm.szczecin.pl/rozklady/index.html'
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



if (isset($config['http_proxy']['enabled']) && $config['http_proxy']['enabled']) {
	$curlopt[CURLOPT_PROXY]			= $config['http_proxy']['host'];
	$curlopt[CURLOPT_PROXYAUTH]		= 'CURLAUTH_BASIC';
	$curlopt[CURLOPT_PROXYUSERPWD]		= $config['http_proxy']['user'] . ':' . $config['http_proxy']['password'];
	$curlopt[CURLOPT_HTTPPROXYTUNNEL]	= true;
}




// ****************************************************************************************************** //
$debug  = 0;
$w = '[\s\S]*?'; //ungreedy wildcard - matches anything
$z = '[\h\v]*?'; //ungreedy wildcard - matches whitespace only
$rc;            // Rolling Curl object (fake multithreading for php)


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
		run_keepright($db1, $db2, $object_type, $table, $curlopt);
	}
}



function run_keepright($db1, $db2, $object_type, $table, $curlopt) {
	global $error_type, $checkable_tags, $whitelist, $error_count, $rc;


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
	global $argc, $argv, $checkable_tags, $curlopt, $rc;

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
	print "Queue $url on ID $element[id]\n";
	$request = new RollingCurlRequest($url);
	$request->callback_data = $element;
	$rc->add($request);
}



// handle http response in standalone mode
function run_standalone_callback($response, $info, $request) {
	echo "Callback on $request->url\n";
	if($info['http_code'] < 200 || $info['http_code'] > 299) {
		print_r(array('type'=>1, 'The URL ($1) cannot be opened (HTTP status code $2)', $request->url, $info['http_code']));
		return;
	}
	if(check_meta_refresh($response, $request->callback_data, $request->url)) {
		return;
	}
	print_r(fuzzy_compare($response, $request->callback_data, $request->url));
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

	if(check_meta_refresh($response, $request->callback_data, $request->url)) {
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

// Requeue pages which simply refrence another page.
// Watch out for loops!
function check_meta_refresh($response, $osm_element, $http_eurl) {
	global $z, $rc;

	if(preg_match("/meta${z}http-equiv$z=$z\"refresh\".*content$z=$z\".*?url=${z}(.*?)\"/i", $response,$match)) {

		$url = trim($match[1]);

		if ($url!=='' && $url!=='/') {		// some pages refresh on "/" or on blank urls; this shall not build a loop

			// Normalize URL
			// guess if given URL is absolute or relative
			// TODO: detect hostnames without scheme prefix like host.domain,net as absolute URL
			if(strstr($match[1], '://')===false && strstr($match[1], 'www.')===false) {
				// seems to be a relative URL so preprend the __host__part__
				// of the old URL
				$urlparts=parse_url($http_eurl);

				$url=$urlparts['scheme'] . '://' . $urlparts['host'] . (substr($url, 0, 1)=='/' ? $url : "/$url");
				//$url = "$http_eurl/".$url;
			}


			print "Old style http-equiv refresh found $http_eurl $match[1] $url\n";

			// count redirects for this element
			if (isset($osm_element['keepright_loopcount']))
				$osm_element['keepright_loopcount']++;
			else
				$osm_element['keepright_loopcount']=1;

			if ($osm_element['keepright_loopcount']>5) {
				echo "redirect loop detected for URL $url. Won't follow this redirect\n";
				return false;	// in case of a redirection-loop most probably a content-mismatch error will be raised because there won't be a match on a redir-page
			}

			queueURL($rc, $osm_element, $url);

			return(true);
		}
	}
	return(false);
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
		if ($word == "cafe")      {continue;}   # Except "cafe"
		if ($word == "café")      {continue;}   # Except "café"

		$searchedfor .= "✔".$word;
		if( stripos( $haystack, $word ) ) {
			return(null);
		}
	}
	return $searchedfor;
}

?>
