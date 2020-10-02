<?php

namespace Npf\Core {

    use Npf\Exception\InvalidParams;


    /**
     * Class Request
     * @package Core
     */
    final class Request extends Container
    {
        /**
         * @var array
         */
        private $headers = [];
        /**
         * @var string Uri
         */
        private $uri = '';
        /**
         * @var string Uri
         */
        private $pathInfo = '';
        /**
         * @var App
         */
        private $app = '';

        private $raw = '';

        private $contentType = 'COMMAND';

        private $method = 'RUN';

        private $protocol = '';

        /**
         * Request constructor.
         * @param App $app
         * @param array|NULL $data
         * @param bool $lock
         */
        final public function __construct(App &$app, array $data = NULL, $lock = FALSE)
        {
            $this->app = &$app;
            $this->initialRequest();
            if (!$data) {
                $data = $this->getRequestParams();
            }
            parent::__construct($data, $lock, true);
        }

        /**
         * Initial Request
         */
        final private function initialRequest()
        {
            $this->contentType = explode(";", isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'COMMAND', 2)[0];
            $this->method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '__RUN__';
            if ($this->method !== '__RUN__') {
                $this->initHeader();
                $this->protocol = $this->isSecure() ? 'https' : 'http';
            }
        }

        /**
         * Retrieve Header from $_SERVER
         */
        final private function initHeader()
        {
            $this->headers = [];
            foreach ($_SERVER as $name => $value)
                if (substr($name, 0, 5) === 'HTTP_')
                    $this->headers[strtolower(str_replace("HTTP_", "", $name))] = $value;
        }

        /**
         * Check is secure
         * @return bool
         */
        final public function isSecure()
        {
            if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] == 'on')
                return true;
            elseif (
                !empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https' ||
                !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] == 'on')
                return true;
            return false;
        }

        /**
         * 默认获取params方式(http post json)
         * @return array
         */
        final private function getRequestParams()
        {
            $this->raw = file_get_contents('php://input');
            switch ($this->method) {

                case '__RUN__':
                    $params = [];
                    $options = getopt("r:", ["run:"]);
                    if (is_array($options) && !empty($options)) {
                        $options = explode("?", reset($options));
                        $_SERVER['REQUEST_URI'] = $options[0];
                        if (isset($options[1]))
                            parse_str($options[1], $params);
                    }
                    return $params;

                default:
                    switch ($this->contentType) {

                        case 'multipart/form-data':
                            $params = $_REQUEST;
                            if (!empty($_FILES) && count($_FILES))
                                $params = array_merge($params, $_FILES);
                            return $params;

                        case 'application/x-www-form-urlencoded':
                            return $_REQUEST;

                        case 'application/json':
                        case 'text/json':
                            $params = json_decode($this->raw, true);
                            if (json_last_error() !== JSON_ERROR_NONE || $params === null) {
                                $params = [];
                                if ($this->raw !== '') {
                                    $params = [
                                        'data' => $this->raw,
                                        'msg' => 'Invalid JSON',
                                    ];
                                }
                            }
                            if (!is_array($params))
                                $params = [];
                            return array_merge($_GET, $params);
                            break;

                        case 'application/xml':
                        case 'text/xml':
                            $defaultXmlError = libxml_use_internal_errors(true);
                            if ($xml = simplexml_load_string($this->raw, "SimpleXMLElement", LIBXML_NOCDATA)) {
                                $json = json_encode($xml);
                                $params = json_decode($json, TRUE);
                            } else {
                                $params = [];
                                if ($this->raw !== '') {
                                    $params = [
                                        'data' => $this->raw,
                                        'msg' => 'Invalid XML',
                                    ];
                                }
                            }
                            libxml_use_internal_errors($defaultXmlError);
                            if (!is_array($params))
                                $params = [];
                            return array_merge($_GET, $params);
                            break;

                        default:
                            return $_GET;
                    }
            }
        }

        /**
         * Get Protocol
         * @return bool
         */
        final public function getProtocol()
        {
            return $this->protocol;
        }

        /**
         * Return Request Method
         * @return string
         */
        final public function getMethod()
        {
            return $this->method;
        }

        /**
         * Return Request Method
         * @return string
         */
        final public function getContentType()
        {
            return $this->contentType;
        }

        /**
         * Return Uri
         * @return mixed|null
         */
        final public function getUri()
        {
            return $this->uri;
        }

        /**
         * Set Uri
         * @param $Uri
         * @return Request
         */
        final public function setUri($Uri)
        {
            $this->uri = $Uri;
            return $this;
        }

        /**
         * Return Uri
         * @return mixed|null
         */
        final public function getPathInfo()
        {
            return $this->pathInfo;
        }

        /**
         * Set Uri
         * @param $pathInfo
         * @return Request
         */
        final public function setPathInfo($pathInfo)
        {
            $this->pathInfo = $pathInfo;
            return $this;
        }

        /**
         * Get Raw Data
         * @return string
         */
        final public function getRaw()
        {
            return $this->raw;
        }

        /**
         * @param $name
         * @param mixed $default
         * @return mixed|null
         */
        final public function header($name, $default = null)
        {
            $name = strtolower($name);
            if ($name === '*')
                return $this->headers;
            else
                return isset($this->headers[$name]) ? $this->headers[$name] : $default;
        }

        /**
         * Set request header
         *
         * @param string $name
         * @param mixed $value Value
         * @return Request
         */
        public function setHeader($name, $value)
        {
            $name = strtolower($name);
            $this->headers[$name] = $value;
            return $this;
        }

        /**
         * @param array $requests
         * @param array $headers
         * @param bool $notExists
         * @return Request
         */
        final public function addRequest($requests = [], $headers = [], $notExists = false)
        {
            if (!empty($requests) && is_array($requests))
                $this->__import($requests, $notExists);
            if (!empty($headers) && is_array($headers))
                foreach ($headers as $name => $value) {
                    $name = strtolower($name);
                    if ($notExists && isset($this->headers[$name]))
                        continue;
                    $this->headers[$name] = $value;
                }
            return $this;
        }

        /**
         * Validate all the parameter accordingly requirement
         * @param string|int|array $patterns
         * @param array|Container $data
         * @throws InvalidParams
         */
        final public function validate($patterns, $data = null)
        {
            if ($data instanceof Container)
                $data = $data();
            if (!is_array($data))
                $data = $this->__dump();
            $needed = Common::validator($patterns, $data);
            reset($needed);
            $code = key($needed);
            if (is_array($needed) && !empty($needed))
                throw new InvalidParams("Invalid Parameter, for more info, please refer 'tips'.", $code, 'error', ['tips' => $needed]);
        }

        /**
         * @return bool
         */
        final public function isXHR()
        {
            $method = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtoupper($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
            return ($method === 'XMLHTTPREQUEST');
        }
    }
}