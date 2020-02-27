<?php

namespace Npf\Core {

    use Npf\Exception\InternalError;

    /**
     * Class Lock
     * @package Library
     */
    class Lock
    {
        /**
         * @var App
         */
        private $app;
        /**
         * @var int
         */
        private $uniqueValue;
        /**
         * @var string
         */
        private $prefix = '';

        /**
         * Consistent Lock Constructor
         * @param App $app
         * @throws InternalError
         */
        final public function __construct(App &$app)
        {
            $this->app = &$app;
            $config = $app->config('General');
            $prefix = $config->get('lockPrefix', 'lock');
            $this->uniqueValue = Common::getServerIp() . ":" . getmypid();
            $this->prefix = !empty($prefix) ? "{$prefix}:" : '';
        }

        /**
         * 释放锁
         * @param $allow
         * @return void
         */
        final public function allowSameInstance($allow = true)
        {
            if ($allow)
                $this->uniqueValue = Common::getServerIp() . ":" . getmypid();
            else
                $this->uniqueValue = Common::getServerIp() . ":" . getmypid() . ":" . floor(Common::timestamp() * 1000000);
        }

        /**
         * 释放锁
         * @param $name
         * @param bool $immediately
         * @return bool
         */
        final public function release($name, $immediately = false)
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
         * @param $name
         * @param int $ttl
         * @return bool|int
         */
        final public function ttl($name, $ttl = 0)
        {
            $name = "{$this->prefix}{$name}";
            $redis = $this->app->redis;
            $value = (int)$redis->get($name);
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
         * @param $name
         * @param int $ttl
         * @param int $maxWait
         * @return boolean
         */
        final public function waitAcquireDone($name, $ttl = 60, $maxWait = 120)
        {
            $start = -1 * (int)microtime(true);
            while (!$this->acquire($name, $ttl)) {
                usleep(Common::randomInt(300000, 1000000));
                if ((int)microtime(true) + $start > (int)$maxWait)
                    return false;
            }
            return true;
        }

        /**
         * Acquire Look
         * @param $name
         * @param int $ttl
         * @return bool
         */
        final public function acquire($name, $ttl = 60)
        {
            usleep(Common::randomInt(10000, 300000));
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
         * @param $name
         * @param int $ttl
         * @return bool
         */
        final public function expire($name, $ttl = null)
        {
            $redis = $this->app->redis;
            $name = "{$this->prefix}{$name}";
            $ttl = (int)$ttl;
            if (empty($ttl))
                return (int)$redis->ttl($name);
            else
                return $redis->expire($name, (int)$ttl);
        }

        /**
         * Acquire Look
         * @param $name
         * @param int $ttl
         * @return bool
         */
        final public function extend($name, $ttl)
        {
            $redis = $this->app->redis;
            $name = "{$this->prefix}{$name}";
            $ttl = (int)$ttl;
            return $redis->expire($name, (int)$redis->ttl($name) + $ttl);
        }
    }
}