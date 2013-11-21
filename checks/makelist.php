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

//Check command line arguments and/or print help

$opt = getopt("s::c:t::f::");

if (!isset($opt['c'])) $opt['c']="help";

$schemalist = 0;
if (isset($opt['s']))
  $schemalist = preg_split('/,/',$opt['s']);


if($opt['c'] == "cut")
  $operation= 'cut';
elseif($opt['c'] == "process")
  $operation='process';
elseif($opt['c'] == "processown")
  $operation='processown';
elseif($opt['c'] == "update")
  $operation='update';
elseif($opt['c'] == "check")
  $operation='check';
else {
  echo "Usage: makelist.php -c command [-s schema] [-t threads] [-f planetfile]\n\n";
  echo "Runs an operation on all active or selected schemata with a given number of threads.\n";
  echo "Separate several schemata with a ','.\n";
  echo "Valid commands:\n";
  echo "process - processes the full schema\n";
  echo "update - updates planet files and loads data into the database\n";
  echo "check - runs the checks and exports the errors\n";
  echo "cut - cuts the planet file in small files, one for each schema. Add planet file name as second argument\n";
  exit();
  }


if(isset($opt['t'])) 
  $thread = $opt['t'];
else if(isset($config['max_parallel_processes']))
  $threads = $config['max_parallel_processes'];
else
  $threads = 1;



$schema_arr = array();
$pid_arr = array();


//Put all schemata in an array
foreach ($schemas as $k=>$v) {
  array_push($schema_arr,$k);
  }


 
for($i=0; $i < count($schema_arr); $i++)  {
  //if too many processes are running - wait for one to finish
  if(count($pid_arr) >= $threads) {
    $s=-1;
    pcntl_waitpid(0,$s);
    array_pop($pid_arr);
    }
  else if ($i != 0) {
  //sleep(900);
  }
  
  //fork and execute next schema
  $pid = pcntl_fork();  
  if(!$pid) { 
    if($schemalist!=0 && !in_array($schema_arr[$i],$schemalist))
      exit($i);
    $GLOBALS['schema']=$schema_arr[$i];
    if($operation == 'process')
      processschema($schema_arr[$i]);
    elseif($operation == 'processown')
      processown($schema_arr[$i]);
    elseif($operation == 'cut')
      cut_planet($opt['f'],$schema_arr[$i]);
    elseif($operation == 'update')
      runupdate($schema_arr[$i]);
    elseif($operation == 'check')
      runchecks($schema_arr[$i]);
    logger("Finished schema ".$schema_arr[$i]);
    exit($i);
    }
  else {
    array_push($pid_arr,$pid);
    }
  }
 
//Wait for all sub-processes before finishing
while( pcntl_waitpid(0,$s) != -1) {
  sleep(1);
  }
 

 
 
//The main function to cut planet files 
function cut_planet($planetfile,$schema) {
  logger("Creating planet cut file for schema $schema from $planetfile\n");
  planet_cut($planetfile, $schema);
  }
 
 
//The main function to fully process a schema
function processschema($schema) {
  runupdate($schema);
  runchecks($schema);
  }

function processown($schema) {
  system("php process_schema.php $schema");
  }
  

//The function to run checks only
function runchecks($schema) {
  logger("Run checks schema".$schema);
  run_checks($schema);

  logger("Export Errors schema".$schema);
  export_errors($schema);
  
  logger("Uploading schema".$schema);
  remote_command('--local', '--upload_errors', $schema);
  } 
 

//The function to run update only
function runupdate($schema) {
  logger("Starting schema ".$schema);
  prepareDB($schema);

  logger("Update DB schema".$schema);
  updateDB($schema);
  }
  
