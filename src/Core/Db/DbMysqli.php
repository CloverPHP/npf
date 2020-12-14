<?php
declare(strict_types=1);

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
        public bool $connected = false;

        private string $queryMode = 'store';
        private null|bool|mysqli $resLink;
        private null|bool|mysqli_result $resResult;
        private bool $queryError = false;
        private bool $tranEnable = false;
        private bool $tranStarted = false;
        private bool $persistent = false;
        #----------------------------------------------------------------------#
        # Class Initialize
        #----------------------------------------------------------------------#

        /**
         * DbMysqli constructor.
         * @param App $app
         * @param Container $config
         */
        final public function __construct(private App $app, private Container $config)
        {
        }

        /**
         * @param $name
         * @return mixed
         */
        final public function __get(string $name): mixed
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
         * Is Connected
         */
        final public function isConnected(): bool
        {
            return $this->connected;
        }

        /**
         * Kill the mysqli thread and disconnect from the mysqli
         * @return bool
         */
        final public function disconnect(): bool
        {
            if (!$this->connected)
                return false;
            $profiler = &$this->app->profiler;
            if ($this->isResLink($this->resLink)) {
                $sTime = -$profiler->elapsed();
                if (!$this->persistent) {
                    $threadId = @mysqli_thread_id($this->resLink);
                    if ($threadId > 0) {
                        @mysqli_kill($this->resLink, $threadId);
                    }
                }
                @mysqli_close($this->resLink);
                $profiler->saveQuery("close", $sTime, "db");
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
        private function isResLink(?mysqli $resLink): bool
        {
            return $resLink instanceof mysqli;
        }
        #----------------------------------------------------------------------#
        #Session of Connect or change user
        #----------------------------------------------------------------------#

        /**
         * Make a connection and store connect id : RETURN RESOURCE LINK
         * @param string $host
         * @return bool|mysqli|null
         * @throws DBQueryError
         */
        final public function connect(string $host = 'localhost'): bool|mysqli|null
        {
            if (extension_loaded("mysqli") == false)
                throw new DBQueryError('Driver Mysqli is not exist.');
            $this->init($this->config->get('characterSet', 'UTF8MB4'), $this->config->get('collate', 'UTF8MB4_UNICODE_CI'), $this->config->get('timeOut', 10));
            $port = (int)$this->config->get('port', 3306);
            $this->persistent = (boolean)$this->config->get('persistent', false);
            $user = $this->config->get('user', 'root');
            $name = $this->config->get('name', '');
            $this->app->ignoreError();
            if (!@mysqli_real_connect($this->resLink, $this->escapeStr($this->persistent ? "p:{$host}" :
                $host), $this->escapeStr($user), $this->escapeStr($this->config->get('pass', '')), $this->escapeStr($name),
                $port)
            ) {
                $this->connected = false;
                $this->app->noticeError();
                throw new DBQueryError("DB Connect Failed : mysql://{$user}@{$host}:{$port}/{$name} " . $this->connectError());
            } else
                $this->connected = true;
            $this->app->noticeError();
            return $this->resLink;
        }

        /**
         * @param string $characterSet
         * @param string $collate
         * @param int $timeOut
         */
        private function init($characterSet = 'UTF8MB4', $collate = 'UTF8MB4_UNICODE_CI', $timeOut = 1000): void
        {
            $this->resLink = mysqli_init();
            $this->option(MYSQLI_OPT_CONNECT_TIMEOUT, $timeOut);
            $this->option(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
            $this->option(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT = 0;");
            $this->option(MYSQLI_INIT_COMMAND, "SET NAMES '{$characterSet}' COLLATE '{$collate}';");
        }

        /**
         * @param $option
         * @param $value
         * @return bool
         */
        final public function option(string|int $option, string|int|float|bool $value): bool
        {
            return mysqli_options($this->resLink, $option, $value);
        }
        #----------------------------------------------------------------------#
        #Select or Listing from Db
        #----------------------------------------------------------------------#

        /**
         * Return escaped string with the mysqli
         * @param $queryStr
         * @return ?string
         */
        final public function escapeStr(string $queryStr): ?string
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
        final public function connectError(): string
        {
            return mysqli_connect_error();
        }

        /**
         * @return bool|string
         */
        final public function info(): bool|string
        {
            if (!$this->connected)
                return false;
            return mysqli_get_host_info($this->resLink);
        }

        /**
         * @return bool
         */
        final public function ping(): bool
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
        final public function selectDB(string $name): bool
        {
            if (!$this->connected)
                return false;
            return mysqli_select_db($this->resLink, $this->escapeStr($name));
        }

        /**
         * Transaction Start
         */
        final public function tranStart(): void
        {
            $this->tranEnable = true;
        }

        /**
         * @return bool
         */
        final public function isTranOn(): bool
        {
            return $this->tranStarted;
        }

        /**
         * Transaction End
         */
        final public function tranEnd(): void
        {
            $this->tranEnable = false;
        }

        /**
         * @param string $mode
         */
        final public function queryMode(string $mode = 'store'): void
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
        final public function multiQuery(array $queryStr): array
        {
            $result = [];
            foreach ($queryStr as $k => $sql)
                $result[$k] = $this->query($sql);
            return $result;
        }

        /**
         * Db Query
         * @param $queryStr
         * @return bool|mysqli_result
         * @throws DBQueryError
         */
        final public function query(string $queryStr): bool|mysqli_result
        {
            if (!$this->connected)
                return false;
            $this->resResult = null;
            if ($this->tranQuery($queryStr))
                return $this->realQuery($queryStr);
            else {
                $this->resResult = null;
                return false;
            }

        }

        /**
         * Start Transaction if tranStarted = true and have DML Query
         * @param string $queryStr
         * @param bool $multi
         * @return mysqli_result|bool|null
         * @throws DBQueryError
         */
        final public function tranQuery(string $queryStr, bool $multi = false): mysqli_result|bool|null
        {
            if (!empty($queryStr) && $this->tranEnable === true && $this->tranStarted === false) {
                $queries = ($multi === true) ? $this->querySplit($queryStr) : [$queryStr];
                foreach ($queries as $query)
                    if ((strtoupper(substr($query, 0, 6)) !== 'SELECT' && strtoupper(substr($query,
                                0, 3)) !== 'SET' && strtoupper(substr($query, 0, 5)) !== 'FLUSH') || strtoupper
                        (substr($query, -10)) === 'FOR UPDATE'
                    ) {
                        $this->tranStarted = $this->realQuery("begin");
                        $this->queryError = !$this->tranStarted;
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
         * @param string $queryStr
         * @return array|string
         */
        private function querySplit(string $queryStr): array|string
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
        final public function realQuery(string $queryStr): bool|mysqli_result
        {
            if (!$this->connected)
                return false;
            $profiler = $this->app->profiler;
            $sTime = -$profiler->elapsed();
            $result = mysqli_real_query($this->resLink, $queryStr);
            $this->resResult = $this->queryMode === 'use' ? mysqli_use_result($this->resLink) : mysqli_store_result($this->resLink);
            $this->resResult = (mysqli_field_count($this->resLink)) ? $this->resResult : $result;
            $errNo = (int)mysqli_errno($this->resLink);

            if ($errNo !== 0) {
                $this->queryError = true;
                $errorMsg = mysqli_error($this->resLink);
                $queryStr = "Query error: {$errorMsg} - {$queryStr}";
                $profiler->saveQuery($queryStr, $sTime, "db");
                throw new DBQueryError($queryStr, $errNo);
            } else
                $profiler->saveQuery($queryStr, $sTime, "db");
            $this->lastQuery = $queryStr;
            return $this->resResult;
        }

        /**
         * Return Update Affected Row
         * @return bool|int
         */
        final public function affectedRow(): bool|int
        {
            if (!$this->connected)
                return false;
            return mysqli_affected_rows($this->resLink);
        }

        /**
         * Get more result from the current result set (for big result set)
         * @return bool
         */
        final public function resultSetMore(): bool
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
        final public function resultSetClear(): bool
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
        final public function resultSetNext(): bool
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
         * @return mysqli_result|bool|null
         */
        final public function resultSetStore(): mysqli_result|bool|null
        {
            if (!$this->connected)
                return false;
            $this->resResult = mysqli_store_result($this->resLink);
            return $this->resResult;
        }

        /**
         * Free the mysqli result set
         * @param mysqli_result|null $resResult
         * @return bool
         */
        final public function free(?mysqli_result $resResult = null): bool
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
         * @return mysqli_result|null
         */
        private function getResResult(?mysqli_result $resResult): ?mysqli_result
        {
            return $resResult instanceof mysqli_result ? $resResult : $this->resResult;
        }

        /**
         * check is it the result set
         * @param ?mysqli_result $resResult
         * @return bool
         */
        private function isResResult(?mysqli_result $resResult): bool
        {
            return $resResult instanceof mysqli_result;
        }

        /**
         * Get the Number of Fields from the current result set.
         * @param mysqli_result|null $resResult
         * @return bool|int
         */
        final public function numFields(?mysqli_result $resResult = null): bool|int
        {
            //$resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_num_fields($resResult) : false;
        }

        /**
         * Fetch the Field from the current result set, return object
         * @param $column
         * @param ?mysqli_result $resResult
         * @return bool|object
         */
        final public function fetchField(int $column, ?mysqli_result $resResult = null): object|bool
        {
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
         * @param mysqli_result|null $resResult
         * @return mixed
         */
        final public function fetchCell(int $column = 0, int $row = 0, ?mysqli_result $resResult = null): mixed
        {
            $resResult = $this->getResResult($resResult);
            if ($this->isResResult($resResult)) {
                mysqli_data_seek($resResult, $row);
                $result = mysqli_fetch_row($resResult);
                return $result[$column];
            } else
                return false;
        }

        /**
         * Return 1 row of the current result set, return index array
         * @param ?mysqli_result $resResult
         * @return array|bool|null
         */
        final public function fetchRow(?mysqli_result $resResult = null): bool|array|null
        {
            return $this->isResResult($resResult) ? mysqli_fetch_row($resResult) : null;
        }
        #----------------------------------------------------------------------#
        # Db Special Function
        #----------------------------------------------------------------------#

        /**
         * Return 1 row of the current result set, return assoc array
         * @param mysqli_result|null $resResult
         * @return array|null
         */
        final public function fetchAssoc(?mysqli_result $resResult = null): ?array
        {
            //$resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_fetch_assoc($resResult) : null;
        }

        #----------------------------------------------------------------------#
        # DB Commit & Rollback
        #----------------------------------------------------------------------#

        /**
         * Get the Number of rows from the current result set.
         * @param ?mysqli_result $resResult
         * @return bool|int
         */
        final public function numRows(?mysqli_result $resResult = null): bool|int
        {
            //$resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_num_rows($resResult) : false;
        }

        /**
         * Seek the current result set positioning
         * @param int $row
         * @param mysqli_result|null $resResult
         * @return bool
         */
        final public function seek(int $row, ?mysqli_result $resResult = null): bool
        {
            //$resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? mysqli_data_seek($resResult, $row) : false;
        }
        #----------------------------------------------------------------------#
        #Session of the Error handling
        #----------------------------------------------------------------------#

        /**
         * Get Db Last Insert Id
         * @return bool|int|string
         */
        final public function insertId(): bool|int|string
        {
            return mysqli_insert_id($this->resLink);
        }

        /**
         * Db Rollback
         * @return bool
         * @throws DBQueryError
         */
        final public function rollback(): bool
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
        final public function commit(): bool
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
        final public function connectErrorNo(): int
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
        final public function errorNo(): bool|int
        {
            if (!$this->connected)
                return false;
            return mysqli_errno($this->resLink);
        }

        /**
         * Db Query Error Message
         * @return bool|string
         */
        final public function error(): bool|string
        {
            if (!$this->connected)
                return false;
            return mysqli_error($this->resLink);
        }
    }
}