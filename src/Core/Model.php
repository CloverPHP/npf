<?php
declare(strict_types=1);

namespace Npf\Core {

    use JetBrains\PhpStorm\Pure;
    use mysqli_result;
    use Npf\Exception\DBQueryError;

    /**
     * Class Model
     * @package Core
     */
    abstract class Model
    {
        /**
         * @var Db
         */
        protected Db $db;
        /**
         * @var string
         */
        protected string $tableName = '';
        /**
         * @var string
         */
        protected string $dbName = '';
        /**
         * @var string|null
         */
        protected ?string $prefix = null;

        /**
         * Model constructor.
         * @param App $app
         * @param Db|null $db
         */
        public function __construct(protected App $app, ?Db &$db = null)
        {
            if ($this->prefix === null)
                $this->prefix = $this->tableName . "_";
            if ($db instanceof Db)
                $this->db = &$db;
            else
                $this->db = &$this->app->db;
            $this->prefix = (string)$this->prefix;
        }

        /**
         * @param string $dbNames
         * @return Model
         */
        public function setDbName(string $dbNames = ''): self
        {
            if (is_string($dbNames) && !empty($dbNames))
                $this->dbName = $dbNames;
            return $this;
        }

        /**
         * @return bool|int|string
         */
        #[Pure] public function getLastQuery(): bool|int|string
        {
            return $this->db->getLastQuery();
        }

        /**
         * @return bool|int|string
         */
        protected function getLastInsertId(): bool|int|string
        {
            return $this->db->getInsertId();
        }

        /**
         * @param array $params
         * @param bool $ignore
         * @return bool|int|mysqli_result
         * @throws DBQueryError
         */
        protected function addOne(array $params, bool $ignore = false): bool|int|mysqli_result
        {
            $data = $this->buildOne($params);
            $ret = $this->db->insert($this->getTableName(), $data, $ignore);
            return (false === $ret) ? false : $this->db->getInsertId();
        }

        /**
         * @param $param
         * @return array
         */
        private function buildOne($param): array
        {
            $data = [];
            foreach ($param as $k => $v)
                $data["{$this->prefix}$k"] = $v;
            return $data;
        }

        /**
         * @return string
         */
        private function getTableName(): string
        {
            return !empty($this->dbName) ? "{$this->dbName}.{$this->tableName}" : $this->tableName;
        }

        /**
         * @param array $fields
         * @param array $params
         * @param bool $ignore
         * @return bool|int|mysqli_result
         * @throws DBQueryError
         */
        protected function addMulti(array $fields,
                                    array $params,
                                    bool $ignore = false): bool|int|mysqli_result
        {
            $fields = $this->buildField($fields);
            $ret = $this->db->inserts($this->getTableName(), $fields, $params, $ignore);
            return (false === $ret) ? false : $this->db->getInsertId();
        }

        /**
         * @param array $param
         * @return array
         */
        private function buildField(array $param): array
        {
            foreach ($param as &$v)
                $v = "{$this->prefix}$v";
            return $param;
        }

        /**
         * @param string|array $field
         * @param int|null $id
         * @param string|array|null $orderBy
         * @param int $seek
         * @param string|array|null $groupBy
         * @param string|array|null $having
         * @return array
         * @throws DBQueryError
         */
        protected function getOneById(string|array $field,
                                      ?int $id,
                                      null|string|array $orderBy = null,
                                      int $seek = 0,
                                      null|string|array $groupBy = null,
                                      null|string|array $having = null): array
        {
            $data = $this->db->one(
                $this->getTableName(),
                $this->buildSelCol($field),
                $this->buildIdField($id),
                $this->buildOrder($orderBy),
                $seek,
                $this->buildGroup($groupBy),
                $this->buildCond($having)
            );
            return !is_array($field) ? $this->formatOne($data) : $data;
        }

        /**
         * @param array|string $fields
         * @return array|bool|string
         */
        private function buildSelCol(array|string $fields): array|bool|string
        {
            $patternFnc = '/^\\{DB_FNC\\}(.*)/';
            $matches = [];
            if ($fields === '*')
                return $fields;
            elseif (is_array($fields) && !empty($fields)) {
                $result = [];
                foreach ($fields as $k => $v) {
                    if (is_numeric($k))
                        $k = $v;
                    $result[$k] = preg_match($patternFnc, $v, $matches) ? $v : $this->buildCmd($v);
                }
                return $result;
            } elseif (is_string($fields)) {
                return preg_match($patternFnc, $fields, $matches) ? $fields : $this->buildCmd($fields);
            } else
                return false;
        }

        /**
         * @param mixed $value
         * @param bool $prefix
         * @return null|int|float|string|array
         */
        private function buildCmd(mixed $value, bool $prefix = true): null|int|float|string|array
        {
            $matches = [];
            if (is_string($value) && !is_numeric($value)) {
                if (preg_match("/^({DB_([A-Z]|FNC|SUM|COL|COUNT|DISTINCT|MIN|MAX|RAND|DATE|DAY|MONTH|YEAR)})(.*)/", $value, $matches)) {
                    if (strtoupper($matches[2]) === 'FNC')
                        $prefix = false;
                    $value = (strtoupper($matches[2]) === 'RAND') ? "{DB_RAND}" : "{$matches[1]}" . ($prefix ? $this->prefix : "") . "{$matches[3]}";
                } elseif ($prefix)
                    $value = "{$this->prefix}{$value}";
            }
            return $value;
        }

        /**
         * Return Id Field
         * @param int $id
         * @return array
         */
        private function buildIdField(int $id): array
        {
            return [
                $this->prefix . 'id' => $id,
            ];
        }

        /**
         * @param mixed $orders
         * @return string|array|int|float|null
         */
        private function buildOrder(mixed $orders): string|array|int|null|float
        {
            if (is_array($orders) && !empty($orders)) {
                $data = [];
                foreach ($orders as $k => $v)
                    if (is_numeric($k)) {
                        if (in_array($v, ['{DB_RAND}', 'RAND']))
                            $data[$k] = 'RAND';
                        else
                            $data[$this->buildCmd($v)] = 'ASC';
                    } else {
                        $v = strtoupper((string)$v);
                        if ($v != "DESC")
                            $v = "ASC";
                        $data[$this->buildCmd($k)] = $v;
                    }
                return $data;
            } elseif (in_array($orders, ['{DB_RAND}', 'RAND']))
                return 'RAND';
            else
                return $this->buildCmd($orders);
        }

        /**
         * @param array|string|null $groupBy
         * @return array|string|null
         */
        private function buildGroup(array|string|null $groupBy): array|null|string
        {
            if (is_array($groupBy) && !empty($groupBy)) {
                $data = [];
                foreach ($groupBy as $v)
                    $data[] = $this->buildCmd($v);
                return $data;
            } elseif (is_string($groupBy)) {
                return $this->buildCmd($groupBy);
            } else
                return $groupBy;
        }

        /**
         * @param mixed $cond
         * @return null|int|float|string|array
         */
        private function buildCond(mixed $cond): null|int|float|string|array
        {
            if (is_array($cond) && !empty($cond)) {
                $data = [];
                foreach ($cond as $k => $v)
                    $data[$this->buildCmd($k)] = $this->buildCmd($v, false);
                return $data;
            } elseif (is_string($cond)) {
                return $this->buildCmd($cond);
            } else
                return $cond;
        }

        /**
         * @param ?array $param
         * @return array
         */
        #[Pure] private function formatOne(?array $param): array
        {
            $data = [];
            if ($param) {
                foreach ($param as $k => $v) {
                    $data[substr($k, strlen($this->prefix))] = $v;
                }
            }
            return $data;
        }

        /**
         * @param string $field
         * @param int $id
         * @param string|array|null $orderBy
         * @param int $seek
         * @return mixed
         * @throws DBQueryError
         */
        protected function getCellById(string $field,
                                       int $id,
                                       string|array|null $orderBy = null,
                                       int $seek = 0): mixed
        {
            return $this->db->cell(
                $this->getTableName(),
                $this->buildSelCol($field),
                $this->buildIdField($id),
                $this->buildOrder($orderBy),
                $seek
            );
        }

        /**
         * @param string $field
         * @param array|null $cond
         * @param string|array|null $groupBy
         * @param string|array|null $having
         * @return float|bool
         * @throws DBQueryError
         */
        protected function getSum(string $field,
                                  array $cond = null,
                                  string|array|null $groupBy = null,
                                  string|array|null $having = null): float|bool
        {
            return $this->db->sum(
                $this->getTableName(),
                $this->buildSelCol($field),
                $this->buildCond($cond),
                $this->buildGroup($groupBy),
                $this->buildCond($having)
            );
        }

        /**
         * @param string $field
         * @param ?array $cond
         * @param string|array|null $groupBy
         * @param string|array|null $having
         * @return bool|int
         * @throws DBQueryError
         */
        protected function getCount(string $field = '*',
                                    ?array $cond = null,
                                    string|array|null $groupBy = null,
                                    string|array|null $having = null): bool|int
        {
            return $this->db->count(
                $this->getTableName(),
                $this->buildCond($cond),
                $this->buildSelCol($field),
                $this->buildGroup($groupBy),
                $this->buildCond($having)
            );
        }

        /**
         * @param int $id
         * @param null $orderBy
         * @param int|float|string|array|null $limit
         * @return bool|int|mysqli_result
         * @throws DBQueryError
         */
        protected function deleteOneById(int $id,
                                         $orderBy = null,
                                         int|float|string|array|null $limit = 0): bool|int|mysqli_result
        {
            return $this->db->delete($this->getTableName(), $this->buildIdField($id), $this->buildOrder($orderBy), $limit);
        }

        /**
         * @param array $data
         * @param int $id
         * @param bool $ignore
         * @return bool|int|mysqli_result
         * @throws DBQueryError
         */
        protected function updateOneById(array $data,
                                         int $id,
                                         bool $ignore = false): bool|int|mysqli_result
        {
            return $this->db->update($this->getTableName(), $this->buildOne($data), $this->buildIdField($id), null, 1, $ignore);
        }

        /**
         * @param string|array $field
         * @param ?array $cond
         * @param string|array|null $orderBy
         * @param int $seek
         * @param string|array|null $groupBy
         * @param string|array|null $having
         * @return bool|array
         * @throws DBQueryError
         */
        protected function getOneByCond(string|array $field = '*',
                                        ?array $cond = null,
                                        string|array|null $orderBy = null,
                                        int $seek = 0,
                                        string|array|null $groupBy = null,
                                        string|array|null $having = null): bool|array
        {
            $data = $this->db->one(
                $this->getTableName(),
                $this->buildSelCol($field),
                $this->buildCond($cond),
                $this->buildOrder($orderBy),
                $seek,
                $this->buildGroup($groupBy),
                $this->buildCond($having)
            );

            return !is_array($field) ? $this->formatOne($data) : $data;
        }

        /**
         * @param string|array $field
         * @param array|null $cond
         * @param string|array|null $orderBy
         * @param int|array|null $limit
         * @param string|array|null $groupBy
         * @param string|array|null $having
         * @return bool|array
         * @throws DBQueryError
         */
        protected function getColumnByCond(array|string $field = '*',
                                           ?array $cond = null,
                                           string|array|null $orderBy = null,
                                           null|int|array $limit = 0,
                                           string|array|null $groupBy = null,
                                           string|array|null $having = null): bool|array
        {
            return $this->db->column(
                $this->getTableName(),
                $this->buildSelCol($field),
                $this->buildCond($cond),
                $this->buildOrder($orderBy),
                $limit,
                $this->buildGroup($groupBy),
                $this->buildCond($having)
            );
        }

        /**
         * @param array|string $field
         * @param ?array $cond
         * @param string|array|null $orderBy
         * @param int $seek
         * @return mixed
         * @throws DBQueryError
         */
        protected function getCellByCond(array|string $field,
                                         ?array $cond = null,
                                         string|array|null $orderBy = null,
                                         int $seek = 0): mixed
        {
            return $this->db->cell(
                $this->getTableName(),
                $this->buildSelCol($field),
                $this->buildCond($cond),
                $this->buildOrder($orderBy),
                $seek
            );
        }

        /**
         * @param string|array $field
         * @param ?string $key
         * @param array|null $cond
         * @param string|array|null $orderBy
         * @param int|float|string|array|null $limit
         * @param string|array|null $groupBy
         * @param string|array|null $having
         * @return bool|array
         * @throws DBQueryError
         */
        protected function getAllByCond(string|array $field = '*',
                                        ?string $key = null,
                                        array $cond = null,
                                        string|array|null $orderBy = null,
                                        int|float|string|array|null $limit = null,
                                        string|array|null $groupBy = null,
                                        string|array|null $having = null): bool|array
        {
            $key = is_null($key) ? $key : ($field === '*' ? $this->prefix . $key : $key);
            $data = $this->db->all(
                $this->getTableName(),
                $this->buildSelCol($field),
                $key,
                $this->buildCond($cond),
                $this->buildOrder($orderBy),
                $limit,
                $this->buildGroup($groupBy),
                $this->buildCond($having)
            );
            return !is_array($field) ? $this->formatAll($data) : $data;
        }

        /**
         * @param array $params
         * @return array
         */
        #[Pure] private function formatAll(array $params): array
        {
            $data = [];
            if ($params) {
                foreach ($params as $index => $param) {
                    $data[$index] = [];
                    if ($param) {
                        foreach ($param as $k => $v) {
                            $data[$index][substr($k, strlen($this->prefix))] = $v;
                        }
                    }
                }
            }
            return $data;
        }

        /**
         * @param array|null $cond
         * @param string|array|null $orderBy
         * @param int|float|string|array|null $limit
         * @return bool
         * @throws DBQueryError
         */
        protected function deleteOneByCond(?array $cond = null,
                                           string|array|null $orderBy = null,
                                           int|float|string|array|null $limit = 0): bool
        {
            return $this->db->delete($this->getTableName(), $this->buildCond($cond), $this->buildOrder($orderBy), $limit);
        }

        /**
         * @param array|null $cond
         * @param string|array|null $orderBy
         * @param int|float|string|array|null $limit
         * @return bool
         * @throws DBQueryError
         */
        protected function deleteAll(?array $cond = null,
                                     string|array|null $orderBy = null,
                                     int|float|string|array|null $limit = null): bool
        {
            return $this->db->delete($this->getTableName(), $this->buildCond($cond), $this->buildOrder($orderBy), $limit);
        }

        /**
         * @param array $data
         * @param ?array $cond
         * @param string|array|null $orderBy
         * @param bool $ignore
         * @return bool|int|mysqli_result
         * @throws DBQueryError
         */
        protected function updateOneByCond(array $data,
                                           ?array $cond = null,
                                           string|array|null $orderBy = null,
                                           bool $ignore = false): bool|int|mysqli_result
        {
            return $this->db->update($this->getTableName(),
                $this->buildOne($data),
                $this->buildCond($cond),
                $this->buildOrder($orderBy),
                1,
                $ignore);
        }

        /**
         * @param array $data
         * @param ?array $cond
         * @param string|array|null $orderBy
         * @param int|float|string|array|null $limit
         * @param bool $ignore
         * @return bool|mysqli_result
         * @throws DBQueryError
         */
        protected function updateAll(array $data,
                                     ?array $cond = null,
                                     string|array|null $orderBy = null,
                                     int|float|string|array|null $limit = null,
                                     bool $ignore = false): bool|mysqli_result
        {
            return $this->db->update($this->getTableName(), $this->buildOne($data), $this->buildCond($cond), $this->buildOrder($orderBy), $limit, $ignore);
        }

        /**
         * @param array $data
         * @param array|null $cond
         * @param bool $check Check those condition exist or not
         * @param bool $ignore
         * @return bool|mysqli_result
         * @throws DBQueryError
         */
        protected function addUpdate(array $data,
                                     ?array $cond = null,
                                     bool $check = false,
                                     bool $ignore = false): bool|mysqli_result
        {
            return $this->db->action($this->getTableName(), $this->buildOne($data), $this->buildCond($cond), $check, $ignore);
        }

        /**
         * @param string $queryStr
         * @param int $resultMode
         * @return bool|int|array|mysqli_result
         * @throws DBQueryError
         */
        protected function query(string $queryStr,
                                 int $resultMode = 0): bool|int|array|mysqli_result
        {
            return $this->db->query($queryStr, $resultMode);
        }
    }
}