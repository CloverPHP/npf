<?php

namespace Npf\Core {

    use Npf\Core\Db\DbData;
    use Npf\Exception\DBQueryError;
    use Npf\Exception\InternalError;
    use Npf\Exception\UnknownClass;

    /**
     * Class Db
     * @package Core
     */
    final class Db extends DbData
    {
        protected $app;

        /**
         * Db constructor.
         * @param App $app
         * @param Container|null $config
         * @throws DBQueryError
         * @throws InternalError
         * @throws UnknownClass
         */
        public function __construct(App &$app, Container &$config = null)
        {
            $this->app = &$app;
            if ($config === null)
                $config = $app->config('Db');

            parent::__construct($app, $config);

            $this->connect();

            if ($config->get('tran'))
                $this->tranStart();
        }

        /**
         * Connect DB
         * @throws DBQueryError
         */
        private function connect()
        {
            $hosts = $this->config->get('hosts');
            shuffle($hosts);
            foreach ($hosts as $host) {
                $sTime = -$this->app->profiler->elapsed();
                $this->driver->connect($host);
                $this->app->profiler->saveQuery("connect mysql://{$host}", $sTime, "db");
                if ($this->driver->connectErrorNo())
                    $this->connectError($this->driver->connectError());
                elseif ($this->driver->connected === true) {
                    break;
                }
            }

            if ($this->driver->connected !== true)
                throw new DBQueryError("No mysql server avaliable, system exit");
        }

        /**
         * Log connection error.
         * @param $Error
         */
        private function connectError($Error)
        {
            $this->app->profiler->logCritical("DBConnectError", "Error Message: {$Error}");
        }

        /**
         * Reconnect
         * @throws DBQueryError
         */
        public function reconnect()
        {
            $this->close();
            $this->connect();
        }

        final public function __destruct()
        {
            parent::__destruct();
        }

        /**
         * SQL Rollback
         * @return bool
         * @throws DBQueryError
         */
        final public function rollback()
        {
            return $this->driver->rollback();
        }

        /**
         * DB is connected
         * @return bool
         */
        final public function isConnected()
        {
            return $this->driver->isConnected();
        }

        /**
         * SQL Commit
         * @return bool
         * @throws DBQueryError
         */
        final public function commit()
        {
            if ($this->errno() !== 0)
                throw new DBQueryError($this->error());
            return $this->driver->commit();
        }

        /**
         * Get last query string
         * @return string
         */
        public function getLastQuery()
        {
            return $this->driver->lastQuery;
        }

    }

}
