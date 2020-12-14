<?php
declare(strict_types=1);

namespace Npf\Core {

    use JetBrains\PhpStorm\Pure;
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
        protected Container $config;

        /**
         * Db constructor.
         * @param App $app
         * @param Container|null $config
         * @throws DBQueryError
         * @throws InternalError
         * @throws UnknownClass
         */
        public function __construct(protected App $app, ?Container $config = null)
        {
            if ($config === null)
                $this->config = $app->config('Db');
            else
                $this->config = $config;

            parent::__construct($app, $this->config);

            $this->connect();

            if ($this->config->get('tran'))
                $this->tranStart();
        }

        /**
         * Connect DB
         * @throws DBQueryError
         */
        private function connect(): void
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
                throw new DBQueryError("No mysql server available, system exit");
        }

        /**
         * Log connection error.
         * @param $Error
         */
        private function connectError($Error): void
        {
            $this->app->profiler->logCritical("DBConnectError", "Error Message: {$Error}");
        }

        /**
         * Reconnect
         * @throws DBQueryError
         */
        public function reconnect(): void
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
        final public function rollback(): bool
        {
            return $this->driver->rollback();
        }

        /**
         * DB is connected
         * @return bool
         */
        #[Pure] final public function isConnected(): bool
        {
            return $this->driver->isConnected();
        }

        /**
         * SQL Commit
         * @return bool
         * @throws DBQueryError
         */
        final public function commit(): bool
        {
            if ($this->errno() !== 0)
                throw new DBQueryError($this->error());
            return $this->driver->commit();
        }

        /**
         * Get last query string
         * @return string
         */
        public function getLastQuery(): string
        {
            return $this->driver->lastQuery;
        }

    }

}
