<?php

namespace Npf\Core {
    /**
     * Class Response
     * @package Core
     */
    final class Response extends Container
    {
        /**
         * @var array
         */
        private $headers = [];
        /**
         * @var int Response Http Status Code
         */
        private $statusCode = false;
        /**
         * @var array
         */
        private $initial = [
            'status' => 'ok',
            'error' => '',
            'code' => ''
        ];
        /**
         * @var true
         */
        private $silent = false;

        /**
         * Response constructor
         * @param null $data
         */
        public function __construct($data = NULL)
        {
            if (!is_array($data))
                $data = [];
            $data += $this->initial;
            parent::__construct($data, false, true);
        }

        /**
         * @param $error
         * @param string $desc
         * @param string $code
         */
        public function error($error, $desc = '', $code = '')
        {
            $this->set('status', 'error');
            $this->set('status', (string)$error);
            if ($desc)
                $this->set('profiler', ['desc' => (string)$desc]);
            if ($code)
                $this->set('code', (string)$code);
        }

        /**
         * @param array $data
         */
        public function success(array $data = [])
        {
            $this->set('status', 'ok');
            $this->set('error', null);
            $this->__import($data);
        }

        /**
         * Response constructor
         * @param null $statusCode
         * @return int
         */
        public function statusCode($statusCode = null)
        {
            if (!empty($statusCode)) {
                $statusCode = (int)$statusCode;
                $this->statusCode = $statusCode;
            }
            return $this->statusCode;
        }

        /**
         * Response constructor
         * @param $array
         * @param bool $overwrite
         */
        public function setHeaders($array, $overwrite = false)
        {
            if (!empty($array) && is_array($array)) {
                if (!is_array($this->headers))
                    $this->headers = [];
                foreach ($array as $name => $value)
                    $this->header($name, $value, $overwrite);
            }
        }

        /**
         * Response constructor
         * @return array
         */
        public function getHeaders()
        {
            if (!is_array($this->headers))
                $this->headers = [];
            return $this->headers;
        }

        /**
         * Response constructor
         * @param $name
         * @param $value
         * @param bool $overwrite
         * @return mixed|null
         */
        public function header($name, $value = null, $overwrite = false)
        {
            if (!is_array($this->headers))
                $this->headers = [];
            if (!empty($value) && !empty($name) && is_string($name)) {
                if (!$overwrite && isset($this->headers[$name]))
                    return $this->headers[$name];
                if (is_array($value) || is_object($value))
                    $value = json_encode($value);
                $this->headers[$name] = (string)$value;
            }
            return isset($this->headers[$name]) ? $this->headers[$name] : null;
        }

        /**
         * @return array
         */
        public function fetch()
        {
            return [
                'statusCode' => $this->statusCode,
                'body' => $this->__dump()
            ];
        }

        /**
         * @param $name
         * @param $value
         * @return $this
         */
        public function add($name, $value)
        {
            $data = $this->{$name};
            switch (gettype($data)) {
                case 'integer':
                case 'double':
                    $data += $value;
                    break;
                case 'string':
                    $data .= $value;
                    break;
                case 'array':
                    $data = array_merge($data, $value);
                    break;
                default:
                    $data = $value;
            }
            $this->{$name} = $data;
            return $this;
        }

        /**
         * Change a buffer item
         * @param string $name
         * @param mixed $data
         */
        final public function chg($name, $data)
        {
            if (!empty($name)) {
                $this->{$name} = $data;
            }
        }

        /**
         * Clear buffer
         */
        final public function clear()
        {
            parent::clear();
            $this->__import($this->initial);
        }
    }
}