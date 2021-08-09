<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\InternalError;

    /**
     * Class Lock
     * @package Library
     */
    class Lock
    {
        /**
         * @var string
         */
        private string $password;
        /**
         * @var string
         */
        private string $prefix;

        /**
         * Consistent Lock Constructor
         * @param App $app
         * @throws InternalError
         */
        final public function __construct(private App $app)
        {
            $config = $app->config('General');
            $prefix = $config->get('lockPrefix', 'lock');
            $this->allowSameInstance();
            $this->prefix = !empty($prefix) ? "{$prefix}:" : '';
        }

        /**
         * Lock Type, allow same instance or not.
         * @param bool $allow
         * @return void
         */
        final public function allowSameInstance(bool $allow = true): void
        {
            if ($allow)
                $this->password = Common::getServerIp();
            else
                $this->password = Common::getServerIp() . ":" . hrtime(true);
        }

        /**
         * Setup Lock value, this will block those unknown value ppl
         * @param string $password
         * @return void
         */
        final public function password(string $password = ''): void
        {
            if (!empty($password))
                $this->password = Common::getServerIp() . ":{$password}";
        }

        /**
         * Acquire Lock
         * @param string $name
         * @param int $ttl
         * @return bool|int
         */
        final public function acquire(string $name, int $ttl = 60): bool|int
        {
            $redis = $this->app->redis;
            $name = "{$this->prefix}{$name}";
            if ($redis->get($name) === $this->password)
                $ret = (boolean)$redis->expire($name, $ttl);
            else
                $ret = (boolean)$redis->setnx($name, $this->password, $ttl);
            return $ret;
        }

        /**
         * Acquire and wait it done
         * @param string $name
         * @param int $ttl
         * @param int $maxWait
         * @return boolean
         */
        final public function waitAcquireDone(string $name, int $ttl = 60, int $maxWait = 120): bool
        {
            $start = hrtime(true);
            $success = true;
            $this->app->profiler->enableQuery(false);
            while (!$this->acquire($name, $ttl)) {
                usleep(Common::randomInt(100000, 300000));
                if (floor((hrtime(true) - $start) / 1e+9) > $maxWait) {
                    $success = false;
                    break;
                }
            }
            $this->app->profiler->enableQuery(true);
            return $success;
        }

        /**
         * Release Lock
         * @param $name
         * @param int $delay
         * @return bool
         */
        final public function release($name, int $delay = 0): bool
        {
            $redis = $this->app->redis;
            if (!$redis->exists("{$this->prefix}{$name}"))
                return true;
            return empty($delay) ? (bool)$redis->del("{$this->prefix}{$name}") : (bool)$this->ttl($name, $delay);
        }

        /**
         * @param string $name
         * @param int $ttl
         * @return bool|int
         */
        final public function ttl(string $name, int $ttl = 0): bool|int
        {
            $name = "{$this->prefix}{$name}";
            $redis = $this->app->redis;
            $value = (string)$redis->get($name);
            if ($value === $this->password) {
                if (!empty($ttl) && $redis->expire($name, $ttl))
                    return $ttl;
                else
                    return $redis->ttl($name);
            } else {
                return false;
            }
        }

        /**
         * Acquire Lock set expire time
         * @param string $name
         * @param int|null $ttl
         * @return bool|int
         */
        final public function expire(string $name, ?int $ttl = null): bool|int
        {
            return (int)$this->ttl($name, (int)$ttl);
        }

        /**
         * Extend Lock Ttl
         * @param string $name
         * @param int $ttl
         * @return bool|int
         */
        final public function extend(string $name, int $ttl): bool|int
        {
            $redis = $this->app->redis;
            return (int)$this->ttl($name, (int)$redis->ttl("{$this->prefix}{$name}") + $ttl);
        }
    }
}