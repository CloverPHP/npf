<?php
declare(strict_types=1);

namespace Npf\Core\Db {

    use mysqli_result;
    use Npf\Core\App;
    use Npf\Core\Container;
    use Npf\Core\Exception;
    use Npf\Exception\DBQueryError;
    use Npf\Exception\UnknownClass;

    /**
     * Class DbData
     * @package Core\Db
     */
    class DbData
    {
        /**
         * @var string
         */
        private string $queryLock = '';
        /**
         * @var DbMysqli
         */
        public DbMysqli $driver;

        private string $colLiteral = '`';
        private string $valLiteral = "'";

        /**
         * SQLData constructor.
         * @param App $app
         * @param Container $config
         * @throws UnknownClass
         */
        public function __construct(private App $app, private Container $config)
        {
            $this->app = &$app;
            $this->config = &$config;
            $driver = __NAMESPACE__ . '\\' . $config->get('driver');
            if (class_exists($driver)) {
                $this->driver = new $driver($app, $config);
                if (!($this->driver instanceof DbDriver))
                    throw new UnknownClass("Class is not instanceof Driver ({$driver}) on DB.Data");
            } else {
                throw new UnknownClass("Db Driver({$driver}) not found on DB.Data");
            }
        }

        public function __destruct()
        {
            $this->close();
        }

        /**
         * SQL Close
         * @return bool
         */
        final public function close(): bool
        {
            return $this->driver->disconnect();
        }

        /**
         * Start Transaction, we just only flag on, won't start transaction until DML Query call
         */
        final public function tranStart(): void
        {
            $this->driver->tranStart();
        }

        /**
         * End Transaction, only flag off the transaction, but the current transaction won't affected
         */
        final public function tranEnd(): void
        {
            $this->driver->tranEnd();
        }

        /**
         * Query Lock for a once query call, default lock is 'FOR UPDATE'
         * @param string $mode
         */
        final public function queryLock(string $mode = 'FOR UPDATE'): void
        {
            $this->queryLock = $mode;
        }

        /**
         * Query Lock for a once query call, default lock is 'FOR UPDATE'
         * @param string $mode
         */
        final public function queryMode(string $mode = 'store'): void
        {
            $this->driver->queryMode($mode);
        }

        /**
         * Lock sql table
         * @param array|string $tables
         * @param string $mode
         * @throws DBQueryError
         */
        final public function lockTable(array|string $tables, string $mode = 'WRITE'): void
        {
            $mode = ($mode === 'WRITE' || $mode === 'READ') ? $mode : 'WRITE';
            $tableStr = '';
            if (is_array($tables) && !empty($tables)) {
                foreach ($tables as $table)
                    $tableStr .= (!empty($tableStr) ? ", " : "") . $this->colLiteral . $this->driver->escapeStr($table) . "{$this->colLiteral} {$mode}";
            } elseif (!empty($tables))
                $tableStr = $this->colLiteral . $this->driver->escapeStr($tables) . "{$this->colLiteral} {$mode}";
            if (!empty($tableStr))
                $this->driver->query("LOCK TABLES {$tableStr}");
        }

        /**
         * Unlock all the locked sql table on this session
         * @throws DBQueryError
         */
        public function unlockTable(): void
        {
            $this->driver->query("UNLOCK TABLES");
        }

        /**
         * Alias with Query
         * @param string $queryStr
         * @param int $resultMode
         * @return mixed
         * @throws DBQueryError
         */
        public function special(string $queryStr, int $resultMode = 0): mixed
        {
            return $this->query($queryStr, $resultMode);
        }

        /**
         * Query DB and return the result instant
         * @param string $queryStr
         * @param int $resultMode
         * @return mixed
         * @throws DBQueryError
         */
        final public function query(string $queryStr, int $resultMode = 0): mixed
        {
            $results = null;
            if (!empty($queryStr)) {
                $resResult = $this->driver->query($queryStr);
                if (NULL === $resResult || is_bool($resResult))
                    $results = $resResult;
                else {
                    switch ($this->driver->numRows($resResult)) {
                        case 0:
                            $results = null;
                            break;

                        case 1:
                            switch ($resultMode) {

                                case 2:
                                    $results[] = $this->driver->fetchRow($resResult);
                                    break;

                                case 1:
                                    $results[] = $this->driver->fetchAssoc($resResult);
                                    break;

                                default:
                                    if ($this->driver->numFields($resResult) === 0)
                                        $results = $this->driver->fetchCell(0, 0, $resResult);
                                    else
                                        $results = $this->driver->fetchAssoc($resResult);
                                    break;
                            }
                            break;

                        default:
                            switch ($resultMode) {

                                case 2:
                                    while ($row = $this->driver->fetchRow($resResult))
                                        $results[] = $row;
                                    break;

                                default:
                                    while ($row = $this->driver->fetchAssoc($resResult))
                                        $results[] = $row;
                                    break;
                            }
                    }
                    $this->driver->free($resResult);
                }
            }
            return $results;
        }

        /**
         * @param string|array $queryStr
         * @return array
         * @throws DBQueryError
         */
        final public function multiQuery(string|array $queryStr): array
        {
            if (is_string($queryStr))
                $queryStr = explode(";", $queryStr);
            return $this->driver->multiQuery($queryStr);
        }

        /**
         * Get the sql insert id
         */
        final public function getInsertId(): bool|int|string
        {
            return $this->driver->insertId();
        }

        /**
         * Query Select get result set.
         * @param mysqli_result $resultSet
         * @return array|null
         */
        final public function fetchRow(mysqli_result $resultSet): ?array
        {
            return $this->driver->fetchAssoc($resultSet);
        }

        /**
         * Query and get all row assoc / array result
         * @param string $table
         * @param string|array $column
         * @param string|null $key
         * @param array|null $cond
         * @param string|int|float|array|null $order
         * @param string|int|float|array|null $limit
         * @param string|int|float|array|null $group
         * @param string|int|float|array|null $having
         * @return array
         * @throws DBQueryError
         */
        final public function all(string $table,
                                  string|array $column = "*",
                                  string|null $key = null,
                                  array|null $cond = null,
                                  string|int|float|array|null $order = null,
                                  string|int|float|array|null $limit = null,
                                  string|int|float|array|null $group = null,
                                  string|int|float|array|null $having = null): array
        {
            $results = [];
            $resResult = $this->select($table, $column, $cond, $order, $limit, $group, $having);

            if (!$key) {
                while ($row = $this->driver->fetchAssoc($resResult))
                    $results[] = $row;
            } else {
                while ($row = $this->driver->fetchAssoc($resResult))
                    $results[$row[$key]] = $row;
            }
            $this->driver->free($resResult);
            return $results;
        }

        /**
         * Query Select get result set.
         * @param string $table
         * @param string|array $column
         * @param string|array|null $cond
         * @param string|int|float|array|null $order
         * @param string|int|float|array|null $limit
         * @param string|int|float|array|null $group
         * @param string|int|float|array|null $having
         * @return bool|mysqli_result|null
         * @throws DBQueryError
         */
        final public function select(string $table,
                                     string|array $column = "*",
                                     string|array|null $cond = null,
                                     string|int|float|array|null $order = null,
                                     string|int|float|array|null $limit = null,
                                     string|int|float|array|null $group = null,
                                     string|int|float|array|null $having = null): mysqli_result|bool|null
        {
            $resultSet = $this->driver->query($this->getSelectSQL($table, $column, $cond, $order, $limit, $group, $having));
            $this->queryLock = '';
            return $resultSet;
        }

        /**
         * Get select sql
         * @param string $table
         * @param string|array $column
         * @param string|array|null $cond
         * @param string|int|float|array|null $order
         * @param string|int|float|array|null $limit
         * @param string|int|float|array|null $group
         * @param string|int|float|array|null $having
         * @return string
         * @throws DBQueryError
         */
        final public function getSelectSQL(string $table,
                                           string|array $column = "*",
                                           string|array|null $cond = null,
                                           string|int|float|array|null $order = null,
                                           string|int|float|array|null $limit = null,
                                           string|int|float|array|null $group = null,
                                           string|int|float|array|null $having = null): string
        {
            if (!empty($table)) {
                $tableStr = $this->convertSplit($this->driver->escapeStr($table));
                return "SELECT " . $this->getColSQL($column) . " FROM {$this->colLiteral}{$tableStr}{$this->colLiteral}" .
                    $this->getCondition($cond) . $this->getGroup($group, $having) . $this->getOrder($order) .
                    $this->getLimit($limit) . (!empty($this->queryLock) ? " {$this->queryLock}" : "");
            } else
                return "";
        }

        /**
         * @param string $content
         * @return string
         */
        private function convertSplit(string $content): string
        {
            return str_replace([".", ","],
                ["{$this->colLiteral}.{$this->colLiteral}",
                    "{$this->colLiteral},{$this->colLiteral}"], $content);
        }

        /**
         * Get column sql
         * @param null|string|array $column
         * @param bool $alias
         * @return string
         */
        private function getColSQL(null|string|array $column, bool $alias = true): string
        {
            $columnStr = "";
            if (!empty($column)) {
                switch (gettype($column)) {
                    case 'array':
                        foreach ($column as $ColAlias => $colName)
                            $columnStr .= (!empty($columnStr) ? ", " : "") . $this->getColNm($colName, $ColAlias, $alias);
                        break;

                    case 'string';
                        $columnStr = $this->getColNm($column, null, $alias);
                }
            }
            return $columnStr;
        }

        /**
         * Get column name
         * @param string $colName
         * @param int|string|null $colAlias
         * @param bool $alias
         * @param bool $fnc
         * @return string
         */
        private function getColNm(string $colName,
                                  null|int|string $colAlias = null,
                                  bool $alias = true,
                                  bool $fnc = false): string
        {
            $result = '';
            $pattern = "/^{DB_([A-Z_]+)}/";
            if (preg_match("/^{$this->valLiteral}(.+){$this->valLiteral}$/", $colName, $matches))
                $result = $this->valLiteral . $this->driver->escapeStr($matches[1]) . $this->
                    valLiteral;
            else {
                if (preg_match($pattern, $colName, $matches)) {
                    $match = strtoupper($matches[1]);
                    switch ($match) {
                        case 'DISTINCT':
                            $result = "DISTINCT " . $this->getColNm(str_replace($matches[0], "", $colName), null, false);
                            break;

                        case 'NO_CACHE':
                            $result = "SQL_NO_CACHE " . $this->getColNm(str_replace($matches[0], "", $colName), null,
                                    false);
                            break;

                        case 'HIGH_PRIORITY':
                            $result = "HIGH_PRIORITY " . $this->getColNm(str_replace($matches[0], "", $colName), null,
                                    false);
                            break;

                        case 'LOW_PRIORITY':
                            $result = "LOW_PRIORITY " . $this->getColNm(str_replace($matches[0], "", $colName), null,
                                    false);
                            break;

                        case "MAX":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "MAX(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "MIN":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "MIN(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case 'FNC':
                            $result = $this->getColNm(str_replace($matches[0], "", $colName), null, false, true);
                            break;

                        case 'VAL':
                            $result = "'" . $this->getColNm(str_replace($matches[0], "", $colName), null, false, true) .
                                "'";
                            break;

                        case "SUM":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "SUM(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "TIME":
                        case "TIMESTAMP":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "TIME(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "DATE":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "DATE(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "DAY":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "DAY(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "MONTH":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "MONTH(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "YEAR":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "YEAR(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "NOW":
                            $result = "NOW()";
                            break;

                        case "LENGTH":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "LENGTH(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "COUNT":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = ($colName === "*" ? "COUNT(*)" : "COUNT(" . $this->getColNm($colName, null, false) .
                                ")");
                            break;

                        case "FROM_UNIXTIME":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "FROM_UNIXTIME(" . $this->getColNm($colName, null, false) . ")";
                            break;

                        case "FROM_UNIXTIME_DATE":
                            $colName = str_replace($matches[0], "", $colName);
                            $result = "FROM_UNIXTIME(" . $this->getColNm($colName, null, false) . ", \"%Y-%m-%d\")";
                            break;

                        case in_array($match, range('A', 'Z')):
                            $result = $this->getColNm(str_replace($matches[0], "", $colName), null, false);
                            break;
                    }
                } elseif ($fnc === false && !str_contains($colName, $this->colLiteral) && $colName !== '*') {
                    $colName = $this->convertSplit($colName);
                    $result = $this->colLiteral . $this->driver->escapeStr($colName) . $this->
                        colLiteral;
                } else
                    $result = $this->driver->escapeStr($colName);
                if ($alias)
                    $result .= (!is_int($colAlias) && !empty($colAlias) ? " AS {$this->valLiteral}" .
                        $this->driver->escapeStr($colAlias) . $this->valLiteral : "");
            }
            return $result;
        }

        /**
         * Get condition sql
         * @param string|array|null $cond
         * @return string
         */
        private function getCondition(string|array|null $cond = null): string
        {
            $condStr = '';
            $matches = [];
            if (!empty($cond)) {
                if (is_array($cond)) {
                    $pattern = "/^{DB_(OR|XOR|LB|RB|AND)}$/i";
                    $logic = true;
                    $bracket = 0;
                    $matches = [];
                    foreach ($cond as $key => $value) {
                        if (is_string($value) && preg_match($pattern, $value, $matches)) {
                            switch (strtoupper($matches[1])) {
                                case "OR":
                                    $condStr .= " OR ";
                                    $logic = true;
                                    break;

                                case "XOR":
                                    $condStr .= " XOR ";
                                    $logic = true;
                                    break;

                                case "LB":
                                    $condStr .= (!$logic ? " AND " : "") . "(";
                                    $bracket++;
                                    $logic = true;
                                    break;

                                case "RB":
                                    if ($bracket > 0) {
                                        $bracket--;
                                        $condStr .= ")";
                                    }
                                    break;

                                default:
                                    $condStr .= " AND ";
                                    $logic = true;
                                    break;
                            }
                        } else {
                            if (!empty($key)) {
                                $condStr .= (!$logic ? " AND " : "") . $this->getCond($key, $value);
                                $logic = false;
                            }
                        }
                    }
                    $condStr = preg_replace("/(\s+)(AND|OR|XOR|\\()(\\s+)$/i", "", $condStr);
                    if ($bracket > 0)
                        for ($i = $bracket; $i > 0; $i--)
                            $condStr .= ")";
                } elseif (preg_match_all("/({$this->colLiteral}?)([^\W]|[\w\d\-]+)({$this->colLiteral}?)(\s*)([>=<!]|LIKE|IS NOT NULL|IN|NOT IN|BETWEEN|NOT BETWEEN)(\s*)(({$this->valLiteral}([^{$this->valLiteral}]*){$this->valLiteral})|[\d.]|(\((.*)\))*)/i",
                        $cond, $matches) <= 0
                )
                    $condStr = $cond;
            }
            return !empty($condStr) ? " WHERE {$condStr}" : "";
        }

        /**
         * Get one of the condition
         * @param string|array $colName
         * @param mixed $colValue
         * @return string
         */
        private function getCond(string|array $colName, mixed $colValue): string
        {
            $condStr = '';
            switch (gettype($colValue)) {
                case 'array':
                    $value = reset($colValue);
                    $key = key($colValue);
                    $condStr = $this->getColNm($colName);
                    $result = [];
                    switch (strtoupper($key)) {
                        case "LIST":
                        case "IN":
                            if (!is_array($value))
                                $value = [];
                            if (!empty($value))
                                foreach ($value as $v)
                                    $result[] = $this->driver->escapeStr($v);
                            $condStr .= " IN ({$this->valLiteral}" . implode("{$this->valLiteral},{$this->valLiteral}", $result) .
                                "{$this->valLiteral})";
                            break;

                        case "XLIST":
                        case "XIN":
                            if (!is_array($value))
                                $value = [];
                            if (!empty($value))
                                foreach ($value as $v)
                                    $result[] = $this->driver->escapeStr($v);
                            $condStr .= " NOT IN ({$this->valLiteral}" . implode("{$this->valLiteral},{$this->valLiteral}", $result) .
                                "{$this->valLiteral})";
                            break;

                        case "BETWEEN":
                            if (is_array($value) && count($value) === 2) {
                                $value = array_values($value);
                                $condStr .= " BETWEEN {$this->valLiteral}" . $this->driver->escapeStr($value[0]) .
                                    "{$this->valLiteral} AND {$this->valLiteral}" . $this->driver->escapeStr($value[1]) .
                                    $this->valLiteral;
                            }
                            break;

                        case "XBETWEEN":
                            if (is_array($value) && count($value) === 2) {
                                $value = array_values($value);
                                $condStr .= " NOT BETWEEN {$this->valLiteral}" . $this->driver->escapeStr($value[0]) .
                                    "{$this->valLiteral} AND {$this->valLiteral}" . $this->driver->escapeStr($value[1]) .
                                    $this->valLiteral;
                            }
                            break;
                        default:
                            $condStr = "";
                    }
                    break;

                case 'double':
                case 'integer':
                case "string":
                case 'boolean';
                    $pattern = "/^{DB_([A-Z]+)}/";
                    $operator = " = ";
                    if (is_bool($colValue))
                        $colValue = '';
                    if (is_string($colValue) && preg_match($pattern, $colValue, $matches)) {
                        $operator = match (strtoupper($matches[1])) {
                            "NE" => " != ",
                            "GE" => " >= ",
                            "GT" => " > ",
                            "LE" => " <= ",
                            "LT" => " < ",
                            "NNE" => " <=> ",
                            "LIKE" => " LIKE ",
                            "XLIKE" => "NOT LIKE ",
                            "INULL" => " IS NULL",
                            "XINULL" => " IS NOT NULL",
                        };
                        if ($operator !== " = ")
                            $colValue = str_replace($matches[0], "", $colValue);
                    }
                    $condStr = $this->getColNm($colName) . "{$operator}";
                    $condStr .= substr($operator, -1) === " " ? $this->getColVal($colValue, $colName) :
                        "";
                    break;

                case 'NULL':
                    $condStr = $this->getColNm($colName) . " = null";
            }
            return $condStr;
        }

        /**
         * Get column value
         * @param string|int|float|array|null $colValue
         * @param string|int|null $colName
         * @return bool|string|int|float
         */
        private function getColVal(string|int|float|array|null $colValue, string|int|null $colName = null): bool|string|int|float
        {
            if ($colValue === null)
                return "NULL";
            else {
                $pattern = "/^{DB_([A-Z_]+)}/";
                if (is_array($colValue) || is_object($colValue))
                    $colValue = json_encode($colValue);
                if (is_string($colValue) && preg_match($pattern, $colValue, $matches)) {
                    $colValue = $this->driver->escapeStr(str_replace($matches[0], "", $colValue));
                    switch (strtoupper($matches[1])) {
                        case "YEAR":
                        case "MONTH":
                        case "DAY":
                        case "COUNT":
                        case "SUM":
                        case 'COL':
                            return $this->getColNm($colValue);

                        case 'FNC':
                            return $colValue;

                        case "NOW":
                            return "NOW()";

                        case "TIME":
                            return "CURRENT_TIME()";

                        case "INC":
                            $colValue = (double)$colValue;
                            return $this->getColNm($colName) . " + {$colValue}";

                        case "DEC":
                            $colValue = (double)$colValue;
                            return $this->getColNm($colName) . " - {$colValue}";

                        case "TIMES":
                            $colValue = (double)$colValue;
                            return $this->getColNm($colName) . " * {$colValue}";

                        case "DIV":
                            $colValue = (double)$colValue;
                            return $this->getColNm($colName) . " / {$colValue}";

                        case "POWER":
                            $colValue = (double)$colValue;
                            return $this->getColNm($colName) . " ^ {$colValue}";
                    }
                } else {
                    $isNumber = is_int($colValue) || is_float($colValue);
                    return $isNumber ? $colValue : $this->valLiteral . $this->driver->escapeStr($colValue) . $this->valLiteral;
                }
            }
            return false;
        }

        /**
         * Get group by sql
         * @param array|string|null $group
         * @param array|string|null $having
         * @return string
         */
        private function getGroup(array|string|null $group = null, array|string|null $having = null): string
        {
            if (is_array($group))
                $group = array_values($group);
            $groupStr = $this->getColSQL($group);
            return !empty($groupStr) ? " GROUP BY {$groupStr}" . $this->getHaving($having) :
                "";
        }

        /**
         * Get having sql
         * @param array|string|null $cond
         * @return string
         */
        private function getHaving(array|string|null $cond = null): string
        {
            return !empty($cond) ? " HAVING " . substr($this->getCondition($cond), 7) :
                "";
        }

        /**
         * Get sql order
         * @param string|array|null $order
         * @return string
         */
        private function getOrder(string|array|null $order = null): string
        {
            $orderStr = '';
            if (!empty($order)) {
                if (is_array($order)) {
                    foreach ($order as $key => $orderType) {
                        if (is_int($key)) {
                            if (in_array($orderType, ["{DB_RAND}", "RAND"]))
                                $key = 'RAND';
                            else {
                                $key = $orderType;
                                $orderType = "ASC";
                            }
                        } else {
                            $orderType = strtoupper($orderType);
                            if ($orderType != "DESC")
                                $orderType = "ASC";
                        }
                        if ($key === "RAND")
                            $orderStr .= (!empty($orderStr) ? ", " : "") . "RAND()";
                        else {
                            $key = $this->getColNm($key);
                            $orderStr .= (!empty($orderStr) ? ", " : "") . "{$key} {$orderType}";
                        }
                    }
                } else
                    $orderStr = in_array(strtoupper($order), ["{DB_RAND}", "RAND"]) ? "RAND()" : $this->getColNm($order) .
                        " ASC";
            }
            return !empty($orderStr) ? " ORDER BY {$orderStr}" : "";
        }

        /**
         * get limit sql
         * @param array|string|float|int|null $limit
         * @return string
         * @throws DBQueryError
         */
        private function getLimit(array|string|float|int $limit = null): string
        {
            $limitStr = '';
            if (!empty($limit)) {
                switch (gettype($limit)) {
                    case "array":
                        $start = (int)reset($limit);
                        $limit = (int)next($limit);
                        if (!empty($limit))
                            $limitStr = "{$start}, {$limit}";
                        break;

                    case "string":
                    case "double":
                    case "integer":
                        $limitStr = (int)$limit;
                        break;
                    default:
                        throw new DBQueryError('Unexpected value');
                }
            }
            return !empty($limitStr) ? " LIMIT {$limitStr}" : "";
        }

        /**
         * Query a column and get entire column array
         * @param string $table
         * @param string|array $column
         * @param array|null $cond
         * @param string|array|null $order
         * @param int|float|string|array|null $limit
         * @param string|array|null $group
         * @param string|array|null $having
         * @return bool|array
         * @throws DBQueryError
         */
        final public function column(string $table,
                                     string|array $column,
                                     array|null $cond = null,
                                     string|array|null $order = null,
                                     int|float|string|array|null $limit = 0,
                                     string|array|null $group = null,
                                     string|array|null $having = null): bool|array
        {
            if (!empty($table) && !empty($column)) {
                $resResult = $this->select($table, $column, $cond, $order, $limit, $group, $having);
                if (!$resResult)
                    return false;
                else {
                    $results = [];
                    $NumCol = $this->driver->numFields($resResult);
                    while ($row = $this->driver->fetchRow($resResult)) {
                        if ($NumCol > 1)
                            $results[$row[0]] = $row[1];
                        else
                            $results[] = $row[0];
                    }
                    $this->driver->free($resResult);
                    return $results;
                }
            } else
                return false;
        }

        /**
         * Query & get one row with assoc array
         * @param string $table
         * @param string|array $column
         * @param array|null $cond
         * @param string|array|null $order
         * @param int $seek
         * @param string|array|null $group
         * @param string|array|null $having
         * @return array|null
         * @throws DBQueryError
         */
        final public function one(string $table,
                                  string|array $column = "*",
                                  array|null $cond = null,
                                  string|array|null $order = null,
                                  int $seek = 0,
                                  string|array|null $group = null,
                                  string|array|null $having = null): ?array
        {
            if (!empty($table)) {
                return $this->driver->fetchAssoc($this->select($table, $column, $cond, $order,
                    [$seek, 1], $group, $having));
            } else
                return null;
        }

        /**
         * Query and get one cell
         * @param string $table
         * @param string|array $column
         * @param array|string|null $cond
         * @param array|string|null $order
         * @param int $seek
         * @return string|array|bool|int|float|null
         * @throws DBQueryError
         */
        final public function cell(string $table,
                                   string|array $column,
                                   array|string|null $cond = null,
                                   array|string|null $order = null,
                                   int $seek = 0): string|array|bool|int|null|float
        {
            if (!empty($table) && !empty($column)) {
                return $this->driver->fetchCell(0, 0,
                    $this->select($table, $column, $cond, $order, [$seek, 1]));
            } else
                return null;
        }

        /**
         * Query and get the first row, first cell with sum query
         * @param string $table
         * @param string|array $column
         * @param array|null $cond
         * @param string|array|null $group
         * @param string|array|null $having
         * @return bool|float
         * @throws DBQueryError
         */
        final public function sum(string $table,
                                  string|array $column,
                                  array|null $cond = null,
                                  string|array|null $group = null,
                                  string|array|null $having = null): float|bool
        {
            if (!empty($table)) {
                if (is_array($column))
                    $column = reset($column);
                return (double)$this->driver->fetchCell(0, 0, $this->select($table,
                    "{DB_SUM}" . (!empty($column) ? $column : ""), $cond, null, 1, $group, $having));
            } else
                return false;
        }

        /**
         * Sql Bulk Insert
         * @param string $table
         * @param array $fields
         * @param array $values
         * @param bool $ignore
         * @return bool|mysqli_result
         * @throws DBQueryError
         */
        final public function inserts(string $table,
                                      array $fields,
                                      array $values,
                                      bool $ignore = false): bool|mysqli_result
        {
            if (!is_array($fields) || !is_array($values) || empty($fields) || empty($values))
                return false;

            if (!empty($table) && is_array($fields) && !empty($values)) {
                $this->queryLock = '';
                $tableStr = $this->convertSplit($this->driver->escapeStr($table));
                $iColField = '';
                $insertValues = '';
                $countField = 0;
                foreach ($fields as $colName) {
                    $countField++;
                    $iColField .= (!empty($iColField) ? ", " : "") . $this->getColNm($colName);
                }
                if (count($values) === 1)
                    $values = [reset($values)];
                foreach ($values as $value) {
                    if (!empty($value) && is_array($value) && count($value) === $countField) {
                        $insertValue = join(",", array_map(function ($v) {
                            return $this->getColVal($v);
                        }, $value));
                        $insertValues .= (!empty($insertValues) ? ", " : "") . "({$insertValue})";
                    }
                }
                return (!empty($insertValues)) ? $this->driver->query("INSERT" . ($ignore ? " IGNORE" : "") . " INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} ({$iColField}) VALUES {$insertValues}")
                    : false;
            } else
                return false;
        }

        /**
         * Query and get the row count.
         * @param string $table
         * @param array|null $cond
         * @param string|array|null $column
         * @param string|array|null $group
         * @param string|array|null $having
         * @return bool|int
         * @throws DBQueryError
         */
        final public function count(string $table,
                                    array|null $cond = null,
                                    string|array|null $column = null,
                                    string|array|null $group = null,
                                    string|array|null $having = null): bool|int
        {
            if (!empty($table)) {
                if (is_array($column))
                    $column = reset($column);
                return (int)$this->driver->fetchCell(0, 0,
                    $this->select($table,
                        "{DB_COUNT}" .
                        (!empty($column) ? $column : "*"), $cond, null, 1, $group, $having));
            } else
                return false;
        }

        /**
         * Insert Query and get last insert id
         * @param string $table
         * @param array $colDatas
         * @param bool $ignore
         * @return int|bool
         * @throws DBQueryError
         */
        final public function insert(string $table,
                                     array $colDatas,
                                     bool $ignore = false): int|bool
        {
            if (!empty($table) && is_array($colDatas) && !empty($colDatas)) {
                $this->queryLock = '';
                $tableStr = $this->convertSplit($this->driver->escapeStr($table));
                $setCol = '';
                foreach ($colDatas as $colName => $colData)
                    $setCol .= (!empty($setCol) ? ", " : "") . $this->getColNm($colName) . " = " . $this->
                        getColVal($colData, $colName);
                if ($this->driver->query("INSERT" . ($ignore ? " IGNORE" : "") . " INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} SET {$setCol}")) {
                    return $this->driver->insertId();
                } else {
                    return false;
                }
            } else
                return false;
        }

        /**
         * Update(s) Query
         * @param string $table
         * @param array $colDatas
         * @param array|null $cond
         * @param string|array|null $order
         * @param int|float|string|array|null $limit
         * @param bool $ignore
         * @return bool
         * @throws DBQueryError
         */
        final public function update(string $table,
                                     array $colDatas,
                                     array|null $cond = null,
                                     string|array|null $order = null,
                                     int|float|string|array|null $limit = null,
                                     bool $ignore = false): bool
        {
            if (!empty($table) && is_array($colDatas) && !empty($colDatas)) {
                $this->queryLock = '';
                $tableStr = $this->convertSplit($this->driver->escapeStr($table));
                $setCol = '';
                foreach ($colDatas as $colName => $colData)
                    $setCol .= (!empty($setCol) ? ", " : "") . $this->getColNm($colName) . " = " . $this->
                        getColVal($colData, $colName);

                return $this->driver->query("UPDATE" . ($ignore ? " IGNORE" : "") . " {$this->colLiteral}{$tableStr}{$this->colLiteral} SET {$setCol}" .
                    $this->getCondition($cond) . $this->getOrder($order) . $this->getLimit($limit));
            } else
                return false;
        }

        /**
         * Insert/Update via sql
         * @param string $table
         * @param array $colDatas
         * @return bool
         * @throws DBQueryError
         */
        final public function insertUpdate(string $table, array $colDatas): bool
        {
            if (!empty($table) && is_array($colDatas) && !empty($colDatas)) {
                $this->queryLock = '';
                $tableStr = $this->convertSplit($this->driver->escapeStr($table));
                $columnUpdate = '';
                $setCol = '';
                foreach ($colDatas as $colName => $colData) {
                    $setCol .= (!empty($setCol) ? ", " : "") . $this->getColNm($colName) . " = " . $this->
                        getColVal($colData, $colName);
                    $columnUpdate .= (!empty($columnUpdate) ? ", " : "") . "{$colName} = VALUES({$colName})";
                }
                return $this->driver->query("INSERT INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} SET {$setCol} ON DUPLICATE KEY UPDATE {$columnUpdate}");
            } else
                return false;
        }

        /**
         * Insert/Update Multiple row via sql
         * @param string $table
         * @param array $fields
         * @param array $values
         * @return bool
         * @throws DBQueryError
         */
        final public function insertsUpdate(string $table,
                                            array $fields,
                                            array $values): bool
        {
            if (!is_array($fields) || !is_array($values) || empty($fields) || empty($values))
                return false;

            if (!empty($table) && is_array($fields) && !empty($values)) {
                $this->queryLock = '';
                $tableStr = $this->convertSplit($this->driver->escapeStr($table));
                $iColField = '';
                $insertValues = '';
                $columnUpdate = '';
                $countField = 0;
                foreach ($fields as $colName) {
                    $countField++;
                    $colName = $this->getColNm($colName);
                    $iColField .= (!empty($iColField) ? ", " : "") . $colName;
                    $columnUpdate .= (!empty($columnUpdate) ? ", " : "") . "{$colName} = VALUES({$colName})";
                }
                if (count($values) === 1)
                    $values = [reset($values)];
                foreach ($values as $value) {
                    $insertValue = "";
                    if (!empty($value) && is_array($value) && count($value) === $countField) {
                        foreach ($value as $val)
                            $insertValue .= (!empty($insertValue) ? ", " : "") . $this->getColVal($val);
                        $insertValues .= (!empty($insertValues) ? ", " : "") . "({$insertValue})";
                    }
                }
                return (!empty($insertValues) && !empty($columnUpdate)) ? $this->driver->query("INSERT INTO {$this->colLiteral}{$tableStr}{$this->colLiteral} ({$iColField}) VALUES {$insertValues} ON DUPLICATE KEY UPDATE {$columnUpdate}")
                    : false;
            } else
                return false;
        }

        /**
         * Insert/Update via condition
         * @param string $table
         * @param array $colData
         * @param array|null $cond
         * @param bool $check
         * @param bool $ignore
         * @return bool|int
         * @throws DBQueryError
         */
        final public function action(string $table,
                                     array $colData,
                                     array|null $cond = null,
                                     bool $check = false,
                                     bool $ignore = false): bool|int
        {
            if (!empty($cond) && $check === true)
                if ($this->count($table, $cond) === 0)
                    $cond = null;
            $this->queryLock = '';
            if (!empty($cond))
                return $this->update($table, $colData, $cond, null, null, $ignore);
            else
                return $this->insert($table, $colData, $ignore);
        }

        /**
         * SQL Delete row(s)
         * @param string $table
         * @param array|null $cond
         * @param string|array|null $order
         * @param int|float|string|array|null $limit
         * @return bool
         * @throws DBQueryError
         */
        final public function delete(string $table,
                                     array|null $cond = null,
                                     string|array|null $order = null,
                                     int|float|string|array|null $limit = null): bool
        {
            if (!empty($table)) {
                $this->queryLock = '';
                $tableStr = $this->convertSplit($this->driver->escapeStr($table));

                return $this->driver->query("DELETE FROM {$this->colLiteral}{$tableStr}{$this->colLiteral}" .
                    $this->getCondition($cond) . $this->getOrder($order) . $this->getLimit($limit));
            } else
                return false;
        }

        /**
         * Procedure SQL Function, return result set/multiple result set
         * @param string $name
         * @param array|null $param
         * @param bool $fetchAll
         * @return array|bool
         * @throws DBQueryError
         */
        final public function procedure(string $name, array|null $param = null, bool $fetchAll = false): bool|array
        {
            if (!empty($name)) {
                $this->queryLock = '';
                $result = null;
                $name = $this->driver->escapeStr($name);
                if (!empty($param)) {
                    foreach ($param as $key => $PValue)
                        $param[$key] = $this->getColVal($PValue, $key);
                    $this->driver->query("CALL {$name}(" . implode("','", $param) . ");");
                } else
                    $this->driver->query("CALL {$name}();");
                $data = [];
                if ($fetchAll) {
                    do {
                        if ($result = $this->driver->resultSetStore()) {
                            $dataNow = [];
                            while ($row = $this->driver->fetchAssoc($result))
                                $dataNow[] = $row;
                            $data[] = $dataNow;
                            $this->driver->free($result);
                        }
                    } while ($this->driver->resultSetNext());
                } else {
                    while ($row = $this->driver->fetchAssoc($result))
                        $data[] = $row;
                    $this->driver->resultSetClear();
                }
                return $data;
            } else
                return false;
        }

        /**
         * Copy row from source table to destination table via sql
         * @param string $tableScr
         * @param string $tableDes
         * @param array $colData
         * @param array|null $cond
         * @param string|array|null $order
         * @param int|float|string|array|null $limit
         * @param string|array|null $group
         * @param string|array|null $having
         * @param bool $ignore
         * @return bool
         * @throws DBQueryError
         * @throws Exception
         */
        protected final function copy(string $tableScr,
                                      string $tableDes,
                                      array $colData,
                                      array|null $cond = null,
                                      string|array|null $order = null,
                                      int|float|string|array|null $limit = null,
                                      string|array|null $group = null,
                                      string|array|null $having = null,
                                      bool $ignore = false): bool
        {
            if (!empty($tableScr) && !empty($tableDes) && !empty($colData) && is_array($colData)) {
                $this->queryLock = '';
                $tableScr = $this->convertSplit($this->driver->escapeStr($tableScr));
                $tableDes = $this->convertSplit($this->driver->escapeStr($tableDes));
                $colNameSrc = [];
                $colNameDes = [];
                foreach ($colData as $_ColNmSrc => $_ColNmDes) {
                    $colNameSrc[] = $_ColNmSrc;
                    $colNameDes[] = $_ColNmDes;
                }
                $colNameSrc = $this->getColSQL($colNameSrc, false);
                return $this->driver->query("INSERT" . ($ignore ? " IGNORE" : "") . " INTO {$this->colLiteral}{$tableDes}{$this->colLiteral} ({$colNameSrc}) " .
                    $this->getSelectSQL($tableScr, $colNameDes, $cond, $order, $limit, $group, $having));
            } else
                return false;
        }

        /**
         * SQL Affected Row
         * @return int
         */
        final public function affectedRow(): int
        {
            return $this->driver->affectedRow();
        }

        /**
         * Get Query Error Message
         * @return bool|string
         */
        final public function error(): bool|string
        {
            return $this->driver->error();
        }

        /**
         * Get Query Error MNumber
         * @return bool|int
         */
        final public function errno(): bool|int
        {
            return $this->driver->errorNo();
        }
    }
}