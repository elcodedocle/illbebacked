<?php
set_time_limit (36000); // 10 hours
ini_set('memory_limit','512M');
exec (
    "php ../../../illbebackedcli.php"
    ." -i ../test_input.sql"
    //." -o ../test_output.sql"
    ." -c illbebackedcliconf.ini"
    ." illbebackcli_test"
);
exec (
    "php ../../../illbebackedcli.php"
    //." -i ../test_input.sql"
    ." -o ../test_output.sql"
    ." -c illbebackedcliconf.ini"
    ." illbebackcli_test"
);
$input = file_get_contents('../test_input.sql');
$output = file_get_contents('../test_output.sql');
unlink('../test_output.sql');
require_once '../../../LoadSchema.php';
$input = info\synapp\tools\backup\LoadSchema::removeComments($input);
$output = info\synapp\tools\backup\LoadSchema::removeComments($output);
if ($input !== $output){
    die ('Test has failed.'.PHP_EOL);
} else {
    die ('Test OK.'.PHP_EOL);
}