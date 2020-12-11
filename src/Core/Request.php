<?php
declare(strict_types=1);

namespace Npf\Core {

    use JetBrains\PhpStorm\Pure;
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
        private array $headers = [];
        /**
         * @var string Uri
         */
        private string $uri = '';
        /**
         * @var string Uri
         */
        private string $pathInfo = '';

        private string $raw = '';

        private string $contentType = 'COMMAND';

        private string $method = 'RUN';

        private string $schema = 'cmd';

        private string $fullRequestUri = '';

        /**
         * Request constructor.
         * @param App $app
         * @param array|NULL $data
         * @param bool $lock
         */
        final public function __construct(private App &$app, array $data = NULL, bool $lock = FALSE)
        {
            $this->initialRequest();
            if (!$data)
                $data = $this->getRequestParams();
            parent::__construct($data, $lock, true);
        }

        /**
         * Initial Request
         */
        private function initialRequest(): void
        {
            $this->contentType = explode(";", isset($_SERVER['CONTENT_TYPE']) ? $_SERVER['CONTENT_TYPE'] : 'COMMAND', 2)[0];
            $this->method = isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : '__RUN__';
            if ($this->method !== '__RUN__') {
                $this->initHeader();
                $this->schema = $this->isSecure() ? 'https' : 'http';
            }
        }

        /**
         * Retrieve Header from $_SERVER
         */
        private function initHeader(): void
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
        final public function isSecure(): bool
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
        private function getRequestParams(): array
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

                        default:
                            return $_GET;
                    }
            }
        }

        /**
         * Get Protocol
         * @return string
         */
        final public function getProtocol(): string
        {
            return $this->schema;
        }

        /**
         * Get Protocol
         * @return string
         */
        final public function getSchema(): string
        {
            return $this->schema;
        }

        /**
         * Return Request Method
         * @return string
         */
        final public function getMethod(): string
        {
            return $this->method;
        }

        /**
         * Return Request Method
         * @return string
         */
        final public function getContentType(): string
        {
            return $this->contentType;
        }

        /**
         * Return Uri
         * @return string
         */
        final public function getFullRequestUri(): string
        {
            if (empty($this->fullRequestUri))
                $this->fullRequestUri = "{$this->getSchema()}://{$this->header('host')}{$this->pathInfo}";
            return $this->fullRequestUri;
        }

        /**
         * Return Uri
         * @return string
         */
        final public function getUri(): string
        {
            return $this->uri;
        }

        /**
         * Set Uri
         * @param $uri
         * @return self
         */
        final public function setUri(string $uri): self
        {
            $this->uri = $uri;
            return $this;
        }

        /**
         * Return Uri
         * @return string
         */
        final public function getPathInfo(): string
        {
            return $this->pathInfo;
        }

        /**
         * Set Uri
         * @param $pathInfo
         * @return self
         */
        final public function setPathInfo(string $pathInfo): self
        {
            $this->pathInfo = $pathInfo;
            return $this;
        }

        /**
         * Get Raw Data
         * @return string
         */
        final public function getRaw(): string
        {
            return $this->raw;
        }

        /**
         * @param string $name
         * @param mixed $default
         * @return mixed
         */
        #[Pure] final public function header(string $name, mixed $default = null): mixed
        {
            $name = strtolower($name);
            if ($name === '*')
                return $this->headers;
            else
                return isset($this->headers[$name]) ? $this->headers[$name] : $default;
        }

        /**
         * Set request header
         * @param string $name
         * @param mixed $value Value
         * @return self
         */
        public function setHeader(string $name, mixed $value): self
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
        final public function addRequest(array $requests = [],
                                         array $headers = [],
                                         bool $notExists = false): self
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
         * @param string|array $patterns
         * @param array|Container|null $data
         * @throws InvalidParams
         */
        final public function validate(string|array $patterns, array|Container|null $data = null): void
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
        final public function isXHR(): bool
        {
            $method = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) ? strtoupper($_SERVER['HTTP_X_REQUESTED_WITH']) : '';
            return ($method === 'XMLHTTPREQUEST');
        }
    }
}