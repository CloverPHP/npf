<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\InternalError;
    use SessionHandlerInterface;

    /**
     * Class Session
     * @package Npf\Core
     */
    class Session
    {
        /**
         * @var Container
         */
        private Container $config;

        /**
         * @var bool
         */
        private bool $status;
        /**
         * @var array
         */
        private array $cookieParams;

        /**
         * Session constructor.
         * @param App $app
         * @throws InternalError
         */
        public function __construct(private App $app)
        {
            $this->config = $app->config('Session');
            $this->cookieParams = [
                'lifeTime' => $this->config->get('cookieLifetime', 0),
                'urlPath' => $this->config->get('cookiePath'),
                'domain' => $this->config->get('cookieDomain'),
                'security' => $this->config->get('cookieSecurity', false),
                'httpOnly' => $this->config->get('cookieHttpOnly', true)
            ];
        }

        /**
         * @param int|null $lifeTime
         * @return int
         */
        public function lifeTime(?int $lifeTime = null): int
        {
            if (!$this->status)
                $this->cookieParams['lifeTime'] = (int)$lifeTime;
            return $this->cookieParams['lifeTime'];
        }

        /**
         * @param string|null $urlPath
         * @return mixed
         */
        public function urlPath(?string $urlPath = null): string
        {
            if (!empty($urlPath) && !$this->status)
                $this->cookieParams['urlPath'] = $urlPath;
            return $this->cookieParams['urlPath'];
        }

        /**
         * @param string|null $domain
         * @return string
         */
        public function cookieDomain(?string $domain = null): string
        {
            if (!empty($domain) && !$this->status)
                $this->cookieParams['domain'] = (string)$domain;
            return $this->cookieParams['domain'];
        }

        /**
         * @param bool|null $security
         * @return bool
         */
        public function cookieSecurity(?bool $security = null): bool
        {
            if (!empty($security) && !$this->status)
                $this->cookieParams['security'] = (boolean)$security;
            return $this->cookieParams['security'];
        }

        /**
         * @param bool|null $httpOnly
         * @return bool
         */
        public function cookieHttpOnly(?bool $httpOnly = null): bool
        {
            if (!empty($httpOnly) && !$this->status)
                $this->cookieParams['httpOnly'] = (boolean)$httpOnly;
            return $this->cookieParams['httpOnly'];
        }

        /**
         * Session Get Data
         * @param string|null $name
         * @param null $default
         * @param string $separator
         * @return mixed
         * @throws InternalError
         */
        public function get(?string $name = null, mixed $default = null, string $separator = '.'): mixed
        {
            if (!$this->status)
                $this->start();

            $data = $_SESSION;
            if ($name === null || $name === '*')
                return $data;
            elseif ($separator && strpos($name, $separator)) {
                $parts = explode($separator, $name);
                while ($key = array_shift($parts)) {
                    if (isset($data[$key]))
                        $data = $data[$key];
                    elseif ($key)
                        return $default;
                    else
                        break;
                }
                return !$parts ? $data : $default;
            } else
                return isset($data[$name]) ? $data[$name] : $default;
        }

        /**
         * @return string
         */
        public function id(): string
        {
            return session_id();
        }

        /**
         * Start PHP Session
         * @throws InternalError
         */
        public function start(): bool
        {
            if (!$this->status) {
                if (!$this->config->get('enable', false))
                    return false;
                $driver = "Npf\\Core\\Session\\Session" . $this->config->get('driver', 'Php');
                if ($driver !== 'Npf\\Core\\Session\\SessionPhp') {
                    if (!class_exists($driver))
                        throw new InternalError('Session Driver Not Found.', $driver);
                    $handler = new $driver($this->app, $this->config);
                    if (!$handler instanceof SessionHandlerInterface)
                        throw new InternalError('Session Driver signature is invalid.');
                    session_set_save_handler($handler, true);
                }

                session_set_cookie_params(
                    $this->cookieParams['lifeTime'],
                    $this->cookieParams['urlPath'],
                    $this->cookieParams['domain'],
                    $this->cookieParams['security'],
                    $this->cookieParams['httpOnly']
                );
                $sessionName = $this->config->get('name', 'PHPSESSID');
                session_name($sessionName);

                if ($this->app->request->get('sessionid'))
                    $_COOKIE[$sessionName] = $this->app->request->get('sessionid');

                if (session_start()) {
                    $this->status = true;
                    return true;
                } else
                    throw new InternalError("Unable to start session");
            }
            return false;
        }

        /**
         * Session Set Data
         * @param string $name
         * @param $value
         * @param string $separator
         * @return self
         * @throws InternalError
         */
        public function set(string $name, mixed $value, string $separator = '.'): self
        {
            if (!$this->status)
                $this->start();

            $data = &$_SESSION;
            if ($separator && strpos($name, $separator)) {
                $parts = explode($separator, $name);
                $lastKey = array_pop($parts);
                while ($key = array_shift($parts)) {
                    if (!isset($data[$key]))
                        $data[$key] = [];
                    $data = &$data[$key];
                }
                $data[$lastKey] = $value;
            } else
                $data[$name] = $value;
            return $this;
        }

        /**
         * Session Delete Key
         * @param string $name
         * @param string $separator
         * @return Session
         * @throws InternalError
         */
        public function del(string $name, string $separator = '.'): self
        {
            if (!$this->status)
                $this->start();

            $data = &$_SESSION;
            if ($separator && strpos($name, $separator)) {
                $parts = explode($separator, $name);
                $lastKey = array_pop($parts);
                while ($key = array_shift($parts)) {
                    if (!isset($data[$key]))
                        return $this;
                    $data = &$data[$key];
                }
                unset($data[$lastKey]);
            } else
                unset($data[$name]);
            return $this;
        }

        /**
         * Session Clear Data
         * @return self
         * @throws InternalError
         */
        public function clear(): self
        {
            if (!$this->status)
                $this->start();
            if (isset($_SESSION))
                $_SESSION = [];
            return $this;
        }

        /**
         * Session Clear Data
         * @return self
         */
        public function rollback(): self
        {
            if (!$this->status)
                session_reset();
            return $this;
        }

        /**
         * Session Close
         * @return self
         */
        public function close(): self
        {
            if ($this->status) {
                session_write_close();
                $this->status = false;
            }
            return $this;
        }
    }
}