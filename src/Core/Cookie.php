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
         * Session Get Data
         * @param string $name
         * @param null $default
         * @return mixed
         */
        public function get(string $name, mixed $default = null): mixed
        {
            return $_COOKIE[$name] ?? $default;
        }

        /**
         * Session Set Data
         * @param string $name
         * @param mixed $value
         * @param string|int $expire
         * @param string $path
         * @param string $domain
         * @param bool $secure
         * @param bool $httpOnly
         * @param string $samesite
         * @return void
         */
        public function set(
            string $name,
            mixed $value,
            string|int $expire,
            string $path = '',
            string $domain = '',
            bool $secure = false,
            bool $httpOnly = false,
            string $samesite = 'None'
        )
        {
            setcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => $samesite,
                'secure' => $secure,
                'httponly' => $httpOnly,
            ]);
        }

        /**
         * Session Set Data
         * @param string $name
         * @param mixed $value
         * @param string|int $expire
         * @param string $path
         * @param string $domain
         * @param bool $secure
         * @param bool $httpOnly
         * @param string $samesite
         * @return void
         */
        public function setRaw(
            string $name,
            mixed $value,
            string|int $expire,
            string $path = '',
            string $domain = '',
            bool $secure = false,
            bool $httpOnly = false,
            string $samesite = 'None')
        {
            setrawcookie($name, $value, [
                'expires' => $expire,
                'path' => $path,
                'domain' => $domain,
                'samesite' => $samesite,
                'secure' => $secure,
                'httponly' => $httpOnly,
            ]);
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