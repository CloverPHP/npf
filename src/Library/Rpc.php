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
    private CurlHandle $handle;

    /**
     * @var array
     */
    private array $rpcThread = [];

    /**
     * @var array $verbose
     */
    private array $verbose = [
        'enable' => false,
        'handle' => null,
        'tmpfile' => '',
        'log' => '',
    ];

    private array $cookie = [
        'request' => [],
        'tmpfile' => '',
        'internal' => '',
    ];

    private array $oldFile = [];

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
     * @param int|null $connectionTimeout
     * @return self
     */
    final public function setTimeout(int $timeout = 30, int $timeoutMS = 0, ?int $connectionTimeout = null): self
    {
        if ($timeout > 0)
            $this->timeout = $timeout;
        if ($timeoutMS > 0)
            $this->timeoutMS = $timeoutMS;
        if (is_int($connectionTimeout) && $connectionTimeout > 0)
            $this->connectTimeout = $connectionTimeout;
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
        if (!empty($userAgent))
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
        if (!empty($user)) {
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
                                   int    $socketType = 0): self
    {
        if (!empty($proxyAddress)) {
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
        if (!empty($name) && !empty($fileName) && file_exists($fileName) && !is_dir($fileName)) {
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
        if (!empty($name) && !empty($content))
            $this->cookie['request'][$name] = $content;
        return $this;
    }

    /**
     * Add Cookie to response
     * @param array $cookies
     * @return self
     */
    final public function addCookies(array $cookies): self
    {
        if (!empty($cookies))
            $this->cookie['request'] = array_merge($this->cookie['request'], $cookies);
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
        if (!empty($optId))
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
        if (!empty($options))
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
        if (!empty($name) && !empty($content))
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
        if (!empty($headers))
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
        if (!empty($name)) {
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
        if (!empty($values)) {
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
     * @return bool|int|float|string|array|null
     */
    final public function getResponseInfo(string $name): null|bool|int|float|string|array
    {
        if (empty($name) || $name === '*')
            return $this->response['info'];
        else
            return $this->response['info'][$name] ?? null;
    }

    /**
     * Get Response Header
     * @param string $name
     * @return bool|int|float|string|array|null
     */
    final public function getResponseHeader(string $name = '*'): null|bool|int|float|string|array
    {
        if (empty($name) || $name === '*')
            return $this->response['header'];
        else
            return $this->response['header'][$name] ?? null;
    }

    /**
     * Get Response Cookie
     * @param string $name
     * @return bool|int|float|string|array|null
     */
    final public function getResponseCookie(string $name = '*'): null|bool|int|float|string|array
    {
        if (empty($name) || $name === '*')
            return $this->response['cookie'];
        else
            return $this->response['cookie'][$name] ?? null;
    }

    /**
     * Get Response Body Content
     * @return bool|int|float|string|array|null
     */
    final public function getResponseContent(): null|bool|int|float|string|array
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
    final public function processResponseHeader(CurlHandle $ch, string $headerLine): int
    {
        $matches = [];
        if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $headerLine, $matches) == 1) {
            $cookie = [];
            parse_str($matches[1], $cookie);
            $this->response['cookie'] = array_merge($this->response['cookie'], $cookie);
        } else {
            if (str_starts_with($headerLine, 'HTTP')) {
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
        $this->cookie['internal'] = $cookieData;
    }

    /**
     * Process Response Header
     * @return string
     */
    final public function exportCookieData(): string
    {
        return $this->cookie['internal'];
    }

    /**
     * For public to execute
     * @param bool $reuseConnection
     * @return string|bool
     */
    final public function execute(bool $reuseConnection = false): string|bool
    {
        $this->_execute($reuseConnection);
        return $this->response['body'];
    }

    /**
     * @return CurlHandle
     */
    final public function getHandle(): CurlHandle
    {
        return $this->handle;
    }

    /**
     * @param CurlHandle $handle
     * @return CurlHandle
     */
    final public function setHandle(CurlHandle $handle): CurlHandle
    {
        return $this->handle = $handle;
    }

    /**
     * @param bool $reuseConnection
     * @return false|resource
     */
    final public function createHandle(bool $reuseConnection = false): CurlHandle|bool
    {
        //Prepare Data
        $this->clearResponse();
        $this->processRequestContent();

        //Prepare Cookie Data
        if (empty($this->cookie['tmpfile'])) {
            $this->cookie['tmpfile'] = tempnam(sys_get_temp_dir(), 'npf.rpc.cookie');
            if (!empty($this->cookie['internal']))
                file_put_contents($this->cookie['tmpfile'], $this->cookie['internal']);
        }

        //Prepare CURL
        $this->curlOpt = [
                CURLOPT_FOLLOWLOCATION => $this->followLocation,
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
                CURLOPT_COOKIEJAR => $this->cookie['tmpfile'],
                CURLOPT_COOKIEFILE => $this->cookie['tmpfile'],
                CURLOPT_VERBOSE => $this->verbose['enable'],
            ] + $this->curlOpt;
        if (($this->timeout * 1000) + $this->timeoutMS < 1000)
            $this->curlOpt[CURLOPT_NOSIGNAL] = TRUE;
        if ($reuseConnection === false) {
            $this->curlOpt += [
                CURLOPT_FORBID_REUSE => TRUE,
                CURLOPT_FRESH_CONNECT => TRUE,
            ];
        }
        if ($this->port > 0)
            $this->curlOpt[CURLOPT_PORT] = $this->port;
        if (!empty($this->CACert))
            $this->curlOpt[CURLOPT_CAINFO] = $this->CACert;

        if ($this->method !== 'GET')
            $this->curlOpt += [
                CURLOPT_POST => TRUE,
                CURLOPT_POSTFIELDS => $this->content,
            ];

        //Record Verbose Log
        if ($this->verbose['enable']) {
            if ($this->verbose['handle'] === null) {
                $this->verbose['tmpfile'] = tempnam(sys_get_temp_dir(), 'npf.rpc.verbose');
                $this->verbose['handle'] = fopen($this->verbose['tmpfile'], 'wb+');
            } else
                fwrite($this->verbose['handle'], "\n-------------------------\n");
            if (is_resource($this->verbose['handle']))
                $this->curlOpt[CURLOPT_STDERR] = $this->verbose['handle'];
        }

        //Setup Curl
        if (isset($this->handle) && $this->handle instanceof CurlHandle)
            curl_reset($this->handle);
        else
            $this->handle = curl_init($this->url);
        curl_setopt_array($this->handle, $this->curlOpt);

        $this->processRequestProxy($this->handle);
        $this->processRequestBasicAuth($this->handle);
        $this->processRequestCookie($this->handle);

        return $this->handle;
    }

    /**
     * Execute a request, & process response
     * @param bool $resueConnection
     * @param mixed $outputHandle
     */
    private function _execute(bool $resueConnection = false, mixed $outputHandle = null)
    {
        $this->createHandle($resueConnection);

        //Execute CURL Request & Getting Returning Response
        $outputReturn = true;
        if (is_resource($outputHandle) && get_resource_type($outputHandle) === 'stream') {
            curl_setopt($this->handle, CURLOPT_FILE, $outputHandle);
            $outputReturn = false;
        }

        $this->response['body'] = curl_exec($this->handle);
        if ($this->response['body'] === false) {
            $this->response['error'] = curl_error($this->handle);
            $this->response['errno'] = curl_errno($this->handle);
        } elseif (!$outputReturn) {
            $this->response['body'] = $outputHandle;
        }
        //Statistics Request Time
        $this->response['info'] = curl_getinfo($this->handle);

        $this->clearRequest();

        //Close Curl
        if ($resueConnection === false)
            $this->closeHandle();
    }

    /**
     * Clear Response
     */
    final public function closeHandle()
    {
        if (!empty($this->handle) && is_resource($this->handle))
            curl_close($this->handle);
        unset($this->handle);

        //Retrieve Verbose Log
        if (is_resource($this->verbose['handle'])) {
            rewind($this->verbose['handle']);
            $this->verbose['log'] = (string)stream_get_contents($this->verbose['handle']);
            fclose($this->verbose['handle']);
            $this->verbose['handle'] = null;
            if (is_string($this->verbose['tmpfile']) && file_exists($this->verbose['tmpfile']))
                unlink($this->verbose['tmpfile']);
        }

        #Export/Unlink Cookie
        if (!empty($this->cookie['tmpfile'])) {
            if (file_exists($this->cookie['tmpfile'])) {
                $this->cookie['internal'] = file_get_contents($this->cookie['tmpfile']);
                unlink($this->cookie['tmpfile']);
            }
            $this->cookie['tmpfile'] = '';
        }
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
            'info' => [],
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
        if (is_array($this->cookie['request']) && !empty($this->cookie['request'])) {
            foreach ($this->cookie['request'] as $key => $value)
                $result [] = "{$key}={$value}";
        }

        if (is_array($this->cookie['request']) && !empty($this->cookie['request']))
            curl_setopt($cHandle, CURLOPT_COOKIE, implode(";", $result));
        $this->cookie['request'] = [];
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
        $this->cookie['request'] = [];
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
        if (!empty($method) && in_array($method, $this->availableMethod, true))
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
        if (!empty($content)) {
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
     * @param string|array|null $content
     * @param array $headers
     * @param array $cookies
     * @return string|bool
     */
    final public function __invoke(string            $url,
                                   string            $method = "GET",
                                   string|array|null $content = null,
                                   array             $headers = [],
                                   array             $cookies = []): string|bool
    {
        return $this->run($url, $method, $content, $headers, $cookies);
    }

    /**
     * @param string $url
     * @param string $method
     * @param string|array|null $content
     * @param array $headers
     * @param array $cookies
     * @return string|bool
     */
    final public function run(string            $url,
                              string            $method = "GET",
                              string|array|null $content = null,
                              array             $headers = [],
                              array             $cookies = []): string|bool
    {
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $this->addCookies($cookies);
        $this->_execute();

        return $this->response['body'];
    }

    /**
     * Request Only, not waiting for response
     * @param string $url
     * @param string $method
     * @param string|array|null $content
     * @param array $headers
     * @param array $cookies
     */
    final public function requestOnly(string            $url,
                                      string            $method = "GET",
                                      string|array|null $content = null,
                                      array             $headers = [],
                                      array             $cookies = []): void
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
     * @param string|array|null $content
     * @param array $headers
     * @param array $cookies
     * @return bool
     */
    final public function downloadFile(string            $url,
                                       string            $saveFileName,
                                       string            $method = "GET",
                                       string|array|null $content = null,
                                       array             $headers = [],
                                       array             $cookies = []): bool
    {
        if (file_exists($saveFileName))
            unlink($saveFileName);
        $fp = fopen($saveFileName, 'w');
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $this->addCookies($cookies);
        $this->_execute(outputHandle: $fp);
        fclose($fp);
        return (int)$this->response['status'] === 200;
    }

    /**
     * @param bool $enable
     * @return self
     */
    final public function verboseDebug(bool $enable = true): self
    {
        $this->verbose['enable'] = $enable;
        return $this;
    }

    /**
     * Return Verbose Log
     * @return string
     */
    final public function verboseLog(): string
    {
        if (is_resource($this->verbose['handle'])) {
            $content = '';
            while (!feof($this->verbose['handle']))
                $content .= fread($this->verbose['handle'], 8192);
            ftruncate($this->verbose['handle'], 0);
            return $content;
        } else
            return $this->verbose['log'];
    }

    /**
     * Print out verbose log
     */
    final public function printVerboseLog(): void
    {
        echo "<pre>" . $this->verboseLog() . "</pre>";
    }

    /**
     * @param self $rpcThread
     * @param string $name
     * @return false|void
     */
    final public function addNewThread(self $rpcThread, string $name = '')
    {
        $handle = $rpcThread->createHandle(true);
        if ($handle === false)
            return false;
        if (!empty($name))
            $this->rpcThread[$name] = $rpcThread;
        else
            $this->rpcThread[] = $rpcThread;
    }

    /**
     * @param bool $autoClose
     * @return array
     */
    final public function multiThread(bool $autoClose = true): array
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
                if (empty($error))
                    $result[$key] = curl_multi_getcontent($handle);
                curl_multi_remove_handle($mh, $handle);
                if ($autoClose)
                    $rpc->closeHandle();
            }
        }
        $this->rpcThread = [];
        curl_multi_close($mh);
        return $result;
    }
}