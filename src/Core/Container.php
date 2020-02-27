<?php

namespace Npf\Core {

    /**
     * Class Container
     * @package Framework\Core
     */
    class Container
    {
        /**
         * Data Storage
         * @var array
         */
        private $__data = [];

        /**
         * @var bool Lock the content
         */
        private $__lock = FALSE;

        /**
         * Container ID (Prevent Clone in same)
         * @var array
         */
        private $__id = '';

        /**
         * Container ID (Prevent Clone in same)
         * @var array
         */
        private $__firstOnly = false;

        /**
         * Container constructor.
         * @param $data
         * @param bool $lock
         * @param bool $firstOnly
         */
        public function __construct($data = NULL, $lock = FALSE, $firstOnly = false)
        {
            $this->__firstOnly = (bool)$firstOnly;
            $this->__uniqueId();
            $this->__import($data);
            $this->__lock = (bool)$lock;
        }

        /**
         * Generate Unique ID
         * @return string
         */
        final private function __uniqueId()
        {
            return $this->__id = uniqid(uniqid('', TRUE), TRUE);
        }

        /**
         * Import data from a object/array/json
         * @param mixed $data
         * @param bool $notExistsOnly
         * @return bool
         */
        final public function __import($data, $notExistsOnly = false)
        {
            if ($this->__lock || NULL === $data)
                return FALSE;
            if (!empty($data)) {

                switch (gettype($data)) {

                    case 'object':
                        $data = $data instanceof Container ? $data() : (array )$data;
                        foreach ($data as $key => $value)
                            $this->set($key, $value, $notExistsOnly);
                        break;

                    case 'array':
                        foreach ($data as $key => $value)
                            $this->set($key, $value, $notExistsOnly);
                        break;

                    case 'string':
                        $data = json_decode($data, TRUE);
                        if (json_last_error() !== 0)
                            $data = [];
                        foreach ($data as $key => $value)
                            $this->set($key, $value, $notExistsOnly);
                        break;
                }
                return TRUE;
            } else
                return FALSE;
        }

        /**
         * Set data value
         * @param string $name Name
         * @param null $value Value to set
         * @param bool $notExistsOnly set only not exists
         * @return bool
         */
        public function set($name, $value = NULL, $notExistsOnly = false)
        {
            if (($notExistsOnly && !isset($this->__data[$name])) || !$notExistsOnly) {
                return $this->__set($name, $value);
            } else
                return false;
        }

        /**
         * Set State
         * @param array $args
         * @return mixed
         */
        public static function __set_state($args)
        {
            $object = new Container($args['__data'], $args['__lock']);
            return $object;
        }

        public function __destruct()
        {
            $this->__data = NULL;
        }

        /**
         * Check the key is exist or not
         * @param $name
         * @return bool
         */
        public function __isset($name)
        {
            return isset($this->__data[$name]);
        }

        /**
         * Remove a key
         * @param $name
         */
        public function __unset($name)
        {
            if (!$this->__lock && isset($this->__data[$name]))
                unset($this->__data[$name]);
        }

        /**
         * Object invoke, equivalent __get
         * @param string $name key of value, empty will return entire.
         * @return array
         */
        public function __invoke($name = NULL)
        {
            return $name === NULL ? $this->__dump() : $this->__get($name);
        }

        /**
         * Dump a copy
         * @return array
         */
        final protected function __dump()
        {
            $entire = [];
            foreach ($this->__data as $key => $data)
                $entire[$key] = $data instanceof Container ? $data->__dump() : $data;
            return $entire;
        }

        /**
         * Get a data value
         * @param string $name
         * @return mixed
         */
        public function __get($name)
        {
            return $this->get($name, NULL);
        }

        /**
         * Set a value
         * @param $name
         * @param $value
         * @return bool
         */
        public function __set($name, $value)
        {
            if ($this->__lock || NULL === $value)
                return FALSE;
            if (!$this->__firstOnly && is_array($value) && $this->__isAssoc($value))
                $this->__data[$name] = new Container($value);
            else
                $this->__data[$name] = $value;
            return true;
        }

        /**
         * Get a data value
         * @param string $name Name
         * @param mixed $default Default Value if not exists
         * @return mixed
         */
        public function get($name, $default = NULL)
        {
            return $name === '*' ? $this->__data : (isset($this->__data[$name]) ? $this->__data[$name] : $default);
        }

        /**
         * Check array is assoc array or index array
         * @param $arr
         * @return bool
         */
        final private function __isAssoc($arr)
        {
            return array_keys($arr) !== range(0, count($arr) - 1);
        }

        /**
         * WakePp
         * @return bool
         */
        public function __wakeup()
        {
            return TRUE;
        }

        /**
         * Data to string, will convert to json
         * @return string
         */
        public function __toString()
        {
            return json_encode($this->__dump());
        }

        /**
         * Clone new object
         */
        public function __clone()
        {
            $this->__uniqueId();
        }

        /**
         * To call a class method if exit, else return FALSE
         * @param $method
         * @param $arguments
         * @return bool|mixed
         */
        final public function __call($method, $arguments)
        {
            if (isset($this->__data[$method]) && is_callable($this->__data[$method]))
                return call_user_func_array($this->__data[$method], $arguments);
            else
                return FALSE;
        }

        /**
         * To call a class method if exit, else return FALSE
         * @param string $prefix
         * @param string $postfix
         * @return bool|mixed
         */
        final public function flattenData($prefix = '', $postfix = '')
        {
            return $this->__flattenData($this->__data, $prefix, $postfix);
        }

        /**
         * @param $data
         * @param string $prefix
         * @param string $postfix
         * @param string $keyPrefix
         * @return array
         */
        final private function __flattenData($data, $prefix = '', $postfix = '', $keyPrefix = '')
        {
            $result = [];
            foreach ($data as $key => $item) {
                $strKey = $keyPrefix . (empty($keyPrefix) || is_int($key) ? '' : '.') . (is_int($key) ? "[{$key}]" : $key);
                if (is_array($item) || is_object($item)) {
                    $result += $this->__flattenData($item, $prefix, $postfix, $strKey);
                } else {
                    $result["{$prefix}{$strKey}{$postfix}"] = $item;
                }
            }
            return $result;
        }

        /**
         * @param $name
         * @return bool
         */
        public function del($name)
        {
            if (isset($this->__data[$name])) {
                unset($this->__data[$name]);
                return true;
            }
            return false;
        }

        /**
         * @return bool
         */
        public function clear()
        {
            $this->__data = [];
            return true;
        }

        /**
         * Lock setup to lock the content.
         * @param bool $lock
         */
        protected function lock($lock = true)
        {
            $this->__lock = (boolean)$lock;
        }
    }
}