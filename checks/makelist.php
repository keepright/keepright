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
$operation="process";

if($argv[1] == "cut")
  $operation= 'cut';
elseif($argv[1] == "process")
  $operation='process';
elseif($argv[1] == "processown")
  $operation='processown';
elseif($argv[1] == "update")
  $operation='update';
elseif($argv[1] == "check")
  $operation='check';
else {
  echo "Runs an operation on all available and active schemata.\n";
  echo "Valid operations:\n";
  echo "process - processes the full schema\n";
  echo "update - updates planet files and loads data into the database\n";
  echo "check - runs the checks and exports the errors\n";
  echo "cut - cuts the planet file in small files, one for each schema. Add planet file name as second argument\n";
  exit();
  }


$schema_arr = array();
$pid_arr = array();


//Put all schemata in an array
foreach ($schemas as $k=>$v) {
  array_push($schema_arr,$k);
  }


 
for($i=0; $i < count($schema_arr); $i++)  {
  //if too many processes are running - wait for one to finish
  if(count($pid_arr) >= $config['max_parallel_processes']) {
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
		$GLOBALS['schema']=$schema_arr[$i];
    if($operation == 'process')
      processschema($schema_arr[$i]);
    elseif($operation == 'processown')
      processown($schema_arr[$i]);
    elseif($operation == 'cut')
      cut_planet($argv[2],$schema_arr[$i]);
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
  
