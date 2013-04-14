<?php

// ###########################################
// KeepRight config file
// ###########################################
//
// This is the main configuration for KeepRight
//
// never change any settings in this file directly!
// Always modify your private config file in ~/.keepright
// as this file is subject to svn updates, which would result
// in change conflicts
//
// ~/.keepright is read _after_ config.php so you may overwrite
// any settings in your private config file.
//
// When running on Windows please always specify path names
// using a single slash ("/") as directory separator.
//
// On Windows your private config file (" ~/.keepright") is
// located in <path to keepright>\config\userconfig.php
//


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


// directory where to put result files
$config['results_dir']=$config['base_dir'] . 'results/';

// directory where to find planet files
$config['planet_dir']=$config['base_dir'] . 'planet/';


// file location of the complete planet file
// optional. just needed in case you want to split
// the file yourself
$config['planet_file']=$config['planet_dir'] . 'planet.pbf';


// the path of the osmosis calling script -- not the JAR-file!
$config['osmosis_bin']='/home/haraldk/OSM/osmosis-0.39/bin/osmosis';


// tools part of (gnuwin32)-coreutils
// file locations for linux
$config['cmd_sort']='/usr/bin/sort';
$config['cmd_join']='/usr/bin/join';

// file locations for windows
//$config['cmd_sort']='"C:/Program Files (x86)/coreutils-5.3.0-bin/bin/sort.exe"';
//$config['cmd_join']='"C:/Program Files (x86)/coreutils-5.3.0-bin/bin/join.exe"';


// to turn off downloading the planet file and use
// the old one set this parameter to false
$config['update_source_data']=true;


// set this option to false if you want the db schema
// to be removed after each run to save hard disk space
// downside is: you cannot have a look inside for debugging.
$config['keep_database_after_processing']=true;


// keepright will stop its infinite loop as soon as
// this file exists. Execution will stop after
// the current schema has finisched working
$config['stop_indicator']='/tmp/stop_keepright';


// logfile for the main loop script
$config['main_logfile']=$config['base_dir'] . 'checks/main.log';


// logging is done one file per database schema per update run.
// specify the number of logfile versions that
// should be retained per schema
$config['logfile_count']=9;


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



// ###########################################
// other settings
// ###########################################


// various file locations for postGIS initialization file
$config['postgis.sql']['ubuntu/postgis 9.1'] = '/usr/share/postgresql/9.1/contrib/postgis-1.5/postgis.sql';
$config['postgis.sql']['ubuntu/postgis 9.0'] = '/usr/local/share/postgis/postgis.sql';
$config['postgis.sql']['debian6/postgis 8.4'] = '/usr/share/postgresql/8.4/contrib/postgis-1.5/postgis.sql';
$config['postgis.sql']['ubuntu/postgis 8.4'] = '/usr/share/postgresql-8.4-postgis/postgis.sql';

// various file locations for hstore initialization file
$config['hstore.sql']['ubuntu/postgis 9.0'] = '/usr/local/Cellar/postgresql/9.0.1/share/contrib/hstore.sql';
$config['hstore.sql']['debian6/postgis 8.4'] = '/usr/share/postgresql/8.4/contrib/hstore.sql';
$config['hstore.sql']['ubuntu/postgis 8.4'] = '/usr/share/postgresql/8.4/contrib/hstore.sql';



// ###########################################
// watchdog settings
// ###########################################


// planet files must not be smaller than this limit
// if they are, they are damaged.
// currently there is no planet file smaller than 90MB
$config['watchdog']['planet_minimum_filesize']=90000000;


// every db schema shall be updated within max 18 days
// config variable is in seconds, as are unix timestamps
$config['watchdog']['schema_max_age']=18 * 86400;


// every host (==user) should commit at least one schema
// every 14 hours, else it stopped working
$config['watchdog']['user_max_age']=14 * 3600;


// result files must not be smaller than this limit
// if they are, they are damaged.
// currently there is no result file smaller than 700kB
$config['watchdog']['error_view_minimum_filesize']=500000;






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


// select your time zone
date_default_timezone_set('Europe/Vienna');


if (platform()=='Linux') {
	$userconfig=getenv('HOME') . '/.keepright';
} else {
	$userconfig='../config/userconfig.php';
}

if (!is_readable($userconfig)) {
	logger("$userconfig not found. This is the first time you have run keepright. I'll create $userconfig for you; adapt the settings as needed and run this script again.", KR_ERROR);

	copy('../config/keepright.config.template', $userconfig);
	exit(1);
}

// read private settings overwriting some of the general settings
require($userconfig);
?>