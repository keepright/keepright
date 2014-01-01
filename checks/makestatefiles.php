#!/usr/bin/php
<?php
require_once("../config/schemas.php");
require('helpers.php');
require('../config/config.php');
require('BufferedInserter.php');

require('prepareDB.php');
require('updateDB.php');
require('run-checks.php');
require('planet.php');
require('export_errors.php');
require('webUpdateClient.php');




foreach ($schemas as $k=>$v) {
//   system("mkdir ../planet/$k");
$f=fopen("../planet/$k/configuration.txt", 'w');
        fwrite($f, "# The URL of the directory containing change files.\n");
        fwrite($f, "baseUrl=http://planet.openstreetmap.org/replication/day\n\n");
        fwrite($f, "# Defines the maximum time interval in seconds to download in a single invocation.\n");
        fwrite($f, "# Setting to 0 disables this feature.\n");
        fwrite($f, "maxInterval = 0\n");
        fclose($f);

   system('echo "#Wed Nov 13 00:05:48 UTC 2013
sequenceNumber=427
timestamp=2013-11-13T00\:00\:00Z">../planet/'.$k.'/state.txt');
  }
