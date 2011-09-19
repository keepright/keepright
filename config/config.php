<?php

$error_types=array();
$schemas=array();
$config=array();


// ###########################################
// Database credentials
// ###########################################
//
// the main database server.
// this is the PostGIS server the checks run
// the main db server may have many databases (see below)

$config['db']['host']='localhost';
$config['db']['port']='5432';
$config['db']['database']='osm';
$config['db']['user']='osm';
$config['db']['password']='shhh!';


// the web servers database server.
// this is the MySQL server behind the web presentation
// please note: you have to duplicate these credentials
// in the file keepright/config/webconfig.inc.php!

$config['web_db']['host']='localhost';
$config['web_db']['database']='osm';
$config['web_db']['user']='osm';
$config['web_db']['password']='shhh!';



// ###########################################
// general settings
// ###########################################

// absolute path pointing to the keepright directory
// please provide a trailing slash
$config['base_dir']='/home/haraldk/OSM/keepright/';


// a separate high speed partition preferably
// please provide a trailing slash
$config['temp_dir']='/tmp/';


// file location of the complete planet file
// optional. just needed in case you want to split
// the file yourself
$config['planet_file']=$config['base_dir'] . 'planet/planet.pbf';


// the path of the osmosis calling script -- not the JAR-file!
$config['osmosis_bin']='/home/haraldk/OSM/osmosis-0.39/bin/osmosis';


// to turn off downloading the planet file and use
// the old one set this parameter to false
$config['update_source_data']=true;


// set this option to false if you want the db schema
// to be removed after each run to save hard disk space
// downside is: you cannot have a look inside for debugging.
$config['keep_database_after_processing']=true;


// keepright will stop its indefinite loop as soon as
// this file exists. Execution will stop after
// the current schema has finisched working
$config['stop_indicator']='/tmp/stop_keepright';


// logfile for the main loop script
$config['main_logfile']=$config['base_dir'] . 'checks/main.log';


# config options for using a http proxy
# used in 410_website.php for checking website URLs
$config['http_proxy']['enabled']=false;
$config['http_proxy']['host']='localhost:3128';
$config['http_proxy']['user']='username';
$config['http_proxy']['password']='shhh!';


// ###########################################
// result uploading settings
// ###########################################

$config['upload']['ftp_host']='keepright.ipax.at';
$config['upload']['ftp_user']='k000382_1';
$config['upload']['ftp_password']='shhh!';

// do not give a slash at the beginning, but leave one
// at the end of this path
$config['upload']['ftp_path']='web/keepright.ipax.at/';

// this URL will be called to load data into the tables
// one for remote and one for localhost access for testing
$config['upload']['url']='http://keepright.ipax.at/webUpdateServer.php';
$config['upload']['url_local']='http://localhost/kr/webUpdateServer.php';



// your keepright-account determines which schemas will be checked
// and gives you access to the web update routines
// You HAVE TO change this password. The script will not work with default settings
$config['account']['user']='you@your.mail.provider';
$config['account']['password']='shhh!';



// various file locations for postGIS initialization file
$config['postgis.sql']['ubuntu/postgis 9.0'] = '/usr/local/share/postgis/postgis.sql';
$config['postgis.sql']['debian6/postgis 8.4'] = '/usr/share/postgresql/8.4/contrib/postgis-1.5/postgis.sql';
$config['postgis.sql']['ubuntu/postgis 8.4'] = '/usr/share/postgresql-8.4-postgis/postgis.sql';

// various file locations for hstore initialization file
$config['hstore.sql']['ubuntu/postgis 9.0'] = '/usr/local/Cellar/postgresql/9.0.1/share/contrib/hstore.sql';
$config['hstore.sql']['debian6/postgis 8.4'] = '/usr/share/postgresql/8.4/contrib/hstore.sql';
$config['hstore.sql']['ubuntu/postgis 8.4'] = '/usr/share/postgresql/8.4/contrib/hstore.sql';




require('../config/schemas.php');
require('../config/error_types.php');

// print_r($error_types);
// print_r($schemas);
// print_r($config);


// log levels
define('KR_ERROR', 1);
define('KR_WARNING', 2);
define('KR_INFO', 4);
define('KR_INDEX_USAGE', 8);
define('KR_COMMANDS', 16);


// determines which kinds of messages should be logged
$config['loglevel'] = KR_ERROR + KR_WARNING + KR_INFO + KR_INDEX_USAGE + KR_COMMANDS;



$userconfig=$_ENV['HOME'] . '/.keepright';

if (!is_readable($userconfig)) {
	logger('~/.keepright not found. This is the first time you have run keepright. I\'ll create ~/.keepright for you; adapt the settings as needed and run this script again.', KR_ERROR);

	copy('../config/keepright.template', $userconfig);
	exit(1);
}

// read private settings overwriting some of the general settings
require($userconfig);

?>