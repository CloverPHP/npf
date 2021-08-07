<?php
declare(strict_types=1);

namespace Npf\Core\Db {

    use JetBrains\PhpStorm\Pure;
    use mysqli;
    use mysqli_result;
    use Npf\Core\App;
    use Npf\Core\Container;
    use Npf\Exception\DBQueryError;

    /**
     * Class DbMysqli
     * @package Core\Db
     */
    class DbMysqli extends DbDriver
    {
        public bool $connected;
        private string $queryMode;
        private string $connectionString;
        private bool|mysqli $mysqli;
        private bool|mysqli_result $resResult;
        private bool $tranEnable;
        private bool $tranStarted;
        private bool $persistent;
        #----------------------------------------------------------------------#
        # Class Initialize
        #----------------------------------------------------------------------#
        private function initialize()
        {
            $this->connected = false;
            $this->queryMode = 'store';
            $this->connectionString = '';
            $this->mysqli = false;
            $this->resResult = false;
            $this->tranEnable = false;
            $this->tranStarted = false;
            $this->persistent = false;
        }

        /**
         * DbMysqli constructor.
         * @param App $app
         * @param Container $config
         */
        final public function __construct(private App $app, private Container $config)
        {
            $this->initialize();
        }

        /**
         * Destructor
         */
        final public function __destruct()
        {
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
            if ($this->connected === false)
                return false;
            if ($this->isResLink($this->mysqli)) {
                $this->app->profiler->timerStart("db");
                if (!$this->persistent)
                    $this->mysqli->kill($this->mysqli->thread_id);
                $this->mysqli->close();
                $this->initialize();
                $this->app->profiler->saveQuery("disconnected {$this->connectionString}", "db");
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
            $this->disconnect();
            if (extension_loaded("mysqli") == false)
                throw new DBQueryError('Driver Mysqli is not exist.');
            $this->init($this->config->get('characterSet', 'UTF8MB4'), $this->config->get('collate', 'UTF8MB4_UNICODE_CI'), $this->config->get('timeOut', 10));
            $port = (int)$this->config->get('port', 3306);
            $this->persistent = (boolean)$this->config->get('persistent', false);
            $user = $this->config->get('user', 'root');
            $name = $this->config->get('name', '');
            $compress = (bool)$this->config->get('compress', false);
            $this->app->profiler->timerStart("db");
            $this->connectionString = "mysql://{$user}@{$host}:{$port}/{$name}";
            if (!$this->mysqli->real_connect(
                hostname: $this->persistent ? "p:{$host}" : $host,
                username: $user,
                password: $this->config->get('pass', ''),
                database: $name,
                port: $port,
                flags: ($compress === true ? MYSQLI_CLIENT_COMPRESS : 0)
            )
            ) {
                $this->initialize();
                throw new DBQueryError("DB Connect Failed : {$this->connectionString} " . $this->connectError());
            } else {
                $this->app->profiler->saveQuery("connected {$this->connectionString}", "db");
                $this->connected = true;
            }
            return $this->mysqli;
        }

        /**
         * @param string $characterSet
         * @param string $collate
         * @param int $timeOut
         */
        private function init(string $characterSet = 'UTF8MB4', string $collate = 'UTF8MB4_UNICODE_CI', int $timeOut = 1000): void
        {
            $this->mysqli = mysqli_init();
            $this->option(MYSQLI_OPT_CONNECT_TIMEOUT, $timeOut);
            $this->option(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
            $this->option(MYSQLI_INIT_COMMAND, "SET AUTOCOMMIT = 0;");
            $this->option(MYSQLI_INIT_COMMAND, "SET NAMES '{$characterSet}' COLLATE '{$collate}';");
        }

        /**
         * @param string|int $option
         * @param string|int|float|bool $value
         * @return bool
         */
        final public function option(string|int $option, string|int|float|bool $value): bool
        {
            return $this->mysqli->options($option, $value);
        }
        #----------------------------------------------------------------------#
        #Select or Listing from Db
        #----------------------------------------------------------------------#

        /**
         * Return escaped string with the mysqli
         * @param int|float|string $queryStr
         * @return ?string
         */
        final public function escapeStr(int|float|string $queryStr): ?string
        {
            $queryStr = (string)$queryStr;
            if ($this->mysqli === false)
                return str_replace(["'", '`'], ["\\'", '\\`'], $queryStr);
            return $this->mysqli->real_escape_string($queryStr);
        }
        #----------------------------------------------------------------------#
        #Transaction AutoCommit, Start End
        #----------------------------------------------------------------------#

        /**
         * @return bool|string
         */
        final public function info(): bool|string
        {
            if ($this->mysqli === false)
                return false;
            return $this->mysqli->host_info;
        }

        /**
         * @return bool
         */
        final public function ping(): bool
        {
            if ($this->mysqli === false)
                return false;
            return $this->mysqli->ping();
        }
        #----------------------------------------------------------------------#
        #Session of Query Handle
        #----------------------------------------------------------------------#

        /**
         * Select Database by the function : RETURN BOOLEAN
         * @param string $name
         * @return bool
         */
        final public function selectDB(string $name): bool
        {
            if ($this->mysqli === false)
                return false;

            $this->app->profiler->timerStart("db");
            $result = $this->mysqli->select_db($this->escapeStr($name));
            $this->app->profiler->saveQuery("select db $name", "db");
            return $result;
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
         * @param string $queryStr
         * @return mysqli_result|bool
         * @throws DBQueryError
         */
        final public function query(string $queryStr): mysqli_result|bool
        {
            $this->resResult = false;
            if ($this->tranQuery($queryStr))
                return $this->realQuery($queryStr);
            else {
                $this->resResult = false;
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
                        $this->app->profiler->timerStart("db");
                        if(!$this->tranStarted = $this->mysqli->begin_transaction())
                            throw new DBQueryError("Unable begin mysql transaction");
                        $this->app->profiler->saveQuery("begin transaction", "db");
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
         * @param string $queryStr
         * @return bool|mysqli_result
         * @throws DBQueryError
         */
        final public function realQuery(string $queryStr): bool|mysqli_result
        {
            if ($this->mysqli === false)
                return false;
            $this->app->profiler->timerStart("db");
            $result = $this->mysqli->real_query($queryStr);
            $this->resResult = $this->queryMode === 'use' ? $this->mysqli->use_result() : $this->mysqli->store_result();
            $this->resResult = $this->mysqli->field_count ? $this->resResult : $result;

            if ($this->mysqli->errno !== 0) {
                $queryStr = "Query error: {$this->mysqli->error} - {$queryStr}";
                $this->app->profiler->saveQuery($queryStr, "db");
                throw new DBQueryError($queryStr, (string)$this->mysqli->errno);
            } else
                $this->app->profiler->saveQuery($queryStr, "db");
            $this->lastQuery = $queryStr;
            return $this->resResult;
        }

        /**
         * Return Update Affected Row
         * @return bool|int
         */
        final public function affectedRow(): bool|int
        {
            if ($this->mysqli === false)
                return false;
            return $this->mysqli->affected_rows;
        }

        /**
         * Get more result from the current result set (for big result set)
         * @return bool
         */
        final public function resultSetMore(): bool
        {
            if ($this->mysqli === false)
                return false;
            return $this->mysqli->more_results();
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
            if ($this->mysqli === false)
                return false;
            while ($this->mysqli->next_result())
                if ($resResult = $this->mysqli->store_result())
                    $resResult->free();
            return true;
        }

        /**
         * Use Next Result Set, once query return
         * @return bool
         */
        final public function resultSetNext(): bool
        {
            if ($this->mysqli === false)
                return false;
            return $this->mysqli->next_result();
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
            if ($this->mysqli === false)
                return false;
            return $this->resResult = $this->mysqli->store_result();
        }

        /**
         * Free the mysqli result set
         * @param mysqli_result|bool|null $resResult
         * @return bool
         */
        final public function free(mysqli_result|bool|null $resResult = null): bool
        {
            if ($this->mysqli === false)
                return false;
            $resResult = $this->getResResult($resResult);
            if ($this->isResResult($resResult)) {
                $resResult->free();
                if ($resResult === $this->resResult)
                    $this->resResult = false;
                return true;
            } else
                return false;
        }

        /**
         * Get the current result set resource object
         * @param mysqli_result|bool|null $resResult
         * @return mysqli_result|bool|null
         */
        private function getResResult(mysqli_result|bool|null $resResult): mysqli_result|bool|null
        {
            return $resResult instanceof mysqli_result ? $resResult : $this->resResult;
        }

        /**
         * check is it the result set
         * @param mysqli_result|bool|null $resResult
         * @return bool
         */
        private function isResResult(mysqli_result|bool|null $resResult): bool
        {
            return $resResult instanceof mysqli_result;
        }

        /**
         * Get the Number of Fields from the current result set.
         * @param mysqli_result|bool|null $resResult
         * @return bool|int
         */
        #[Pure] final public function numFields(mysqli_result|bool|null $resResult = null): bool|int
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? $resResult->field_count : false;
        }

        /**
         * Fetch the Field from the current result set, return object
         * @param int $column
         * @param mysqli_result|bool|null $resResult
         * @return bool|object
         */
        final public function fetchField(int $column, mysqli_result|bool|null $resResult = null): object|bool
        {
            $resResult = $this->getResResult($resResult);
            if ($this->isResResult($resResult)) {
                $resResult->field_seek($column);
                return $resResult->fetch_field();
            } else
                return false;
        }

        /**
         * Fetch one of cell from the current result set, default is first row, first field.
         * @param int $column
         * @param int $row
         * @param mysqli_result|bool|null $resResult
         * @return mixed
         */
        final public function fetchCell(int $column = 0, int $row = 0, mysqli_result|bool|null $resResult = null): mixed
        {
            $resResult = $this->getResResult($resResult);
            if ($this->isResResult($resResult)) {
                $resResult->data_seek($row);
                $result = $resResult->fetch_row();
                return $result[$column] ?? null;
            } else
                return false;
        }

        /**
         * Return 1 row of the current result set, return index array
         * @param mysqli_result|bool|null $resResult
         * @return array|bool|null
         */
        final public function fetchRow(mysqli_result|bool|null $resResult = null): bool|array|null
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? $resResult->fetch_row() : null;
        }
        #----------------------------------------------------------------------#
        # Db Special Function
        #----------------------------------------------------------------------#

        /**
         * Return 1 row of the current result set, return assoc array
         * @param mysqli_result|bool|null $resResult
         * @return array|null
         */
        final public function fetchAssoc(mysqli_result|bool|null $resResult = null): ?array
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? $resResult->fetch_assoc() : null;
        }

        #----------------------------------------------------------------------#
        # DB Commit & Rollback
        #----------------------------------------------------------------------#

        /**
         * Get the Number of rows from the current result set.
         * @param mysqli_result|bool|null $resResult
         * @return bool|int
         */
        #[Pure] final public function numRows(mysqli_result|bool|null $resResult = null): bool|int
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) ? $resResult->num_rows : false;
        }

        /**
         * Seek the current result set positioning
         * @param int $row
         * @param mysqli_result|bool|null $resResult
         * @return bool
         */
        final public function seek(int $row, mysqli_result|bool|null $resResult = null): bool
        {
            $resResult = $this->getResResult($resResult);
            return $this->isResResult($resResult) && $resResult->data_seek($row);
        }
        #----------------------------------------------------------------------#
        #Session of the Error handling
        #----------------------------------------------------------------------#

        /**
         * Get Db Last Insert Id
         * @return int|string
         */
        final public function insertId(): int|string
        {
            return $this->mysqli->insert_id;
        }

        /**
         * Db Rollback
         * @return bool
         */
        final public function rollback(): bool
        {
            if ($this->mysqli === false)
                return false;
            $this->tranStarted = false;
            $this->app->profiler->timerStart("db");
            $result = $this->mysqli->rollback();
            $this->app->profiler->saveQuery("commit", "db");
            return $result;
        }

        /**
         * Db Commit & will return true if no start.
         * @return bool
         */
        final public function commit(): bool
        {
            if ($this->mysqli === false)
                return false;
            if ($this->tranStarted) {
                $this->tranStarted = false;
                $this->app->profiler->timerStart("db");
                $result = $this->mysqli->commit();
                $this->app->profiler->saveQuery("commit", "db");
                return $result;
            } else
                return true;
        }
        #----------------------------------------------------------------------#
        #Session of the Close or free result
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
         * Db Connection Error Number
         * @return int
         */
        final public function connectErrorNo(): int
        {
            return mysqli_connect_errno();
        }

        /**
         * Db Query Error Number
         * @return bool|int
         */
        final public function errorNo(): bool|int
        {
            if ($this->mysqli === false)
                return false;
            return $this->mysqli->errno;
        }

        /**
         * Db Query Error Message
         * @return bool|string
         */
        final public function error(): bool|string
        {
            if ($this->mysqli === false)
                return false;
            return $this->mysqli->error;
        }
    }
}