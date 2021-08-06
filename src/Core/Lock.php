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
        private string $uniqueValue;
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
         * 释放锁
         * @param bool $allow
         * @return void
         */
        final public function allowSameInstance(bool $allow = true): void
        {
            if ($allow)
                $this->uniqueValue = Common::getServerIp();
            else
                $this->uniqueValue = Common::getServerIp() . ":" . getmypid();
        }

        /**
         * 释放锁
         * @param $name
         * @param bool $immediately
         * @return int|bool
         */
        final public function release($name, bool $immediately = false): bool|int
        {
            $name = "{$this->prefix}{$name}";
            $redis = $this->app->redis;
            if (!$redis->exists($name))
                return true;
            if ($immediately === true)
                $ret = $redis->del($name);
            else
                $ret = $redis->expire($name, 1);
            return $ret;
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
            if ($value === $this->uniqueValue) {
                if (!empty($ttl) && $redis->expire($name, $ttl))
                    return $ttl;
                else
                    return $redis->ttl($name);
            } else {
                return false;
            }
        }

        /**
         * Wait Acquire Done
         * @param string $name
         * @param int $ttl
         * @param int $maxWait
         * @return boolean
         */
        final public function waitAcquireDone(string $name, int $ttl = 60, int $maxWait = 120): bool
        {
            $start = hrtime(true);
            while (!$this->acquire($name, $ttl)) {
                usleep(Common::randomInt(100000, 300000));
                if (floor((hrtime(true) - $start) / 1e+9) > $maxWait)
                    return false;
            }
            return true;
        }

        /**
         * Acquire Look
         * @param string $name
         * @param int $ttl
         * @return bool|int
         */
        final public function acquire(string $name, int $ttl = 60): bool|int
        {
            $redis = $this->app->redis;
            $name = "{$this->prefix}{$name}";
            if ($redis->get($name) === $this->uniqueValue)
                $ret = (boolean)$redis->expire($name, $ttl);
            else
                $ret = (boolean)$redis->setnx($name, $this->uniqueValue, $ttl);
            return $ret;
        }

        /**
         * Acquire Look
         * @param string $name
         * @param int|null $ttl
         * @return bool|int
         */
        final public function expire(string $name, ?int $ttl = null): bool|int
        {
            $redis = $this->app->redis;
            $name = "{$this->prefix}{$name}";
            $ttl = (int)$ttl;
            if (empty($ttl))
                return (int)$redis->ttl($name);
            else
                return $redis->expire($name, $ttl);
        }

        /**
         * Acquire Look
         * @param string $name
         * @param int $ttl
         * @return bool|int
         */
        final public function extend(string $name, int $ttl): bool|int
        {
            $redis = $this->app->redis;
            $name = "{$this->prefix}{$name}";
            return $redis->expire($name, (int)$redis->ttl($name) + $ttl);
        }
    }
}