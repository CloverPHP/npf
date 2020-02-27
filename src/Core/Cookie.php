<?php

namespace Npf\Core {

    /**
     * Class Cookie
     * @package Npf\Core
     */
    class Cookie
    {
        /**
         * @var App
         */
        private $app;

        /**
         * Session constructor.
         * @param App $app
         */
        public function __construct(App $app)
        {
            $this->app = &$app;
        }

        /**
         * Session Get Data
         * @param string $name
         * @param null $default
         * @return mixed|null
         */
        public function get($name = null, $default = null)
        {
            return isset($_COOKIE[$name]) ? $_COOKIE[$name] : $default;
        }

        /**
         * Session Set Data
         * @param $name
         * @param $value
         * @param $expired
         * @param string $path
         * @param string $domain
         * @param bool $secure
         * @param bool $httpOnly
         * @return void
         */
        public function set($name, $value, $expired, $path = '', $domain = '', $secure = false, $httpOnly = false)
        {
            setcookie($name, $value, $expired, $path, $domain, $secure, $httpOnly);
        }

        /**
         * Session Set Data
         * @param $name
         * @param $value
         * @param $expired
         * @param string $path
         * @param string $domain
         * @param bool $secure
         * @param bool $httpOnly
         * @return void
         */
        public function setRaw($name, $value, $expired, $path = '', $domain = '', $secure = false, $httpOnly = false)
        {
            setrawcookie($name, $value, $expired, $path, $domain, $secure, $httpOnly);
        }

        /**
         * Session Clear Data
         */
        public function clear()
        {
            foreach ($_COOKIE as $key => $vale)
                $this->del($key);
        }

        /**
         * Session Delete Key
         * @param string $name
         */
        public function del($name)
        {
            setcookie($name, "", time() - 86400);
        }
    }
}