<?php

require_once '../../../LoadSchema.php';
require_once '../../../DumpSchema.php';

require_once 'config.php';

use info\synapp\tools\backup\LoadSchema;
use info\synapp\tools\backup\DumpSchema;

// Loads test_schema_dump.sql file into test_schema DB on localhost:3306 MySQL 
// server
$loadSchema = new LoadSchema(
    $defaultSchemaFilename = '../test_input.sql',
    $defaultSchemaName = 'test_schema',
    $defaultDbh = $dbParams,
    $defaultUseMysqlClient = true,
        // use mysql CLI client instead of PDO queries by default
    $defaultOverwrite = true // DROP DATABASE IF EXISTS
);
$loadSchema->load(); // No args provided -> use default params set in construct

// Dumps test_schema from localhost:3306 MySQL server DB into 
// test_schema_dump_copy.sql file
$dumpSchema = new DumpSchema(
    $defaultSchemaName = 'test_schema',
    $defaultSchemaFilename = '../test_output.sql',
    $defaultDbh = $dbParams,
    $defaultOverwrite = true // overwrites schema filename if exists
);
$dumpSchema->dump(); // No args provided -> use default params set in construct
$input = file_get_contents('../test_input.sql');
$output = file_get_contents('../test_output.sql');
unlink('../test_output.sql');
$input = info\synapp\tools\backup\LoadSchema::removeComments($input);
$output = info\synapp\tools\backup\LoadSchema::removeComments($output);
if ($input !== $output){
    die ('Test has failed.'.PHP_EOL);
} else {
    die ('Test OK.'.PHP_EOL);
}