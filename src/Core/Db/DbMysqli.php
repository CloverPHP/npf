<?PHP

namespace Npf\Core\Db {

    use mysqli;
    use mysqli_result;
    use Npf\Core\App;
    use Npf\Core\Container;
    use Npf\Exception\DBQueryError;
    use function mysqli_errno;
    use function mysqli_error;
    use function mysqli_field_count;
    use function mysqli_real_query;
    use function mysqli_store_result;
    use function mysqli_use_result;

    /**
     * Class DbMysqli
     * @package Core\Db
     */
    class DbMysqli extends DbDriver
    {
        public $connected = false;
        /**
         * @var App
         */
        private $app;
        /**
         * @var Container
         */
        private $config;
        private $queryMode = 'store';
        private $resLink;
        private $resResult;
        private $tranEnable = false;
        private $tranStarted = false;
        private $persistent = false;
        #----------------------------------------------------------------------#
        # Class Initialize
        #----------------------------------------------------------------------#

        /**
         * DbMysqli constructor.
         * @param App $app
         * @param Container $config
         */
        final public function __construct(App &$app, Container &$config)
        {
            $this->app = &$app;
            $this->config = &$config;
        }

        /**
         * @param $name
         * @return null
         */
        final public function __get($name)
        {
            if (isset($this->{$name}))
                return $this->{$name};
            else
                return null;
        }

        /**
         * Destructor
         */
        final public function __destruct()
        {
            if ($this->connected)
                $this->disconnect();
        }
        #----------------------------------------------------------------------#
        # Connection Initialize
        #----------------------------------------------------------------------#

        /**
         * Destructor
         */
        final public function isConnected()
        {
            return $this->connected;
        }

        /**
         * Kill the mysqli thread and disconnect from the mysqli
         * @return bool
         */
        final public function disconnect()
        {
            if (!$this->connected)
                return false;
            if ($this->isResLink($this->resLink)) {
                $this->app->profiler->timerStart("db");
                if (!$this->persistent) {
                    $threadId = @mysqli_thread_id($this->resLink);
                    if ($threadId > 0) {
                        @mysqli_kill($this->resLink, $threadId);
                    }
                }
                @mysqli_close($this->resLink);
                $this->app->profiler->saveQuery("close", "db");
                $this->resLink = null;
                $this->connected = false;
                return true;
            }
            return false;
        }
        #----------------------------------------------------------------------#
        #Session of Option
        #----------------------------------------------------------------------#

        /**
         * check is it the mysqli link
         * @param mysqli|mixed $resLink Resource Link
         * @return bool
         */
        private function isResLink($resLink)
        {
            return $resLink instanceof mysqli;
        }
        #----------------------------------------------------------------------#
        #Session of Connect or change user
        #----------------------------------------------------------------------#

        /**
         * Make a connection and store connect id : RETURN RESOURCE LINK
         * @param string $host
         * @return bool
         * @throws DBQueryError
         */
        final public function connect($host = 'localhost')
        {
            if (extension_loaded("mysqli") == false)
                throw new DBQueryError('Driver Mysqli is not exist.');
            $this->init($this->config->get('characterSet', 'UTF8MB4'), $this->config->get('collate', 'UTF8MB4_UNICODE_CI'), $this->config->get('timeOut', 10));
            $port = (int)$this->config->get('port', 3306);
            $this->persistent = (boolean)$this->config->get('persistent', false);
            $user = $this->config->get('user', 'root');
            $name = $this->config->get('name', '');
            if (!@mysqli_real_connect($this->resLink, $this->escapeStr($this->persistent ? "p:{$host}" :
                $host), $this->escapeStr($user), $this->escapeStr($this->config->get('pass', '')), $this->escapeStr($name),
                $port)
            ) {
                $this->connected = false;
                throw new DBQueryError("DB Connect Failed : mysql://{$user}@{$host}:{$port}/{$name} " . $this->connectError());
            } else
                $this->connected = true;
            return $this->resLink;
        }

        /**
         * @param string $characterSet
         * @param string $collate
         * @param int $timeOut
         */
        private function init($characterSet = 'UTF8MB4', $collate = 'UTF8MB4_UNICODE_CI', $timeOut = 1000)
        {
            $this->resLink = mysqli_init();
            $this->option(MYSQLI_OPT_CONNECT_TIMEOUT, $timeOut);
            $this->option(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
            $this->option(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT = 0;");
            $this->option(MYSQLI_INIT_COMMAND, "SET NAMES '{$characterSet}' COLLATE '{$collate}';");
        }

        /**
         * @param $Option
         * @param $Data
         * @return bool
         */
        final public function option($Option, $Data)
        {
            return mysqli_options($this->resLink, $Option, $Data);
        }
        #----------------------------------------------------------------------#
        #Select or Listing from Db
        #----------------------------------------------------------------------#

        /**
         * Return escaped string with the mysqli
         * @param $queryStr
         * @return array|string|string[]
         */
        final public function escapeStr($queryStr)
        {
            if (!$this->connected)
                return str_replace(["'", '`'], ["\\'", '\\`'], $queryStr);
            return mysqli_real_escape_string($this->resLink, $queryStr);
        }
        #----------------------------------------------------------------------#
        #Transaction AutoCommit, Start End
        #----------------------------------------------------------------------#

        /**
         * Db Connection Error Message
         * @return string
         */
        final public function connectError()
        {
            return mysqli_connect_error();
        }

        /**
         * @return bool|string
         */
        final public function info()
        {
            if (!$this->connected)
                return false;
            return mysqli_get_host_info($this->resLink);
        }

        /**
         * @return bool
         */
        final public function ping()
        {
            if (!$this->connected)
                return false;
            return mysqli_ping($this->resLink);
        }
        #----------------------------------------------------------------------#
        #Session of Query Handle
        #----------------------------------------------------------------------#

        /**
         * Select Database by the function : RETURN BOOLEAN
         * @param $name
         * @return bool
         */
        final public function selectDB($name)
        {
            if (!$this->connected)
                return false;
            return mysqli_select_db($this->resLink, $this->escapeStr($name));
        }

        /**
         * Transaction Start
         */
        final public function tranStart()
        {
            $this->tranEnable = true;
        }

        /**
         * @return bool
         */
        final public function isTranOn()
        {
            return $this->tranStarted;
        }

        /**
         * Transaction End
         */
        final public function tranEnd()
        {
            $this->tranEnable = false;
        }

        /**
         * @param string $mode
         */
        final public function queryMode($mode = 'store')
        {
            $mode = strtolower($mode);
            if (in_array($mode, ['store', 'use'], true))
                $this->queryMode = $mode;
        }

        /**
         * Multiple Query, comma to split out.
         * @param array $queryStr
         * @return array
         * @throws DBQueryError
         */
        final public function multiQuery(array $queryStr)
        {
            $ret = [];
            foreach ($queryStr as $k => $sql) {
                $ret[$k] = $this->query($sql);
            }
            return $ret;
        }

        /**
         * Db Query
         * @param $queryStr
         * @return bool|mysqli_result
         * @throws DBQueryError
         */
        final public function query($queryStr)
        {
            if (!$this->connected)
                return false;
            $this->resResult = false;
            if ($this->tranQuery($queryStr))
                return $this->realQuery($queryStr);
            else {
                $this->resResult = false;
                return $this->resResult;
            }

        }

        /**
         * Start Transaction if dbtranenable = true and have DML Query
         * @param $queryStr
         * @param bool $Multi
         * @return bool
         * @throws DBQueryError
         */
        final public function tranQuery($queryStr, $Multi = false)
        {
            if (!empty($queryStr) && $this->tranEnable === true && $this->tranStarted === false) {
                $queries = ($Multi === true) ? $this->querySplit($queryStr) : [$queryStr];
                foreach ($queries as $query)
                    if ((strtoupper(substr($query, 0, 6)) !== 'SELECT' && strtoupper(substr($query,
                                0, 3)) !== 'SET' && strtoupper(substr($query, 0, 5)) !== 'FLUSH') || strtoupper
                        (substr($query, -10)) === 'FOR UPDATE'
                    ) {
                        $this->tranStarted = $this->realQuery("begin");
                        return $this->tranStarted;
                    }
                return true;
            } else
                return true;
        }
        #----------------------------------------------------------------------#
        #Session of ResultSet Handle.
        #----------------------------------------------------------------------#

        /**
         * Return the Split MultiQuery SQL to []
         * @param $queryStr
         * @return array|mixed
         */
        private function querySplit($queryStr)
        {
            $pattern = '%\s*((?:\'[^\'\\\\]*(?:\\\\.[^\'\\\\]*)*\'|"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"|/*[^*]*\*+([^*/][^*]*\*+)*/|\#.*|--.*|[^"\';#])+(?:;|$))%x';
            $matches = [];
            if (preg_match_all($pattern, $queryStr, $matches))
                return $matches[1];
            return [];
        }

        /**
         * @param $queryStr
         * @return bool|mysqli_result
         * @throws DBQueryError
         */
        final public function realQuery($queryStr)
        {
            if (!$this->connected)
                return false;
            $this->app->profiler->timerStart("db");
            $result = mysqli_real_query($this->resLink, $queryStr);
            $this->resResult = $this->queryMode === 'use' ? mysqli_use_result($this->resLink) : mysqli_store_result($this->resLink);
            $this->resResult = (mysqli_field_count($this->resLink)) ? $this->resResult : $result;
            $errNo = mysqli_errno($this->resLink);

            if ($errNo !== 0) {
                $errorMsg = mysqli_error($this->resLink);
                $queryStr = "Query error: {$errorMsg} - {$queryStr}";
                $this->app->profiler->saveQuery($queryStr, "db");
                throw new DBQueryError($queryStr, $errNo);
            } else
                $this->app->profiler->saveQuery($queryStr, "db");
            $this->lastQuery = $queryStr;
            return $this->resResult;
        }

        /**
         * Return Update Affected Row
         * @return bool|int
         */
        final public function affectedRow()
        {
            if (!$this->connected)
                return false;
            return mysqli_affected_rows($this->resLink);
        }

        /**
         * Get more result from the current result set (for big result set)
         * @return bool
         */
        final public function resultSetMore()
        {
            if (!$this->connected)
                return false;
            return mysqli_more_results($this->resLink);
        }
        #----------------------------------------------------------------------#
        #Session of the Result Field
        #----------------------------------------------------------------------#

        /**
         * Clear the result set to release the memory
         * @return bool
         */
        final public function resultSetClear()
        {
            if (!$this->connected)
                return false;
            while ($this->resultSetNext())
                if ($resResult = $this->resultSetStore())
                    $this->free($resResult);
            return true;
        }

        /**
         * Use Next Result Set, once query return
         * @return bool
         */
        final public function resultSetNext()
        {
            if (!$this->connected)
                return false;
            return mysqli_next_result($this->resLink);
        }
        #----------------------------------------------------------------------#
        #Session of the Result Data
        #----------------------------------------------------------------------#

        /**
         * Once query, store the current result set from db.
         * @return bool|mysqli_result
         */
        final public function resultSetStore()
        {
            if (!$this->connected)
                return false;
            $this->resResult = mysqli_store_result($this->resLink);
            return $this->resResult;
        }

        /**
         * Free the mysqli result set
         * @param null $resResult
         * @return bool
         */
        final public function free($resResult = null)
        {
            if (!$this->connected)
                return false;
            $resResult = $this->getResResult($resResult);
            if ($this->isResResult($resResult)) {
                mysqli_free_result($resResult);
                if ($resResult === $this->resResult)
                    $this->resResult = null;
                return true;
            } else
                return false;
        }

        /**
         * Get the current result set resource object
         * @param $resResult
         * @return mysqli_result
         */
        private function getResResult($resResult)
        {
            return $resResult instanceof mysqli_result ? $resResult : $this->resResult;
        }

        /**
         * check is it the result set
         * @param $resResult
         * @return boolean
         */
        private function isResResult($resResult)
        {
            return $resResult instanceof mysqli_result;
        }

        /**
         * Get the Number of Fields from the current result set.
         * @param null $resResult
         * @return bool|int
         */
        final public function numFields($resResult = null)
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_num_fields($resResult) : false;
        }

        /**
         * Fetch the Field from the current result set, return object
         * @param $column
         * @param null $resResult
         * @return bool|object
         */
        final public function fetchField($column, $resResult = null)
        {
            $resResult = $this->getResResult($resResult);
            if ($this->isResResult($resResult)) {
                mysqli_field_seek($resResult, $column);
                return mysqli_fetch_field($resResult);
            } else
                return false;
        }

        /**
         * Fetch one of cell from the current result set, default is first row, first field.
         * @param int $column
         * @param int $row
         * @param null $resResult
         * @return bool
         */
        final public function fetchCell($column = 0, $row = 0, $resResult = null)
        {
            $resResult = $this->getResResult($resResult);
            if ($this->isResResult($resResult)) {
                mysqli_data_seek($resResult, $row);
                $result = mysqli_fetch_row($resResult);
                return is_array($result) ? $result[$column] : $result;
            } else
                return false;
        }

        /**
         * Return 1 row of the current result set, return index array
         * @param null $resResult
         * @return array|bool|null
         */
        final public function fetchRow($resResult = null)
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_fetch_row($resResult) : null;
        }
        #----------------------------------------------------------------------#
        # Db Special Function
        #----------------------------------------------------------------------#

        /**
         * Return 1 row of the current result set, return assoc array
         * @param null $resResult
         * @return array|null
         */
        final public function fetchAssoc($resResult = null)
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_fetch_assoc($resResult) : null;
        }

        #----------------------------------------------------------------------#
        # DB Commit & Rollback
        #----------------------------------------------------------------------#

        /**
         * Get the Number of rows from the current result set.
         * @param null $resResult
         * @return bool|int
         */
        final public function numRows($resResult = null)
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_num_rows($resResult) : false;
        }

        /**
         * Seek the current result set positioning
         * @param $Row
         * @param null $resResult
         * @return bool
         */
        final public function seek($Row, $resResult = null)
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) && mysqli_data_seek($resResult, $Row);
        }
        #----------------------------------------------------------------------#
        #Session of the Error handling
        #----------------------------------------------------------------------#

        /**
         * Get Db Last Insert Id
         * @return int|string
         */
        final public function insertId()
        {
            return mysqli_insert_id($this->resLink);
        }

        /**
         * Db Rollback
         * @return bool
         * @throws DBQueryError
         */
        final public function rollback()
        {
            if (!$this->connected)
                return false;
            $this->tranStarted = false;
            return $this->realQuery("rollback");
        }

        /**
         * Db Commit & will return true if no start.
         * @return bool
         * @throws DBQueryError
         */
        final public function commit()
        {
            if (!$this->connected)
                return false;
            if ($this->tranStarted) {
                $this->tranStarted = false;
                return $this->realQuery("commit");
            } else
                return true;
        }

        /**
         * Db Connection Error Number
         * @return int
         */
        final public function connectErrorNo()
        {
            return mysqli_connect_errno();
        }
        #----------------------------------------------------------------------#
        #Session of the Close or free result
        #----------------------------------------------------------------------#

        /**
         * Db Query Error Number
         * @return bool|int
         */
        final public function errorNo()
        {
            if (!$this->connected)
                return false;
            return mysqli_errno($this->resLink);
        }

        /**
         * Db Query Error Message
         * @return bool|string
         */
        final public function error()
        {
            if (!$this->connected)
                return false;
            return mysqli_error($this->resLink);
        }
    }
}
