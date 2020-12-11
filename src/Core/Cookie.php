<?php
declare(strict_types=1);

namespace Npf\Core {

    /**
     * Class Cookie
     * @package Npf\Core
     */
    class Cookie
    {
        /**
         * Session constructor.
         * @param App $app
         */
        public function __construct(private App $app)
        {
        }

        /**
         * Session Get Data
         * @param string $name
         * @param null $default
         * @return mixed
         */
        public function get(string $name, mixed $default = null): mixed
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
        public function set(
            string $name,
            mixed $value,
            string|int $expired,
            string $path = '',
            string $domain = '',
            bool $secure = false,
            bool $httpOnly = false)
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
        public function setRaw(
            string $name,
            mixed $value,
            string|int $expired,
            string $path = '',
            string $domain = '',
            bool $secure = false,
            bool $httpOnly = false)
        {
            setrawcookie($name, $value, $expired, $path, $domain, $secure, $httpOnly);
        }

        /**
         * Session Clear Data
         */
        public function clear(): self
        {
            foreach ($_COOKIE as $key => $vale)
                $this->del($key);
            return $this;
        }

        /**
         * Session Delete Key
         * @param string $name
         * @return Cookie
         */
        public function del(string $name): self
        {
            setcookie($name, "", time() - 86400);
            return $this;
        }
    }
}