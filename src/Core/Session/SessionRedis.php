<?php

namespace Npf\Core\Session {

    use Npf\Core\App;
    use Npf\Core\Container;
    use Npf\Exception\InternalError;
    use SessionHandlerInterface;

    /**
     * Class SessionRedis
     * @package Npf\Core\Session
     */
    class SessionRedis implements SessionHandlerInterface
    {
        /**
         * @var App
         */
        private App $app;

        private string $prefix;
        private int $maxWait; //Session blocking maximum waitting time
        private int $lockTtl;
        private int $sessionTtl;
        private string $sessionId = '';
        private string $fingerprint = '';
        /**
         * @var Container
         */
        private Container $config;

        /**
         * Class constructor
         * @param App $app
         * @param Container $config
         */
        public function __construct(App $app, Container &$config)
        {
            $this->app = &$app;
            $this->config = &$config;
            $this->prefix = $config->get('prefix', 'sess');
            $this->maxWait = $config->get('maxWait', 30);
            $this->lockTtl = $config->get('lockTtl', 600);
            $this->sessionTtl = $config->get('sessionTtl', 10800);
        }

        /**
         * Class destructor
         */
        public function __destruct()
        {
            $this->close();
        }

        /**
         * Open
         * Sanitizes save_path and initializes connection.
         * @param string $path Server path
         * @param string $name Session cookie name, unused
         * @return    bool
         */
        public function open($path, $name): bool
        {
            $this->app->lock->allowSameInstance(false);
            return true;
        }

        /**
         * Read
         * Reads session data and acquires a lock
         * @param string $id Session ID
         * @return string Serialized session data
         * @throws InternalError
         */
        public function read($id): string
        {
            if ($this->app->lock->waitAcquireDone("{$this->prefix}:{$id}:lock", $this->lockTtl, $this->maxWait)) {
                $this->sessionId = $id;
                $data = (string)$this->app->redis->get("{$this->prefix}:{$id}");
                $this->app->redis->expire("{$this->prefix}:{$id}", $this->sessionTtl);
                $this->fingerprint = sha1($data);
                return $data;
            } else
                throw new InternalError("Session Lock wait timeout");
        }

        /**
         * Write
         * Writes (create / update) session data
         * @param string $id Session ID
         * @param string $data Serialized session data
         * @return    bool
         */
        public function write($id, $data): bool
        {
            // Was the ID regenerated?
            if ($id !== $this->sessionId) {
                if (!$this->app->lock->release("{$this->prefix}:{$this->sessionId}:lock") || !$this->app->lock->waitAcquireDone("{$this->prefix}:{$id}:lock", $this->lockTtl, $this->maxWait))
                    return false;
                $this->fingerprint = null;
                $this->sessionId = $id;
            }
            $this->app->lock->extend("{$this->prefix}:{$id}:lock", $this->lockTtl);
            $fingerprint = sha1($data);
            if ($this->fingerprint !== $fingerprint) {
                if ($this->app->redis->set("{$this->prefix}:{$id}", $data, $this->sessionTtl)) {
                    $this->fingerprint = $fingerprint;
                    return true;
                }
                return false;
            }
            return (bool)$this->app->redis->expire("{$this->prefix}:{$id}", $this->sessionTtl);
        }

        /**
         * Close
         * Releases locks and closes connection.
         * @return    bool
         */
        public function close(): bool
        {
            return $this->app->lock->release("{$this->prefix}:{$this->sessionId}:lock", true);
        }

        /**
         * Destroy
         * Destroys the current session.
         * @param string $id Session ID
         * @return    bool
         */
        public function destroy($id): bool
        {
            if (($result = $this->app->redis->del("{$this->prefix}:{$id}")) !== 1)
                $this->app->profiler->logInfo('Session', 'Session: Redis::del() expected to return 1, got ' . var_export($result, true) . ' instead.');
            return true;
        }

        /**
         * Garbage Collector
         * Deletes expired sessions
         * @param int $max_lifetime Maximum lifetime of sessions
         * @return    bool
         */
        public function gc($max_lifetime): bool
        {
            return true;
        }
    }
}
