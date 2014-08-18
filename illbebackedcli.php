<?php

require_once('DumpSchema.php');
require_once('LoadSchema.php');

use \info\synapp\tools\backup\DumpSchema;
use \info\synapp\tools\backup\LoadSchema;

if (PHP_SAPI!=='cli'){
    die ("This program must be executed from the command line.".PHP_EOL);
}

// Read command line input params
for ($i=1;$i<$argc;$i++){
    switch ($argv[$i]){
        case '-i':
            $i++;
            if ($i===$argc){
                die ("invalid args".PHP_EOL);
            }
            $inputFilename = $argv[$i];
            break;
        case '-o':
            $i++;
            if ($i===$argc){
                die ("Invalid args (-o must be followed by output filename)".PHP_EOL);
            }
            $outputFilename = $argv[$i];
            break;
        case '-c':
            $i++;
            if ($i===$argc){
                die ("Invalid args (-c must be followed by config filename)".PHP_EOL);
            }
            if (!file_exists($argv[$i])){
                die ("Invalid args (-c must be followed by existing config "
                    ."filename)".PHP_EOL);
            }
            $params['configFilename'] = $argv[$i];
            break;
        default:
            $dbName = $argv[$i];
            if ($i!==($argc-1)){
                echo $i."".PHP_EOL;
                echo $argc."".PHP_EOL;
                die (
                    "Invalid arguments.".PHP_EOL."Usage: php illbebackedcli.php "
                    ."[-i input] [-o output] [-c config] [dbName]".PHP_EOL
                );
            }
    }
}

// Read .ini params
if (
    !(
        $params = parse_ini_file(
            isset($params['configFilename'])?
                $params['configFilename']
                :'illbebackedcliconf.ini'
        )
    )
){
    die ("Invalid config file".PHP_EOL);
}

if (!isset($inputFilename)){
    $inputFilename = isset($params['inputFilename'])?
        $params['inputFilename']:null;
}
unset ($params['inputFilename']);

if (!isset($outputFilename)){
    $outputFilename = isset($params['outputFilename'])?
        $params['outputFilename']:null;
}
unset ($params['outputFilename']);

if (!isset($dbName)){
    $dbName = isset($params['dbName'])?
        $params['dbName']:null;
}
unset ($params['dbName']);

$overwriteOutputFile = isset($params['overwriteOutputFile'])?
    $params['overwriteOutputFile']:false;
unset ($params['overwriteOutputFile']);

$dropDatabaseIfExists = isset($params['dropDatabaseIfExists'])?
    $params['dropDatabaseIfExists']:false;
unset ($params['dropDatabaseIfExists']);

$useMysqlCli = isset($params['useMysqlCli'])?
    $params['useMysqlCli']:true;
unset ($params['useMysqlCli']);

$dbh = array();
if (isset($params['hostname'])){
    $dbh['hostname'] = $params['hostname'];
}
if (isset($params['port'])){
    $dbh['port'] = $params['port'];
}
if (isset($params['user'])){
    $dbh['user'] = $params['user'];
}
if (isset($params['password'])){
    $dbh['password'] = $params['password'];
}

if (isset($outputFilename)||!isset($inputFilename)){
    $dumpSchema = new DumpSchema(
        $dbName,
        $outputFilename,
        $dbh,
        $overwriteOutputFile
    );
    $dumpSchema->dump();
}

if (isset($inputFilename)){
    $loadSchema = new LoadSchema(
        $inputFilename,
        $dbName,
        $dbh,
        $useMysqlCli,
        $dropDatabaseIfExists
    );
    $loadSchema->load();
}
