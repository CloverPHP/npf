<?php
declare(strict_types=1);

namespace Npf\Library;

use CurlHandle;
use finfo;
use Npf\Exception\InternalError;

/**
 * Class Rpc
 * Enhanced curl and make more easy to use
 */
final class Rpc
{
    /**
     * @var string Url
     */
    private string $url = '';

    /**
     * @var bool Enable Verbose Debug
     */
    private bool $verboseDebug = false;

    /**
     * @var string CURL Verbose Debug Log
     */
    private string $verboseDebugLog = '';

    /**
     * @var int Request Timeout
     */
    private int $timeout = 30;

    /**
     * @var int Request Timeout in ms
     */
    private int $timeoutMS = 0;

    /**
     * @var int Request Timeout
     */
    private int $connectTimeout = 30;

    /**
     * @var int Request Connection Port
     */
    private int $port = 0;

    /**
     * @var array Request Header
     */
    private array $headers = ['Expect' => ''];

    /**
     * @var array Basic Auth
     */
    private array $basicAuth = [];

    /**
     * @var array Get/Post Parameters
     */
    private array $params = [];

    /**
     * @var array Get/Post Binding Parameters
     */
    private array $bindingParams = [];

    /**
     * @var array Cookie
     */
    private array $cookie = [];

    /**
     * @var string Body Content
     */
    private string $content = '';

    /**
     * @var string Request Method : Post/Get/Put/HTTP2.0 Custom method
     */
    private string $method = 'GET';

    /**
     * @var array Available Method
     */
    private array $availableMethod = ['GET', 'POST', 'PUT', 'HEADER'];

    /**
     * @var string User Agent Simulation
     */
    private string $userAgent = 'Mozilla/5.0 (Windows NT 10.0) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/83.0.4103.116 Safari/537.36'; //Simulation Google Chrome

    /**
     * @var array Response Data
     */
    private array $response = [];

    /**
     * @var ?array Request Proxy
     */
    private ?array $proxy = null;

    /**
     * @var string Internal Cookie Handle
     */
    private string $internalCookie = '';

    /**
     * @var bool Flag is have file upload
     */
    private bool $fileUpload = false;

    /**
     * @var array Curl additional option
     */
    private array $curlOpt = [];

    /**
     * @var string CA Cert File Path to load
     */
    private string $CACert = '';

    /**
     * @var bool
     */
    private bool $followLocation = true;
    /**
     * @var CurlHandle
     */
    private mixed $handle;

    private array $rpcThread = [];

    /**
     * Rpc constructor.
     * @throws InternalError
     */
    final public function __construct()
    {
        if (!extension_loaded('curl'))
            throw new InternalError('Curl extension is not loaded');
    }

    /**
     * Set Connection Timeout
     * @param int $connectionTimeout
     * @return self
     */
    final public function setConnectionTimeout(int $connectionTimeout = 0): self
    {
        if ($connectionTimeout > 0)
            $this->connectTimeout = $connectionTimeout;
        return $this;
    }

    /**
     * Rpc constructor.
     * @param int $timeout
     * @param int $timeoutMS
     * @return self
     */
    final public function setTimeout(int $timeout = 30, int $timeoutMS = 0): self
    {
        if ($timeout > 0)
            $this->timeout = $timeout;
        if ($timeoutMS > 0)
            $this->timeoutMS = $timeoutMS;
        return $this;
    }

    /**
     * Auto Follow Location
     * @param null|boolean $enable
     * @return bool
     */
    final public function autoFollowLocation(?bool $enable = null): bool
    {
        if ($enable !== null) {
            $this->followLocation = $enable;
            return $enable;
        } else
            return $this->followLocation;
    }

    /**
     * Get User Agent
     * @return string
     */
    final public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    /**
     * Set User Agent
     * @param string $userAgent
     * @return self
     */
    final public function setUserAgent(string $userAgent): self
    {
        if (is_string($userAgent) && !empty($userAgent))
            $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Set Request Connection Port
     * @param int $port
     * @return self
     */
    final public function setPort(int $port = 0): self
    {
        if (is_int($port) && !empty($port))
            $this->port = $port;
        return $this;
    }

    /**
     * Set Request Basic Auth
     * @param string $user Proxy Auth User
     * @param string $pass Proxy Auth Pass
     * @param string $type
     * @return self
     */
    final public function setBasicAuth(string $user, string $pass, string $type = 'any'): self
    {
        if (is_string($user) && !empty($user) && is_string($pass)) {
            $this->basicAuth['userpwd'] = "{$user}:{$pass}";
            $this->basicAuth['type'] = $type;
        }
        return $this;
    }

    /**
     * Set Request Proxy
     * @param string $proxyAddress Proxy address, include port e.g. 192.168.1.1:8080
     * @param string $user Proxy Auth User
     * @param string $pass Proxy Auth Pass
     * @param string $serviceName
     * @param ?array $header
     * @param int $socketType
     * @return self
     */
    final public function setProxy(string $proxyAddress,
                                   string $user = '',
                                   string $pass = '',
                                   string $serviceName = '',
                                   ?array $header = null,
                                   int $socketType = 0): self
    {
        if (is_string($proxyAddress) && !empty($proxyAddress)) {
            $this->proxy['proxy'] = $proxyAddress;
            if (is_string($user) && !empty($user) && is_string($pass))
                $this->proxy['auth'] = "{$user}:{$pass}";
            if (is_array($header) && !empty($socketType))
                $this->proxy['header'] = $header;
            if (is_string($serviceName) && !empty($serviceName))
                $this->proxy['service'] = $serviceName;
            if (is_int($socketType) && !empty($socketType))
                $this->proxy['sockettype'] = $socketType;
        }
        return $this;
    }

    /**
     * Set Request Body Content
     * @param string $cert File
     * @return self
     */
    final public function setCACert(string $cert): self
    {
        $cert = realpath($cert);
        if (is_string($cert) && !empty($cert) && file_exists($cert))
            $this->CACert = $cert;
        return $this;
    }

    /**
     * Add A GET/POST param
     * @param string $name
     * @param mixed $value
     * @return self
     */
    final public function bindingParam(string $name, mixed $value): self
    {
        if (!empty($name) && (is_string($value) || is_numeric($value)))
            $this->bindingParams[$name] = $value;
        return $this;
    }

    /**
     * Add A GET/POST param
     * @param string $name
     * @param mixed $value
     * @return self
     */
    final public function bindingParams(string $name, mixed $value): self
    {
        if (!empty($name) && (is_string($value) || is_numeric($value)))
            $this->bindingParams[$name] = $value;
        return $this;
    }

    /**
     * Add Multiple Get/Set Params
     * @param string $name
     * @param string $fileName
     * @param ?string $contentType
     * @return self
     * @internal param array $values
     */
    final public function addFile(string $name, string $fileName, ?string $contentType = null): self
    {
        if (is_string($name) && !empty($name) && is_string($fileName) && !empty($fileName) && file_exists($fileName) && !is_dir($fileName)) {
            if (empty($contentType)) {
                $fifo = new finfo(FILEINFO_MIME);
                $contentType = $fifo->file($fileName);
            }
            if (function_exists('curl_file_create'))
                $cFile = curl_file_create($fileName, $contentType, basename($fileName));
            else
                $cFile = "@{$fileName};filename=" . basename($fileName) . ($contentType ? ";type=$contentType" : '');
            $this->fileUpload = true;
            $this->params[$name] = $cFile;
        }
        return $this;
    }

    /**
     * Add Cookie to response
     * @param string $name
     * @param string $content
     * @return self
     */
    final public function addCookie(string $name, string $content): self
    {
        if (is_string($name) && !empty($name) && is_string($content) && !empty($content))
            $this->cookie[$name] = $content;
        return $this;
    }

    /**
     * Add Cookie to response
     * @param array $cookies
     * @return self
     */
    final public function addCookies(array $cookies): self
    {
        if (is_array($cookies) && !empty($cookies))
            $this->cookie = array_merge($this->cookie, $cookies);
        return $this;
    }

    /**
     * Add a curl option
     * @param int $optId
     * @param int|float|bool|string|array $value
     * @return self
     */
    final public function addOption(int $optId, int|float|bool|string|array $value): self
    {
        if (!empty($optId) && is_int($optId))
            $this->curlOpt[$optId] = $value;
        return $this;
    }

    /**
     * Add multiple request header
     * @param array $options
     * @return self
     */
    final public function addOptions(array $options): self
    {
        if (is_array($options) && !empty($options))
            $this->curlOpt += $options;
        return $this;
    }

    /**
     * Added a request header
     * @param string $name
     * @param string $content
     * @return Rpc
     */
    final public function addHeader(string $name, string $content): self
    {
        if (is_string($name) && !empty($name) && is_string($content) && !empty($content))
            $this->headers[$name] = $content;
        return $this;
    }

    /**
     * Add multiple request header
     * @param array $headers
     * @return self
     */
    final public function addHeaders(array $headers): self
    {
        if (is_array($headers) && !empty($headers))
            $this->headers = array_merge($this->headers, $headers);
        return $this;
    }

    /**
     * Add A GET/POST param
     * @param string $name
     * @param string $value
     * @return self
     */
    final public function addParam(string $name, string $value): self
    {
        if (!empty($name) && (is_string($value) || is_numeric($value))) {
            $this->params[$name] = $value;
            $this->content = '';
        }
        return $this;
    }

    /**
     * Add Multiple Get/Set Params
     * @param array $values
     */
    final public function addParams(array $values)
    {
        if (is_array($values) && !empty($values)) {
            $this->params = array_merge($this->params, $values);
            $this->content = '';
        }
    }

    /**
     * Get Response Header
     * @return array
     */
    final public function getResponse(): array
    {
        return $this->response;
    }

    /**
     * Get Response Header
     * @param string $name
     * @return ?string
     */
    final public function getResponseHeader(string $name): ?string
    {
        if (is_string($name) && !empty($name)) {
            if ($name === '*')
                return $this->response['header'];
            else
                return $this->response['header'][$name] ?? null;
        } else
            return null;
    }

    /**
     * Get Response Cookie
     * @param string $name
     * @return ?string
     */
    final public function getResponseCookie(string $name): ?string
    {
        if (is_string($name) && !empty($name)) {
            if ($name === '*')
                return $this->response['cookie'];
            else
                return $this->response['cookie'][$name] ?? null;
        } else
            return null;
    }

    /**
     * Get Response Body Content
     * @return string
     */
    final public function getResponseContent(): string
    {
        return $this->response['body'];
    }

    /**
     * Get Response Status Code
     * @return string
     */
    final public function getResponseStatus(): string
    {
        return $this->response['status'];
    }

    /**
     * Get Response Status Code
     * @return int
     */
    final public function getResponseStatusCode(): int
    {
        return (int)$this->response['code'];
    }

    /**
     * Process Response Header
     * @param CurlHandle $ch
     * @param string $headerLine
     * @return int
     */
    final public function processResponseHeader(mixed $ch, string $headerLine): int
    {
        $matches = [];
        if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $matches) == 1) {
            $cookie = [];
            parse_str($matches[1], $cookie);
            $this->response['cookie'] = array_merge($this->response['cookie'], $cookie);
        } else {
            if (substr($headerLine, 0, 4) === 'HTTP') {
                list($this->response['http'], $this->response['code'], $this->response['status']) = explode(" ", $headerLine, 3);
                $this->response['status'] = trim($this->response['status']);
            } elseif (trim($headerLine) !== '') {
                list($name, $value) = explode(":", $headerLine, 2);
                $this->response['header'][trim($name)] = trim($value);
            }
        }
        return strlen($headerLine);
    }

    /**
     * Process Response Header
     * @param string $cookieData
     */
    final public function importCookieData(string $cookieData)
    {
        $this->internalCookie = $cookieData;
    }

    /**
     * Process Response Header
     * @return string
     */
    final public function exportCookieData(): string
    {
        return $this->internalCookie;
    }

    /**
     * For public to execute
     * @return string
     */
    final public function execute(): string
    {
        return $this->_execute();
    }

    /**
     * @param bool $fresh
     * @return false|resource
     */
    final public function createHandle(bool $fresh = false): CurlHandle|bool
    {
        //Prepare Data
        $this->clearResponse();
        $this->processRequestContent();

        $this->handle = curl_init($this->url);
        //Prepare CURL
        $this->curlOpt = [
                CURLOPT_FOLLOWLOCATION => TRUE,
                CURLOPT_AUTOREFERER => $this->followLocation,
                CURLOPT_MAXREDIRS => 100,
                CURLOPT_URL => $this->url,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_NONE,
                CURLOPT_CUSTOMREQUEST => $this->method,
                CURLOPT_TIMEOUT_MS => ($this->timeout * 1000) + $this->timeoutMS,
                CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
                CURLOPT_USERAGENT => $this->userAgent,
                CURLOPT_ENCODING => 'deflate,gzip',
                CURLOPT_RETURNTRANSFER => TRUE,
                CURLOPT_SSL_VERIFYHOST => FALSE,
                CURLOPT_SSL_VERIFYPEER => FALSE,
                CURLOPT_NOSIGNAL => FALSE,
                CURLOPT_HEADERFUNCTION => [$this, 'processResponseHeader'],
                CURLOPT_HTTPHEADER => $this->processRequestHeader(),
                CURLOPT_COOKIESESSION => FALSE,
                CURLOPT_VERBOSE => $this->verboseDebug,
            ] + $this->curlOpt;
        if (($this->timeout * 1000) + $this->timeoutMS < 1000)
            $this->curlOpt[CURLOPT_NOSIGNAL] = TRUE;
        if ($fresh) {
            $this->curlOpt[CURLOPT_FORBID_REUSE] = TRUE;
            $this->curlOpt[CURLOPT_FRESH_CONNECT] = TRUE;
        }
        if ($this->port > 0)
            $this->curlOpt[CURLOPT_PORT] = $this->port;
        if (!empty($this->CACert))
            $this->curlOpt[CURLOPT_CAINFO] = $this->CACert;

        //Setup Curl Option
        curl_setopt_array($this->handle, $this->curlOpt);

        if ($this->method !== 'GET') {
            curl_setopt_array($this->handle, [
                CURLOPT_POST => TRUE,
                CURLOPT_POSTFIELDS => $this->content,
            ]);
        }
        $this->processRequestProxy($this->handle);
        $this->processRequestBasicAuth($this->handle);
        $this->processRequestCookie($this->handle);
        return $this->handle;
    }

    /**
     * @return mixed
     */
    final public function getHandle(): mixed
    {
        return $this->handle;
    }

    /**
     * Execute a request, & process response
     * @param mixed $outputHandle
     * @return mixed
     */
    private function _execute(mixed $outputHandle = null): mixed
    {
        $this->createHandle();

        //Prepare Cookie Data
        $tmpCookie = tempnam(sys_get_temp_dir(), 'library.class.rpc');
        if (!empty($this->internalCookie))
            file_put_contents($tmpCookie, $this->internalCookie);

        //Prepare CURL
        $this->curlOpt = [
                CURLOPT_COOKIEJAR => $tmpCookie,
                CURLOPT_COOKIEFILE => $tmpCookie,
                CURLOPT_VERBOSE => $this->verboseDebug,
            ] + $this->curlOpt;

        //Execute CURL Request & Getting Returning Response
        $outputReturn = true;
        if (is_resource($outputHandle) && get_resource_type($outputHandle) === 'stream') {
            curl_setopt($this->handle, CURLOPT_FILE, $outputHandle);
            $outputReturn = false;
        }

        //Record Verbose Log
        $verbose = null;
        if ($this->verboseDebug) {
            $verbose = fopen('php://temp', 'w+');
            curl_setopt($this->handle, CURLOPT_STDERR, $verbose);
            $this->verboseDebugLog = '';
        }

        $this->response['body'] = curl_exec($this->handle);
        if ($this->response['body'] === false) {
            $this->response['error'] = curl_error($this->handle);
            $this->response['errno'] = curl_errno($this->handle);
        } elseif (!$outputReturn) {
            $this->response['body'] = $outputHandle;
        }
        //Statistics Request Time
        $this->response['time'] = [
            'total' => (curl_getinfo($this->handle, CURLINFO_TOTAL_TIME) * 1000) . " ms",
            'connect' => (curl_getinfo($this->handle, CURLINFO_CONNECT_TIME) * 1000) . " ms",
            'nslookup' => (curl_getinfo($this->handle, CURLINFO_NAMELOOKUP_TIME) * 1000) . " ms",
        ];

        //Close Curl
        curl_close($this->handle);

        //Retrieve Verbose Log
        if ($this->verboseDebug) {
            rewind($verbose);
            $this->verboseDebugLog = stream_get_contents($verbose);
        }

        #Export Cookie Jar to get the cookie
        $this->internalCookie = file_get_contents($tmpCookie);

        //Clear Cooke File
        unlink($tmpCookie);
        $this->clearRequest();
        return $this->response['body'];
    }

    /**
     * Clear Response
     */
    private function closeHandle()
    {
        if (!empty($this->handle) && is_resource($this->handle))
            curl_close($this->handle);
        $this->handle = null;
    }

    /**
     * Clear Response
     */
    private function clearResponse(): void
    {
        $this->response = [
            'errno' => 0,
            'error' => '',
            'http' => 0,
            'code' => 0,
            'status' => 0,
            'header' => [],
            'cookie' => [],
            'time' => [],
            'body' => '',
        ];
    }

    /**
     * Process Request Body
     */
    private function processRequestContent()
    {
        if ($this->method === 'GET') {
            if (is_array($this->params) && !empty($this->params)) {
                $content = http_build_query($this->params);
                $this->url .= (!str_contains($this->url, "?") ? "?" : "&") . $content;
            }
            $this->content = '';
            $this->params = [];
        } elseif (is_array($this->params) && !empty($this->params) && empty($this->content)) {
            if (!$this->fileUpload)
                $this->addHeader("Content-Type", "application/x-www-form-urlencoded");
            if (is_array($this->params))
                $this->content = http_build_query(array_merge($this->params, $this->bindingParams));
            $this->params = [];
        }
    }

    /**
     * Process Request Body
     * @return array
     */
    private function processRequestHeader(): array
    {
        $result = [];
        if (is_array($this->headers) && !empty($this->headers)) {
            foreach ($this->headers as $key => $value)
                $result[] = "{$key}: {$value}";
        }
        $this->headers = ['Expect' => ''];
        return $result;
    }

    /**
     * Process Request Proxy
     * @param CurlHandle $cHandle
     */
    private function processRequestProxy(mixed $cHandle)
    {
        if (is_array($this->proxy) && !empty($this->proxy) && isset($this->proxy['proxy'])) {
            curl_setopt($cHandle, CURLOPT_PROXY, $this->proxy['proxy']);
            if (isset($this->proxy['auth']))
                curl_setopt($cHandle, CURLOPT_PROXYUSERPWD, $this->proxy['auth']);
            if (isset($this->proxy['header']))
                curl_setopt($cHandle, CURLOPT_PROXYHEADER, $this->proxy['header']);
            if (isset($this->proxy['service']))
                curl_setopt($cHandle, CURLOPT_PROXY_SERVICE_NAME, $this->proxy['service']);
            if (isset($this->proxy['sockettype']))
                curl_setopt($cHandle, CURLOPT_PROXYTYPE, $this->proxy['sockettype']);
            $this->proxy = null;
        }
    }

    /**
     * Process Request Basic Auth
     * @param CurlHandle $cHandle
     */
    private function processRequestBasicAuth(CurlHandle $cHandle)
    {
        if (is_array($this->basicAuth) && !empty($this->basicAuth) && isset($this->basicAuth['userpwd'])) {
            curl_setopt($cHandle, CURLOPT_USERPWD, $this->basicAuth['userpwd']);
            if (isset($this->basicAuth['type']))
                switch ($this->basicAuth['type']) {
                    case 'ANYSAFE':
                    case 'BASIC':
                        curl_setopt($cHandle, CURLOPT_HTTPAUTH, CURLAUTH_ANYSAFE);
                        break;
                    case 'DIGEST':
                        curl_setopt($cHandle, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
                        break;
                    case 'GSSNEGOTIATE':
                        curl_setopt($cHandle, CURLOPT_HTTPAUTH, CURLAUTH_GSSNEGOTIATE);
                        break;
                    case 'NTLM':
                        curl_setopt($cHandle, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
                        break;
                    default:
                        curl_setopt($cHandle, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
                }
            $this->proxy = null;
        }
    }

    /**
     * Process Request Cookie
     * @param CurlHandle $cHandle
     */
    private function processRequestCookie(CurlHandle $cHandle)
    {
        $result = [];
        if (is_array($this->cookie) && !empty($this->cookie)) {
            foreach ($this->cookie as $key => $value)
                $result [] = "{$key}={$value}";
        }

        if (is_array($this->cookie) && !empty($this->cookie))
            curl_setopt($cHandle, CURLOPT_COOKIE, implode(";", $result));
        $this->cookie = [];
    }

    /**
     * Clear Response
     */
    private function clearRequest()
    {
        $this->port = 80;
        $this->CACert = '';
        $this->curlOpt = [];
        $this->params = [];
        $this->cookie = [];
        $this->content = '';
        $this->headers = ['Expect' => ''];
        $this->fileUpload = false;
        $this->basicAuth = [];
        $this->proxy = null;
        $this->port = 0;
        $this->method = 'GET';
        $this->url = '';
        $this->followLocation = true;
    }

    /**
     * @param string $url
     * @param string $method
     * @param mixed $content
     * @param array $headers
     * @param array $cookies
     */
    final public function prepare(string $url, string $method = "GET", mixed $content = null, array $headers = [], array $cookies = [])
    {
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $this->addCookies($cookies);
    }

    /**
     * Set Request URL
     * @param $url
     */
    final public function setUrl($url)
    {
        if (is_string($url) && !empty($url)) {
            $parser = parse_url($url);
            if (isset($parser['scheme']) && isset($parser['host'])) {
                $this->url = $url;
            }
        }
    }

    /**
     * Setup Request Method, default is auto
     * @param string $method
     * @return self
     */
    final public function setMethod(string $method): self
    {
        if (is_string($method) && !empty($method) && in_array($method, $this->availableMethod, true))
            $this->method = strtoupper($method);
        return $this;
    }

    /**
     * Set Request Body Content
     * @param string $content
     * @param string $contentType
     * @return self
     */
    final public function setContent(string $content, string $contentType = 'plain/text'): self
    {
        if (is_string($content) && !empty($content)) {
            $this->params = [];
            $this->content = $content;
            $this->method = 'POST';
            $this->addHeader("Content-Type", $contentType);
        }
        return $this;
    }

    /**
     * @param string $url
     * @param string $method
     * @param ?string $content
     * @param array $headers
     * @param array $cookies
     * @return mixed
     */
    final public function __invoke(string $url,
                                   string $method = "GET",
                                   ?string $content = null,
                                   array $headers = [],
                                   array $cookies = []): mixed
    {
        return $this->run($url, $method, $content, $headers, $cookies);
    }

    /**
     * @param string $url
     * @param string $method
     * @param string|array|null $content
     * @param array $headers
     * @param array $cookies
     * @return mixed
     */
    final public function run(string $url,
                              string $method = "GET",
                              ?string|array $content = null,
                              array $headers = [],
                              array $cookies = []): mixed
    {
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $this->addCookies($cookies);
        return $this->_execute();
    }

    /**
     * Request Only, not waiting for response
     * @param string $url
     * @param string $method
     * @param ?string $content
     * @param array $headers
     * @param array $cookies
     */
    final public function requestOnly(string $url,
                                      string $method = "GET",
                                      ?string $content = null,
                                      array $headers = [],
                                      array $cookies = []): void
    {
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $this->addCookies($cookies);
        $timeout = $this->timeout;
        $this->timeout = 0;
        $this->addOption(CURLOPT_TIMEOUT_MS, 1);
        $this->_execute();
        $this->timeout = $timeout;
    }

    /**
     * Download a file to Local
     * @param string $url
     * @param string $saveFileName
     * @param string $method
     * @param string|null $content
     * @param array $headers
     * @param array $cookies
     * @return bool
     */
    final public function downloadFile(string $url,
                                       string $saveFileName,
                                       string $method = "GET",
                                       ?string $content = NULL,
                                       array $headers = [],
                                       array $cookies = []): bool
    {
        if (file_exists($saveFileName))
            @unlink($saveFileName);
        $fp = fopen($saveFileName, 'w');
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $this->addCookies($cookies);
        $result = $this->_execute($fp);
        fclose($fp);
        return (bool)$result;
    }

    /**
     * @param bool $enable
     * @return self
     */
    final public function verboseDebug(bool $enable = true): self
    {
        $this->verboseDebug = $enable;
        return $this;
    }

    /**
     * Return Verbose Log
     * @return string
     */
    final public function verboseLog(): string
    {
        return $this->verboseDebugLog;
    }

    /**
     * Print out verbose log
     */
    final public function printVerboseLog(): void
    {
        echo "<pre>" . $this->verboseDebugLog . "</pre>";
    }

    /**
     * @param self $rpcThread
     * @param string $name
     * @return bool|resource
     */
    final public function addNewThread(self $rpcThread, string $name = ''): CurlHandle|bool
    {
        $handle = $rpcThread->createHandle(true);
        if ($handle === false)
            return false;
        if (!empty($name))
            $this->rpcThread[$name] = $rpcThread;
        else
            $this->rpcThread[] = $rpcThread;
        return $handle;
    }

    /**
     * @return array
     */
    final public function multiThread(): array
    {
        $mh = curl_multi_init();
        foreach ($this->rpcThread as $rpc)
            if ($rpc instanceof self)
                curl_multi_add_handle($mh, $rpc->getHandle());

        // execute the handles
        $running = null;
        do {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
        } while ($running > 0);

        $result = [];
        foreach ($this->rpcThread as $key => $rpc) {
            if ($rpc instanceof Rpc) {
                $handle = $rpc->getHandle();
                $error = curl_error($handle);
                if (empty($error)) {
                    $result[$key] = curl_multi_getcontent($handle);
                }
                curl_multi_remove_handle($mh, $handle);
                $rpc->closeHandle();
            }
        }
        $this->rpcThread = [];
        curl_multi_close($mh);
        return $result;
    }
}