<?php
declare(strict_types=1);

namespace Npf\Core {

    use JetBrains\PhpStorm\Pure;

    /**
     * Class Container
     * @package Framework\Core
     */
    class Container
    {
        /**
         * Data Storage
         */
        private array $data = [];

        private bool $lock;

        /**
         * Container constructor.
         * @param object|array|null $data
         * @param bool $lock
         * @param bool $__firstOnly
         */
        public function __construct(object|array|null $data = NULL,
                                    bool $lock = FALSE,
                                    private bool $__firstOnly = false)
        {
            $this->import($data);
            $this->lock = $lock;
        }

        /**
         * Import data from a object/array/json
         * @param mixed $data
         * @param bool $notExistsOnly
         * @return Container
         */
        final public function import(object|array|null $data,
                                       bool $notExistsOnly = false): self
        {
            if ($this->lock || NULL === $data)
                return $this;
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
            }
            return $this;
        }

        /**
         * Set data value
         * @param int|string $name Name
         * @param null $value Value to set
         * @param bool $notExistsOnly set only not exists
         * @return Container
         */
        public function set(int|string $name, mixed $value = NULL, bool $notExistsOnly = false): self
        {
            if (($notExistsOnly && !isset($this->data[$name])) || !$notExistsOnly)
                $this->__set($name, $value);
            return $this;
        }

        /**
         * Set State
         * @param array $args
         * @return self
         */
        public static function __set_state(array $args): self
        {
            return new Container($args['__data'], $args['__lock']);
        }

        public function __destruct()
        {
            $this->data = [];
        }

        /**
         * Check the key is exist or not
         * @param string $name
         * @return bool
         */
        public function __isset(string $name): bool
        {
            return isset($this->data[$name]);
        }

        /**
         * Remove a key
         * @param string $name
         */
        public function __unset(string $name): void
        {
            if (!$this->lock && isset($this->data[$name]))
                unset($this->data[$name]);
        }

        /**
         * Object invoke, equivalent __get
         * @param string|null $name key of value, empty will return entire.
         * @return mixed
         */
        public function __invoke(?string $name = NULL): mixed
        {
            return $name === NULL ? $this->__dump() : $this->__get($name);
        }

        /**
         * Dump a copy
         * @return array
         */
        final protected function __dump(): array
        {
            $entire = [];
            foreach ($this->data as $key => $data)
                $entire[$key] = $data instanceof Container ? $data->__dump() : $data;
            return $entire;
        }

        /**
         * Get a data value
         * @param string $name
         * @return mixed
         */
        public function __get(string $name): mixed
        {
            return $this->get($name);
        }

        /**
         * Set a value
         * @param int|string $name
         * @param mixed $value
         */
        public function __set(int|string $name, mixed $value)
        {
            if ($this->lock || NULL === $value)
                return;
            if (!$this->__firstOnly && is_array($value) && $this->__isAssoc($value))
                $this->data[$name] = new Container($value);
            else
                $this->data[$name] = $value;
        }

        /**
         * Get a data value
         * @param string $name Name
         * @param mixed $default Default Value if not exists
         * @return mixed
         */
        public function get(string $name, mixed $default = NULL): mixed
        {
            return $name === '*' ? $this->data : ($this->data[$name] ?? $default);
        }

        /**
         * Check array is assoc array or index array
         * @param array $arr
         * @return bool
         */
        #[Pure] private function __isAssoc(array $arr): bool
        {
            return array_keys($arr) !== range(0, count($arr) - 1);
        }

        /**
         * Data to string, will convert to json
         * @return string
         */
        public function __toString(): string
        {
            return json_encode($this->__dump());
        }

        /**
         * To call a class method if exit, else return FALSE
         * @param string $method
         * @param array $arguments
         * @return mixed
         */
        final public function __call(string $method, array $arguments): mixed
        {
            if (isset($this->data[$method]) && is_callable($this->data[$method]))
                return call_user_func_array($this->data[$method], $arguments);
            else
                return FALSE;
        }

        /**
         * To call a class method if exit, else return FALSE
         * @param string $prefix
         * @param string $postfix
         * @return array
         */
        final public function flattenData(string $prefix = '', string $postfix = ''): array
        {
            return $this->__flattenData($this->data, $prefix, $postfix);
        }

        /**
         * @param mixed $data
         * @param string $prefix
         * @param string $postfix
         * @param string $keyPrefix
         * @return array
         */
        private function __flattenData(mixed $data,
                                       string $prefix = '',
                                       string $postfix = '',
                                       string $keyPrefix = ''): array
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
         * @param string $name
         * @return Container
         */
        public function del(string $name): self
        {
            if (isset($this->data[$name]))
                unset($this->data[$name]);
            return $this;
        }

        /**
         * @return Container
         */
        public function clear(): self
        {
            $this->data = [];
            return $this;
        }

        /**
         * Lock setup to lock the content.
         * @param bool $lock
         * @return Container
         */
        protected function lock(bool $lock = true): self
        {
            $this->lock = $lock;
            return $this;
        }
    }
}