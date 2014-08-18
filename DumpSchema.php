<?php

namespace info\synapp\tools\backup;

use \Exception;

class DumpSchema {

    /**
     * @var null|string
     */
    private $defaultSchemaName;

    /**
     * @var null|string
     */
    private $defaultSchemaFilename;

    /**
     * @var null|array
     */
    private $defaultDbh;

    /**
     * @var bool
     */
    private $defaultOverwrite;

    /**
     * Checks and sets dump params
     *
     * @param string $schemaName
     * @param string $schemaFilename
     * @param array $dbh
     * @param null|bool $overwrite whether to overwrite dump file if exists
     * @throws \Exception
     * @return bool
     */
    private function setDumpParams(
        &$schemaName, 
        &$schemaFilename, 
        &$dbh, 
        &$overwrite = null
    ){

        if (!is_bool($overwrite)){
            $overwrite = $this->defaultOverwrite;
        }
        if (!isset($dbh)){
            $dbh = $this->defaultDbh;
        }
        if (!isset($dbh)) {
            throw new Exception (
                "Error: need database connection params/PDO handler.",
                500
            );
        }

        if (!isset($schemaName)){
            $schemaName = $this->defaultSchemaName;
        }
        if (!isset($schemaName)) {
            throw new Exception (
                "Error: need a database name.",
                500
            );
        }

        if (!isset($schemaFilename)){
            $schemaFilename = $this->defaultSchemaFilename;
        }
        if (!is_string($schemaFilename)||!(strlen($schemaFilename)>0)) {
            $schemaFilename = $schemaName.'_'.date("Ymd_His", time()).'.sql';
        }
        if (file_exists($schemaFilename)&&!$overwrite){
            throw new Exception(
                'Error: output file already exists',
                500
            );
        }

        return true;

    }

    /**
     * Dumps $schemaName database to a $schemaFilename file using mysqldump CLI
     * utility with the provided $dbh parameters
     *
     * @param null|string $schemaName
     * @param null|string $schemaFilename
     * @param null|array $dbh
     * @param null|bool $overwrite
     * @throws \Exception
     * @return bool
     */
    public function dump(
        $schemaName = null, 
        $schemaFilename = null,
        $dbh = null, 
        $overwrite = null
    ){

        if (
            $this->setDumpParams(
                $schemaName, 
                $schemaFilename, 
                $dbh, 
                $overwrite
            )!==true
        ){
            throw new Exception (
                "Error: cannot set dump params.",
                500
            );
        }

        if (!is_array($dbh)){
            throw new Exception (
                "Error: cannot set database connection params.",
                500
            );
        }

        if (!isset($dbh['hostname'])){
            $dbh['hostname'] = 'localhost';
        }
        if (!isset($dbh['port'])){
            $dbh['port'] = '3306';
        }
        if (!isset($dbh['user'])){
            $dbh['user'] = 'root';
        }

        $password = (is_string($dbh['password'])&&strlen($dbh['password'])>0)?
            '-p'.$dbh['password']:'';

        $command = "mysqldump --opt -h {$dbh['hostname']} -P {$dbh['port']} -u {$dbh['user']} {$password} -r \"{$schemaFilename}\" {$schemaName}";
        $output=array();
        exec($command,$output,$worked);
        if ($worked!==0){
            throw new Exception (
                "Error: error invoking mysql CLI command. Output:"
                .PHP_EOL.var_export($output,true),
                500
            );
        }

        return true;
    }

    /**
     * Sets default parameters
     *
     * @param null|string $defaultSchemaName
     * @param null|string $defaultSchemaFilename
     * @param null|array $defaultDbh ($dbh must be an array
     * providing the required connection parameters indexed by their names:
     * 'hostname', 'port', 'user' and 'password'. On parameter missing, the
     * corresponding construct default will be used and if a construct default
     * value for the parameter is missing too, a hardcoded default value will
     * be used instead)
     * @param bool $defaultOverwrite whether to overwrite dump file if exists. 
     * Defaults to false
     */
    public function __construct(
        $defaultSchemaName = null, 
        $defaultSchemaFilename = null, 
        $defaultDbh = array(), 
        $defaultOverwrite = false
    ){
        $this->defaultSchemaName = $defaultSchemaName;
        $this->defaultSchemaFilename = $defaultSchemaFilename;
        $this->defaultDbh = $defaultDbh;
        $this->defaultOverwrite = $defaultOverwrite;
    }

} 