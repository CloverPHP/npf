<?php

namespace Npf\Library;

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
    private $url = '';

    /**
     * @var bool Enable Verbose Debug
     */
    private $verboseDebug = false;

    /**
     * @var string CURL Verbose Debug Log
     */
    private $verboseDebugLog = '';

    /**
     * @var int Request Timeout
     */
    private $timeout = 30;

    /**
     * @var int Request Timeout in ms
     */
    private $timeoutMS = 0;

    /**
     * @var int Request Timeout
     */
    private $connectTimeout = 30;

    /**
     * @var int Request Connection Port
     */
    private $port = 0;

    /**
     * @var array Request Header
     */
    private $headers = ['Expect' => ''];

    /**
     * @var array Basic Auth
     */
    private $basicAuth = [];

    /**
     * @var array Get/Post Parameters
     */
    private $params = [];

    /**
     * @var array Get/Post Binding Parameters
     */
    private $bindingParams = [];

    /**
     * @var array Cookie
     */
    private $cookie = [];

    /**
     * @var string Body Content
     */
    private $content = '';

    /**
     * @var string Request Method : Post/Get/Put/HTTP2.0 Custom method
     */
    private $method = 'GET';

    /**
     * @var array Available Method
     */
    private $availableMethod = ['GET', 'POST', 'PUT', 'HEADER'];

    /**
     * @var string User Agent Simulation
     */
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/54.0.2840.99 Safari/537.36'; //Simulation Google Chrome

    /**
     * @var array Response Data
     */
    private $response = [];

    /**
     * @var null|array Request Proxy
     */
    private $proxy = null;

    /**
     * @var string Internal Cookie Handle
     */
    private $internalCookie = '';

    /**
     * @var bool Flag is have file upload
     */
    private $fileUpload = false;

    /**
     * @var array Curl additional option
     */
    private $curlOpt = [];

    /**
     * @var string CA Cert File Path to load
     */
    private $CACert = '';

    /**
     * @var bool
     */
    private $followLocation = true;
    /**
     * @var
     */
    private $handle;

    private $rpcThread;

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
     * Rpc constructor.
     * @param int $connectionTimeout
     */
    final public function setConnectionTimeout($connectionTimeout = 0)
    {
        $connectionTimeout = (int)$connectionTimeout;
        if ($connectionTimeout > 0)
            $this->connectTimeout = $connectionTimeout;
    }

    /**
     * Rpc constructor.
     * @param int $timeout
     * @param int $timeoutMS
     */
    final public function setTimeout($timeout = 30, $timeoutMS = 0)
    {
        $timeout = (int)$timeout;
        $timeoutMS = (int)$timeoutMS;
        if ($timeout > 0)
            $this->timeout = $timeout;
        if ($timeoutMS > 0)
            $this->timeoutMS = $timeoutMS;
    }

    /**
     * Auto Follow Location
     * @param null|boolean $enable
     * @return bool
     */
    final public function autoFollowLocation($enable = null)
    {
        if ($enable !== null) {
            $this->followLocation = (boolean)$enable;
            return $enable;
        } else
            return $this->followLocation;
    }

    /**
     * Get User Agent
     * @return string
     */
    final public function getUserAgent()
    {
        return $this->userAgent;
    }

    /**
     * Set User Agent
     * @param string $userAgent
     */
    final public function setUserAgent($userAgent)
    {
        if (is_string($userAgent) && !empty($userAgent)) {
            $this->userAgent = $userAgent;
        }
    }

    /**
     * Set Request Connection Port
     * @param int $port
     */
    final public function setPort($port = 0)
    {
        if (is_int($port) && !empty($port))
            $this->port = $port;
    }

    /**
     * Set Request Basic Auth
     * @param string $user Proxy Auth User
     * @param string $pass Proxy Auth Pass
     * @param string $type
     */
    final public function setBasicAuth($user, $pass, $type = 'any')
    {
        if (is_string($user) && !empty($user) && is_string($pass)) {
            $this->basicAuth['userpwd'] = "{$user}:{$pass}";
            $this->basicAuth['type'] = $type;
        }
    }

    /**
     * Set Request Proxy
     * @param string $proxyAddress Proxy address, include port e.g. 192.168.1.1:8080
     * @param string $user Proxy Auth User
     * @param string $pass Proxy Auth Pass
     * @param string $serviceName
     * @param $header
     * @param int $socketType
     */
    final public function setProxy($proxyAddress, $user = '', $pass = '', $serviceName = '', $header = null, $socketType = 0)
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
    }

    /**
     * Set Request Body Content
     * @param string $cert File
     */
    final public function setCACert($cert)
    {
        $cert = realpath($cert);
        if (is_string($cert) && !empty($cert) && file_exists($cert)) {
            $this->CACert = $cert;
        }
    }

    /**
     * Add A GET/POST param
     * @param $name
     * @param $value
     */
    final public function addParam($name, $value)
    {
        if (!empty($name) && (is_string($value) || is_numeric($value))) {
            $this->params[$name] = $value;
            $this->content = '';
        }
    }

    /**
     * Add multiple request header
     * @param array $options
     */
    final public function addOptions(array $options)
    {
        if (is_array($options) && !empty($options)) {
            $this->curlOpt += $options;
        }
    }

    /**
     * Add A GET/POST param
     * @param $name
     * @param $value
     */
    final public function bindingParam($name, $value)
    {
        if (!empty($name) && (is_string($value) || is_numeric($value))) {
            $this->bindingParams[$name] = $value;
        }
    }

    /**
     * Add A GET/POST param
     * @param $name
     * @param $value
     */
    final public function bindingParams($name, $value)
    {
        if (!empty($name) && (is_string($value) || is_numeric($value))) {
            $this->bindingParams[$name] = $value;
        }
    }

    /**
     * Add Multiple Get/Set Params
     * @param $name
     * @param $fileName
     * @param null $contentType
     * @return bool
     * @internal param array $values
     */
    final public function addFile($name, $fileName, $contentType = null)
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
            return true;
        } else
            return false;
    }

    /**
     * Add Cookie to response
     * @param $name
     * @param $content
     */
    final public function addCookie($name, $content)
    {
        if (is_string($name) && !empty($name) && is_string($content) && !empty($content)) {
            $this->cookie[$name] = $content;
        }
    }

    /**
     * Get Response Header
     * @return array
     */
    final public function getResponse()
    {
        return $this->response;
    }

    /**
     * Get Response Header
     * @param $name
     * @return mixed|null
     */
    final public function getResponseHeader($name)
    {
        if (is_string($name) && !empty($name)) {
            if ($name === '*')
                return $this->response['header'];
            else
                return isset($this->response['header'][$name]) ? $this->response['header'][$name] : null;
        } else
            return null;
    }

    /**
     * Get Response Cookie
     * @param $name
     * @return mixed|null
     */
    final public function getResponseCookie($name)
    {
        if (is_string($name) && !empty($name)) {
            if ($name === '*')
                return $this->response['cookie'];
            else
                return isset($this->response['cookie'][$name]) ? $this->response['cookie'][$name] : null;
        } else
            return null;
    }

    /**
     * Get Response Body Content
     * @return string
     */
    final public function getResponseContent()
    {
        return $this->response['body'];
    }

    /**
     * Get Response Status Code
     * @return string
     */
    final public function getResponseStatus()
    {
        return $this->response['status'];
    }

    /**
     * Get Response Status Code
     * @return string
     */
    final public function getResponseStatusCode()
    {
        return (int)$this->response['code'];
    }

    /**
     * Process Response Header
     * @param $ch
     * @param string $headerLine
     * @return int
     */
    final public function processResponseHeader($ch, $headerLine)
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
     * @param $cookieData
     */
    final public function importCookieData($cookieData)
    {
        $this->internalCookie = $cookieData;
    }

    /**
     * Process Response Header
     * @return string
     */
    final public function exportCookieData()
    {
        return $this->internalCookie;
    }

    /**
     * For public to execute
     * @return mixed
     */
    final public function execute()
    {
        return $this->_execute();
    }

    /**
     * @param bool $fresh
     * @return false|resource
     */
    final public function createHandle($fresh = false)
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
                CURLOPT_VERBOSE => $this->verboseDebug ? TRUE : FALSE,
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
    final public function getHandle()
    {
        return $this->handle;
    }

    /**
     * Execute a request, & process response
     * @param null $outputHandle
     * @return mixed
     */
    final private function _execute($outputHandle = null)
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
                CURLOPT_VERBOSE => $this->verboseDebug ? TRUE : FALSE,
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
    final private function closeHandle()
    {
        if (!empty($this->handle) && is_resource($this->handle))
            curl_close($this->handle);
        $this->handle = null;
    }

    /**
     * Clear Response
     */
    final private function clearResponse()
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
    final private function processRequestContent()
    {
        if ($this->method === 'GET') {
            if (is_array($this->params) && !empty($this->params)) {
                $content = http_build_query($this->params);
                $this->url .= (strpos($this->url, "?") === false ? "?" : "&") . $content;
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
     * Added a request header
     * @param string $name
     * @param string $content
     */
    final public function addHeader($name, $content)
    {
        if (is_string($name) && !empty($name) && is_string($content) && !empty($content)) {
            $this->headers[$name] = $content;
        }
    }

    /**
     * Process Request Body
     * @return array
     */
    final private function processRequestHeader()
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
     * @param resource $cHandle
     */
    final private function processRequestProxy($cHandle)
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
     * @param resource $cHandle
     */
    final private function processRequestBasicAuth($cHandle)
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
     * @param resource $cHandle
     */
    final private function processRequestCookie($cHandle)
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
    final private function clearRequest()
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
     * @param $url
     * @param $method
     * @param $content
     * @param array $headers
     */
    final public function prepare($url, $method = "GET", $content = null, array $headers = [])
    {
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
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
     */
    final public function setMethod($method)
    {
        if (is_string($method) && !empty($method) && in_array($method, $this->availableMethod, true)) {
            $this->method = strtoupper($method);
        }
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
     * Set Request Body Content
     * @param string $content
     * @param string $contentType
     */
    final public function setContent($content, $contentType = 'plain/text')
    {
        if (is_string($content) && !empty($content)) {
            $this->params = [];
            $this->content = $content;
            $this->method = 'POST';
            $this->addHeader("Content-Type", $contentType);
        }
    }

    /**
     * Add multiple request header
     * @param array $headers
     */
    final public function addHeaders(array $headers)
    {
        if (is_array($headers) && !empty($headers)) {
            $this->headers = array_merge($this->headers, $headers);
        }
    }


    /**
     * @param $url
     * @param $method
     * @param $content
     * @param array $headers
     * @return mixed
     */
    final public function __invoke($url, $method = "GET", $content = null, array $headers = [])
    {
        return $this->run($url, $method, $content, $headers);
    }

    /**
     * @param $url
     * @param $method
     * @param $content
     * @param array $headers
     * @return mixed
     */
    final public function run($url, $method = "GET", $content = null, array $headers = [])
    {
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        return $this->_execute();
    }

    /**
     * Request Only, not waiting for response
     * @param $url
     * @param $method
     * @param $content
     * @param array $headers
     */
    final public function requestOnly($url, $method = "GET", $content = null, array $headers = [])
    {
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $timeout = $this->timeout;
        $this->timeout = 0;
        $this->addOption(CURLOPT_TIMEOUT_MS, 1);
        $this->_execute();
        $this->timeout = $timeout;
    }

    /**
     * Add a curl option
     * @param int $optId
     * @param $value
     */
    final public function addOption($optId, $value)
    {
        if (!empty($optId) && is_int($optId)) {
            $this->curlOpt[$optId] = $value;
        }
    }

    /**
     * Download a file to Local
     * @param $url
     * @param $saveFileName
     * @param string $method
     * @param null $content
     * @param array $headers
     * @return bool
     */
    final public function downloadFile($url, $saveFileName, $method = "GET", $content = NULL, array $headers = [])
    {
        if (file_exists($saveFileName)) {
            @unlink($saveFileName);
        }
        $fp = fopen($saveFileName, 'w');
        $this->setUrl($url);
        $this->setMethod($method);
        if (is_array($content))
            $this->addParams($content);
        elseif (is_string($content))
            $this->setContent($content);
        $this->addHeaders($headers);
        $result = $this->_execute($fp);
        fclose($fp);
        return !$result ? FALSE : TRUE;
    }

    /**
     * @param bool $enable
     */
    final public function verboseDebug($enable = true)
    {
        $this->verboseDebug = $enable;
    }

    /**
     * Return Verbose Log
     * @return string
     */
    final public function verboseLog()
    {
        return $this->verboseDebugLog;
    }

    /**
     * Print out verbose log
     */
    final public function printVerboseLog()
    {
        echo "<pre>" . $this->verboseDebugLog . "</pre>";
    }

    /**
     * @param Rpc $rpcThread
     * @param string $name
     * @return bool|false|resource
     */
    final public function addNewThread(Rpc $rpcThread, $name = '')
    {
        $handle = $rpcThread->createHandle(true);
        if ($handle === false)
            return false;
        $name = (string)$name;
        if (!empty($name))
            $this->rpcThread[$name] = $rpcThread;
        else
            $this->rpcThread[] = $rpcThread;
        return $handle;
    }

    /**
     * @return array
     */
    final public function multiThread()
    {
        $mh = curl_multi_init();
        foreach ($this->rpcThread as $rpc)
            if ($rpc instanceof Rpc) {
                curl_multi_add_handle($mh, $rpc->getHandle());
            }

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