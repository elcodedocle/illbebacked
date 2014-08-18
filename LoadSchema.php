<?php
namespace info\synapp\tools\backup;
use \Exception;
use \PDO;
use \PDOException;

/**
 * Class LoadSchema
 * 
 * Class based on sql_parse.php script from the phpBB group
 * 
 * @copyright Gael Abadin
 * @license MIT Expat
 * @version 0.3.9
 * @package synapp\install
 * 
 */
class LoadSchema {

    /**
     * @var null|PDO $defaultDbh
     */
    private $defaultDbh;

    /**
     * @var array $defaultDbParams
     */
    private $defaultDbParams;

    /**
     * @var null|string $defaultSchemaFilename
     */
    private $defaultSchemaFilename;

    /**
     * @var null|string $defaultSchemaName
     */
    private $defaultSchemaName;

    /**
     * @var null|string $defaultUseMysqlCli
     */
    private $defaultUseMysqlCli;

    /**
     * @var bool $defaultOverwrite
     */
    private $defaultOverwrite;

    /**
     * Strip the comments out of $output sql file string
     * 
     * @param string $output
     * @return string
     */
    public static function removeComments(&$output) {
        
        $lines = explode("\n", $output);
        $output = "";

        $linecount = count($lines);

        $inComment = false;
        for ($i = 0; $i < $linecount; $i++) {
            if (preg_match("/^\\/\\*/", preg_quote($lines[$i]))) {
                $inComment = true;
            }

            if (!$inComment) {
                if (!preg_match('/^--( |$)/', $lines[$i])){
                    $output .= $lines[$i] . "\n";
                }
            }

            if (preg_match("/\\*\\/$/", preg_quote($lines[$i]))) {
                $inComment = false;
            }
        }

        unset($lines);
        return $output;
        
    }

    /**
     * Strip the comments (# remarks) out of $sql sql file string
     * 
     * @param string $sql
     * @return string
     */
    public function removeRemarks($sql) {
        
        $lines = explode("\n", $sql);

        $linecount = count($lines);
        $output = "";

        for ($i = 0; $i < $linecount; $i++) {
            
            if (($i != ($linecount - 1)) || (strlen($lines[$i]) > 0)) {
                if (isset($lines[$i][0]) && $lines[$i][0] != "#") {
                    $output .= $lines[$i] . "\n";
                } else {
                    $output .= "\n";
                }
                $lines[$i] = "";
            }
            
        }

        return $output;

    }

    /**
     * Split $sql file string into single sql statements (expects $sql to be trimmed)
     * 
     * @param string $sql
     * @param string $delimiter
     * @return array
     */
    public function splitSqlFile($sql, $delimiter) {
        // Split up our string into "possible" SQL statements.
        $tokens = explode($delimiter, $sql);

        // try to save mem.
        unset($sql);
        $output = array();

        // we don't actually care about the matches preg gives us.
        $matches = array();

        // this is faster than calling count($oktens) every time thru the loop.
        $tokenCount = count($tokens);
        for ($i = 0; $i < $tokenCount; $i++) {
            // Don't wanna add an empty string as the last thing in the array.
            if (($i != ($tokenCount - 1)) || (strlen($tokens[$i] > 0))) {
                // This is the total number of single quotes in the token.
                $totalQuotes = preg_match_all("/'/", $tokens[$i], $matches);
                // Counts single quotes that are preceded by an odd number of backslashes,
                // which means they're escaped quotes.
                $escapedQuotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$i], $matches);

                $unescapedQuotes = $totalQuotes - $escapedQuotes;

                // If the number of unescaped quotes is even, then the delimiter did NOT occur inside a string literal.
                if (($unescapedQuotes % 2) == 0) {
                    // It's a complete sql statement.
                    $output[] = $tokens[$i];
                    // save memory.
                    $tokens[$i] = "";
                } else {
                    // incomplete sql statement. keep adding tokens until we have a complete one.
                    // $temp will hold what we have so far.
                    $temp = $tokens[$i] . $delimiter;
                    // save memory..
                    $tokens[$i] = "";

                    // Do we have a complete statement yet?
                    $completeStmt = false;

                    for ($j = $i + 1; (!$completeStmt && ($j < $tokenCount)); $j++) {
                        // This is the total number of single quotes in the token.
                        $totalQuotes = preg_match_all("/'/", $tokens[$j], $matches);
                        // Counts single quotes that are preceded by an odd number of backslashes,
                        // which means they're escaped quotes.
                        $escapedQuotes = preg_match_all("/(?<!\\\\)(\\\\\\\\)*\\\\'/", $tokens[$j], $matches);

                        $unescapedQuotes = $totalQuotes - $escapedQuotes;

                        if (($unescapedQuotes % 2) == 1) {
                            // odd number of unescaped quotes. In combination with the previous incomplete
                            // statement(s), we now have a complete statement. (2 odds always make an even)
                            $output[] = $temp . $tokens[$j];

                            // save memory.
                            $tokens[$j] = "";
                            $temp = "";

                            // exit the loop.
                            $completeStmt = true;
                            // make sure the outer loop continues at the right point.
                            $i = $j;
                        } else {
                            // even number of unescaped quotes. We still don't have a complete statement.
                            // (1 odd and 1 even always make an odd)
                            $temp .= $tokens[$j] . $delimiter;
                            // save memory.
                            $tokens[$j] = "";
                        }

                    } // for..
                } // else
            }
        }

        return $output;
        
    }

    /**
     * Checks and sets load params
     *
     * @param null|string $schemaFilename
     * @param string $schemaName
     * @param null|array|\PDO $dbh
     * @param null|bool $useMysqlCli
     * @param null|bool $overwrite
     * @throws \Exception
     * @return bool
     */
    private function setLoadParams(&$schemaFilename, &$schemaName, &$dbh, &$useMysqlCli, &$overwrite){

        if (!isset($schemaFilename)){
            $schemaFilename = $this->defaultSchemaFilename;
        }
        if (!is_string($schemaFilename)||!file_exists($schemaFilename)) {
            throw new Exception (
                "Error: need a valid input file name.",
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
        
        if (!isset($dbh)){
            $dbh = $this->defaultDbh;
        }
        if (!isset($dbh)){
            $dbh = $this->defaultDbParams;
        }
        if (!isset($dbh)) {
            throw new Exception (
                "Error: need database connection params/PDO handler.",
                500
            );
        }
        if (is_array($dbh)){
            $dbh = $this->getDbhParamsFromArray($dbh);
        }

        if (!isset ($useMysqlCli)){
            $useMysqlCli = $this->defaultUseMysqlCli;
        }
        
        if (!isset($useMysqlCli)) {
            $useMysqlCli = is_array($dbh)?true:false;
        }
        
        if (is_array($dbh)&&$useMysqlCli!==true){
            $dbh = $this->getDbhFromConnectionParamsArray($dbh);
        }

        if (!isset ($overwrite)){
            $overwrite = $this->defaultOverwrite;
        }
        
        return true;
        
    }

    /**
     * Use mysql cli tool to load the .sql file
     *
     * @param null|string $schemaFilename (null for default)
     * @param null|string $schemaName (null for default)
     * @param null|array $dbh (null for default)
     * @param bool $overwrite
     * @throws \Exception
     * @return bool
     */
    public function loadUsingMysqlCli(
        $schemaFilename = null, 
        $schemaName = null, 
        $dbh = null, 
        $overwrite = null
    ){

        $useMysqlCli = true;
        if (
            $this->setLoadParams(
                $schemaFilename, 
                $schemaName, 
                $dbh, 
                $useMysqlCli, 
                $overwrite
            )!==true
        ){
            throw new Exception (
                "Error: cannot set load params.",
                500
            );
        }

        if (!is_array($dbh)){
            throw new Exception (
                "Error: cannot set database connection params.",
                500
            );
        }
        
        $password = (is_string($dbh['password'])&&strlen($dbh['password'])>0)?
            '-p'.$dbh['password']:'';

        if ($overwrite){
            $command = "mysql -h {$dbh['hostname']} -P {$dbh['port']} "
                ."-u {$dbh['user']} {$password} -e \"DROP DATABASE IF EXISTS "
                ."{$schemaName}\"";
            $output = array();
            exec($command,$output,$worked);
            //echo var_export($output,true);
            if ($worked!==0){
                throw new Exception (
                    "Error: error invoking mysql CLI command. Output:"
                    .PHP_EOL.var_export($output,true),
                    500
                );
            }
        }

        $command = "mysql -h {$dbh['hostname']} -P {$dbh['port']} "
            ."-u {$dbh['user']} {$password} -e \"CREATE DATABASE "
            ."{$schemaName}\"";
        $output = array();
        exec($command,$output,$worked);
        //echo var_export($output,true);
        if ($worked!==0){
            throw new Exception (
                "Error: error invoking mysql CLI command. Output:"
                .PHP_EOL.var_export($output,true),
                500
            );
        }
        $command = "mysql -h {$dbh['hostname']} -P {$dbh['port']} "
            ."-u {$dbh['user']} {$password} -e \"SOURCE {$schemaFilename}\" "
            ."{$schemaName}";
        $output = array();
        exec($command,$output,$worked);
        //echo var_export($output,true);
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
     * Set default/provided dbh params array
     * 
     * @param array $dbh
     * @return array
     */
    private function getDbhParamsFromArray($dbh){

        $defParams =
            (isset($this->defaultDbParams)&&is_array($this->defaultDbParams))?
                $this->defaultDbParams:array();
        $dbh['hostname'] = isset($dbh['hostname'])?$dbh['hostname']:
            (isset($defParams['hostname'])?$defParams['hostname']:'localhost');
        $dbh['port'] = isset($dbh['port'])?$dbh['port']:
            (isset($defParams['port'])?$defParams['port']:'3306');
        $dbh['user'] = isset($dbh['user'])?$dbh['user']:
            (isset($defParams['user'])?$defParams['user']:'root');
        $dbh['password'] = isset($dbh['password'])?$dbh['password']:
            (isset($defParams['password'])?$defParams['password']:'');
        
        return $dbh;
        
    }

    /**
     * Establishes a database connection
     *
     * @param array $dbh
     * @throws \Exception
     * @return \PDO
     */
    private function getDbhFromConnectionParamsArray($dbh){
        
        try {
            
            $dbh = new PDO(
                "mysql:host=".$dbh['hostname'].
                ";port=".$dbh['port'].
                ";charset=utf8",
                $dbh['user'],
                $dbh['password'],
                array(
                    PDO::ATTR_PERSISTENT => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                )
            );
            $dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES,true);
            $dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
            
            return $dbh;
            
        } catch (\PDOException $e) {
            
            throw new Exception(
                'Unable to connect to database',
                500
            );
            
        }
        
    }

    /**
     * Creates a schema on $dbh named $schemaName with the contents of
     * $schemaFilename sql file.
     *
     * @param null|string $schemaFilename (null for default)
     * @param null|string $schemaName (null for default)
     * @param null|PDO|array $dbh Array of mysqldump parameters or PDO database
     * handler (null for default)
     * @param null|bool $useMysqlCli
     * @param null|bool $overwrite
     * @throws \Exception an exception containing an error message an code
     * @return bool whether the operation succeeded
     */
    public function load(
        $schemaFilename = null, 
        $schemaName = null, 
        $dbh = null, 
        $useMysqlCli = null,
        $overwrite = null
    ){
        
        if ($this->setLoadParams(
                $schemaFilename, 
                $schemaName, 
                $dbh, 
                $useMysqlCli, 
                $overwrite
            )!==true){
            throw new Exception (
                "Error: cannot set load params.",
                500
            );
        }
        
        if ($useMysqlCli){
            return $this->loadUsingMysqlCli($schemaFilename,$schemaName,$dbh);
        }
        
        if ($overwrite===true){
            $stmt = $dbh->prepare("DROP DATABASE IF EXISTS '{$schemaName}'");
            $stmt->execute();
        } else {
            $stmt = $dbh->prepare("SHOW DATABASES LIKE '{$schemaName}'");
            $stmt->execute();
            if ($stmt->rowCount() === 1) {
                throw new Exception (
                    "Error: database already exists.",
                    500
                );
            }
        }

        $stmt = $dbh->prepare("CREATE DATABASE {$schemaName}");
        if (!$stmt->execute()) {
            throw new Exception (
                "CREATE DATABASE error.",
                500
            );
        }
        
        $stmt = $dbh->prepare("ALTER DATABASE {$schemaName} CHARACTER SET utf8 COLLATE utf8_unicode_ci");
        if (!$stmt->execute()) {
            throw new Exception (
                "ALTER DATABASE {$schemaName} CHARACTER SET utf8 COLLATE utf8_unicode_ci error.",
                500
            );
        }

        if (!$dbh->query("USE {$schemaName}")) {
            throw new Exception (
                "`USE {$schemaName}` error.",
                500
            );
        }

        $sqlQuery = @fread(@fopen($schemaFilename, 'r'), @filesize($schemaFilename));
        if (!$sqlQuery){
            throw new Exception (
                'Cannot read sql file',
                500
            );
        }
        $sqlQuery = $this->removeRemarks($sqlQuery);
        $sqlQuery = $this->splitSqlFile($sqlQuery, ';');

        $i = 1;
        foreach ($sqlQuery as $sql) {
            echo $i++;
            echo PHP_EOL;
            $stmt = $dbh->prepare($sql);
            if (!$stmt->execute()){
                throw new Exception ('error in query',500);
            }
        }

        return true;
        
    }

    /**
     * Set default required parameters
     *
     * @param null|string $defaultSchemaFilename
     * @param null|string $defaultSchemaName
     * @param null|PDO|array $defaultDbh (defaults to array() - will try to
     * connect to localhost using mysql client with standard parameters, user
     * root and no password). Array of mysqldump parameters or PDO database
     * handler
     * @param null|bool $defaultUseMysqlCli Whether to import the file to
     * the database using the external mysql CLI app ($dbh must be an array
     * providing the required connection parameters indexed by their names:
     * 'hostname', 'port', 'user' and 'password'. On parameter missing, the
     * corresponding construct default will be used and if a construct default
     * value for the parameter is missing too, a hardcoded default value will
     * be used instead)
     * @param bool $defaultOverwrite (Whether to DROP DATABASE IF EXISTS. 
     * Defaults to false)
     * @throws \Exception
     */
    public function __construct(
        $defaultSchemaFilename = null, 
        $defaultSchemaName = null, 
        $defaultDbh = array(),
        $defaultUseMysqlCli = null,
        $defaultOverwrite = false
    ){
        
        $this->defaultSchemaFilename = $defaultSchemaFilename;
        $this->defaultSchemaName = $defaultSchemaName;
        if (is_array($defaultDbh)){
            $this->defaultDbParams = $defaultDbh;
            if ($defaultUseMysqlCli === false){
                $this->defaultDbh = $this->getDbhFromConnectionParamsArray($defaultDbh);
            }
        } else {
            if ($defaultDbh instanceof \PDO){
                $this->defaultDbh = $defaultDbh;
            } else {
                throw new Exception(
                    "\$defaultDbh must be array or instanceof \\PDO",
                    500
                );
            }
        }
        $this->defaultUseMysqlCli = $defaultUseMysqlCli;
        $this->defaultOverwrite = $defaultOverwrite;
        
    }
    
}
?>