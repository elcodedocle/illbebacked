I'll be backed
==============
#####*A PHP MySQL/MariaDB database load/backup utility*

 Copyright (C) 2014 Gael Abadin<br/>
 License: [MIT Expat][1]<br />


### Requirements

* PHP >= 5.3 (with PDO extension or external mysql CLI client tool)
* mysqldump CLI tool

### Motivation

I wanted to trigger a database backup file load from some of my automated PHP 
integration tests on a restricted server (no access to mysql CLI) and didn't 
find any tool that satisfied my needs (i.e. PDO driver support).
 
### How to use

First of all, SQL transactions can take some time, so don't forget to increase 
your script's time limit (if you are in safe mode you may do it by modifying 
`max_execution_time` in your `php.ini` file):
 
```php
// 1 hour (if you need more than that you should not be using this tool!)
set_time_limit (3600); 
```

Also, despite the optimizations performed, you may want to increase the memory 
limit of your script
 
```php
// 512M (if you need more than that you should not be using this tool!)
ini_set('memory_limit','512M');
```

#### Standalone

Backup current database:
```bash
php /path/to/illbebackedcli.php [-o backup.sql] database
```
If `-o backup.sql` is ommited `database` will be backed up to a file named 
`$schemaName.'_'.date("Ymd_His", time()).'.sql'`

Create/overwrite database with `input.sql`:
```bash
php /path/to/illbebackedcli.php -i input.sql database
```

Backup current database and overwrite it with `input.sql`:
```bash
php /path/to/illbebackedcli.php -i input.sql -o output.sql database

Unix/Linux cron script (`~/.crontab` or /etc/crontab) automatic periodical 
backup to `$schemaName.'_'.date("Ymd_His", time()).'.sql'`: 
```
# * * * * *  command to execute
# ┬ ┬ ┬ ┬ ┬
# │ │ │ │ │
# │ │ │ │ │
# │ │ │ │ └───── day of week (0 - 6) (0 to 6 are Sunday to Saturday, or use names; 7 is Sunday, the same as 0)
# │ │ │ └────────── month (1 - 12)
# │ │ └─────────────── day of month (1 - 31)
# │ └──────────────────── hour (0 - 23)
# └───────────────────────── min (0 - 59)
00 06 * * * sudo -u someuser /usr/bin/php /path/to/illbebackedcli.php database
```

You can take a look at the provided `illbebackedcliconf.ini` to see other 
configuration options for this CLI tool (and use -c option to specify a 
different .ini config file for the CLI tool).

_Note the standalone version is a CLI tool, not intended to be deployed on a 
public access folder. A .htaccess file denying web access to the utility and
default backup path is included as a preventive measure, as well as a 
`PHP_SAPI!=='cli'` test. For a nice web based PHP MySQL database 
access/load/backup tool, check out [phpMyAdmin](http://www.phpmyadmin.net)._

#### Framework

For usage as a framework, the required class(es) must be imported and 
instantiated, and their methods called with the required parameter values 
(defaults applied on missing parameters can be provided on instantiation):

```php
require_once 'LoadSchema.php';
require_once 'DumpSchema.php';

use info\synapp\tools\backup\LoadSchema;
use info\synapp\tools\backup\DumpSchema;

// test_schema_dump.sql -> localhost:3306 test_schema 
$loadSchema = new LoadSchema(
        $defaultSchemaFilename = 'test_schema_dump.sql', 
        $defaultSchemaName = 'test_schema', 
        $defaultDbh = array(
            'host'=>'localhost',
            'port'=>'3306', //can also be a number
            'user'=>'root',
            'password'=>'rootpassword'
        ), 
        $defaultUseMysqlCli = true,
            // use mysql CLI client instead of PDO queries by default
        $defaultOverwrite = true // DROP DATABASE IF EXISTS
    );
$loadSchema->load(); // No args provided -> use default params set in construct

// localhost:3306 test_schema -> test_schema_dump_copy.sql
$dumpSchema = new DumpSchema(
        $defaultSchemaName = 'test_schema', 
        $defaultSchemaFilename = 'test_schema_dump_copy.sql', 
        $defaultDbh = array(
            'host'=>'localhost',
            'port'=>'3306', //can also be an int
            'user'=>'root',
            'password'=>'rootpassword'
        ), 
        $defaultOverwrite = true // overwrites schema filename if exists
    );
$dumpSchema->dump(); // No args provided -> use default params set in construct
```

Check out the phpdoc docblocks embedded on the code for more info and many 
useful extra settings.

### TODO

* Add no CLI dump option (PDO based instead of exec("mysqldump... dependant).

### Acks

- [The phpBB developers team] from which a good chunk of the "mysql-cli-less"
code of the load class was taken.

- Igor Romanenko anf the MySQL developers team, creators of mysqldump utility 
and mysql CLI.


Enjoy!

(

And, if you're happy with this product, donate! 

bitcoin: 1A1rRxys47gtL2pERM9FjqcTzbyFKC5aS2 

dogecoin: D9TnK9G2edQcaGsduGBRWRZiuYCScWMLq4

paypal: http://goo.gl/5BMS5Q

)


[1]: https://raw.githubusercontent.com/elcodedocle/illbebacked/master/LICENSE
