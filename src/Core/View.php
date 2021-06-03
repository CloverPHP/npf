<?php


namespace Npf\Core;

use finfo;
use Npf\Exception\InternalError;
use Npf\Library\Xml;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Lexer;
use Twig\Loader\FilesystemLoader;

/**
 * Class View
 * Enhanced curl and make more easy to use
 */
class View
{
    /**
     * @var App
     */
    private $app;

    /**
     * @var string
     */
    private $type = '';

    /**
     * @var mixed
     */
    private $data = false;

    /**
     * @var array
     */
    private $twigExtension = [];

    /**
     * @var array
     */
    private $twigPath = [];

    /**
     * @var bool
     */
    private $output;

    /**
     * @var Container
     */
    private $generalConfig;

    /**
     * @var bool Cache Control
     */
    private $cache = true;

    /**
     * @var bool Lock Current View
     */
    private $lockView = false;

    /**
     * @var bool Lock Current View
     */
    private $expiryTtl = 0;

    /**
     * View constructor.
     * @param App $app
     */
    final public function __construct(App &$app)
    {
        $this->app = &$app;
        if (in_array($this->app->getRoles(), ['daemon', 'cronjob'], true)) {
            $opts = getopt('o', ['output']);
            if (is_array($opts) && !empty($opts))
                $this->output = true;
        } else
            $this->output = (boolean)$this->app->response->get('output', false);
        try {
            $this->generalConfig = $this->app->config('General', true);
        } catch (\Exception $ex) {
            $this->generalConfig = new Container();
        }
        $this->type = $this->generalConfig->get('defaultOutput', 'json');
    }

    /**
     * @return string
     */
    final public function getType()
    {
        return $this->type;
    }

    /**
     * @return void
     */
    final public function cached()
    {
        $this->cache = true;
    }

    /**
     * @return void
     */
    final public function noCache()
    {
        $this->cache = false;
    }

    /**
     * @return void
     */
    final public function lock()
    {
        $this->lockView = true;
    }

    /**
     * @return void
     */
    final public function unlock()
    {
        $this->lockView = false;
    }

    /**
     * @param $expireTtl
     * @return void
     */
    final public function setViewExpiry($expireTtl)
    {
        $this->expiryTtl = (int)$expireTtl;
        if ($this->expiryTtl < 0)
            $this->expiryTtl = 0;
    }

    /**
     * @param $twigExtension
     */
    final public function addTwigExtension($twigExtension)
    {
        if (!empty($twigExtension) && (is_string($twigExtension) || is_object($twigExtension)))
            $this->twigExtension[] = $twigExtension;
    }

    /**
     * @param $path
     * @param null $name
     */
    final public function addTwigPath($path, $name = null)
    {
        if (!empty($path) && file_exists($path) && is_dir($path)) {
            if (!empty($name) && is_string($name))
                $this->twigPath[$name] = $path;
            else
                $this->twigPath[] = $path;
        }
    }

    /**
     * Set View as none type
     */
    final public function setNone()
    {
        if ($this->lockView)
            return;
        $this->type = 'none';
        $this->data = null;
    }

    /**
     * Set View as none type
     * @param $content
     */
    final public function setPlain($content)
    {
        if ($this->lockView)
            return;
        if (!empty($content)) {
            $this->type = 'plain';
            $this->data = $content;
        }
    }

    /**
     * Set View as json type
     */
    final public function setJson()
    {
        if ($this->lockView)
            return;
        $this->type = 'json';
        $this->data = null;
    }

    /**
     * @param null $rooTag
     */
    final public function setXml($rooTag = null)
    {
        if ($this->lockView)
            return;
        $this->type = 'xml';
        if (empty($rooTag) || !is_string($rooTag))
            $rooTag = $this->generalConfig->get('xmlRoot', 'root');
        $this->data = $rooTag;
    }

    /**
     * @param $file
     * @param $paths
     * @param int $viewLevel
     */
    final public function setTwig($file, $paths = null, $viewLevel = 1)
    {
        if ($this->lockView)
            return;
        $this->type = 'twig';
        if (!empty($paths) && is_string($paths))
            $paths = [$paths];
        if (!is_array($paths))
            $paths = [];
        $twigPaths = $paths;
        $localPath = '';
        if ($callerInfo = $this->app->getCallerInfo($viewLevel))
            $localPath = Common::strPop('/', $callerInfo['file']);
        if (!file_exists($localPath))
            $localPath = '';
        if (!empty($localPath))
            $twigPaths[] = $localPath;
        $this->data = [
            'file' => $file,
            'path' => $twigPaths,
        ];
    }

    /**
     * @param null $file
     * @throws InternalError
     */
    final public function setStatic($file = null)
    {
        if ($this->lockView)
            return;
        if (file_exists($file) && !is_dir($file)) {
            $this->type = 'static';
            $this->data = $file;
        } else
            throw new InternalError('Static File Not found on app.view.setView');
    }

    /**
     * Setup View File
     * @param string $type
     * @param null $data
     * @param int $viewLevel
     * @throws InternalError
     */
    final public function setView($type, $data = null, $viewLevel = 1)
    {
        switch ($type) {
            case 'html':
            case 'twig':
                $this->setTwig($data, [], $viewLevel);
                break;
            case'static':
                $this->setStatic($data);
                break;
            case 'xml':
                $this->setXml($data);
                break;
            case'plain':
                $this->setPlain($data);
                break;
            case 'none':
            case false:
                $this->setNone();
                break;
            default:
                $this->setJson();
                break;
        }
    }

    /**
     * @throws InternalError
     */
    final public function error()
    {
        $errorDisplay = $this->app->config('Profiler')->get('errorOutput');
        if ($errorDisplay === 'auto') {
            if (!empty($this->type))
                $errorDisplay = $this->type;
            elseif (!in_array($this->app->getRoles(), ['cronjob', 'daemon'], true))
                $errorDisplay = $this->app->request->isXHR() ? 'json' : 'twig';
            else
                $errorDisplay = 'json';
        }
        switch ($errorDisplay) {

            case 'html':
            case 'twig':
                $corePath = Common::strPop('/', str_replace('\\', '/', __FILE__));
                $mainPath = Common::strPop('/', $corePath);
                $this->setTwig(
                    $this->app->config('Profiler')->get('errorTwig', 'error.twig'),
                    [
                        $mainPath
                    ]);
                break;

            case 'xml':
                $this->setXml();
                break;

            case 'none':
                $this->setNone();
                if (!$this->app->response->statusCode())
                    $this->app->response->statusCode(500);
                break;

            default:
                $this->setJson();
                break;
        }
    }

    /**
     * @throws InternalError
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    final public function render()
    {
        $response = $this->app->response->fetch();
        if ($this->type === '')
            $this->setJson();
        if ($this->type === 'none' && !$response['statusCode'])
            $response['statusCode'] = 204;
        $data = $response['body'];
        if (!$this->app->profiler->enable())
            unset($data['profiler']);
        elseif (isset($content['profiler']['debug']))
            $data['profiler']['debug'] = array_values($data['profiler']['debug']);

        switch ($this->type) {

            case 'plain':
                $this->renderPlan($response['statusCode']);
                break;

            case 'xml':
                $this->renderXml($data, $response['statusCode']);
                break;

            case 'none':
                $this->renderNone($response['statusCode']);
                break;

            case 'static':
                $this->renderStatic($response['statusCode']);
                break;

            case 'twig':
                if (!empty($this->data['file']) && !empty($this->data['path'])) {
                    if (is_string($this->data['path']))
                        $this->addTwigPath($this->data['path']);
                    else {
                        foreach ($this->data['path'] as $name => $path)
                            $this->addTwigPath($path, is_numeric($name) ? null : $name);
                    }
                    $this->data = $this->data['file'];
                    $this->renderTwig($data, $response['statusCode']);
                } else
                    throw new InternalError('View Twig info is incomplete on app.view.render');
                break;

            default:
                $this->type = 'json';
                $this->renderJson($data, $response['statusCode']);
                break;
        }
    }

    /**
     * Render None Response
     * @param bool $statusCode
     */
    private function renderNone($statusCode = false)
    {
        $this->output(false, $statusCode);
    }

    /**
     * Render Plan Text
     * @param bool $statusCode
     */
    private function renderPlan($statusCode = false)
    {
        $this->app->response->header('Content-Type', 'text/plain; charset=utf-8');
        $this->output($this->data, $statusCode);
    }

    /**
     * @param $data
     * @param bool $statusCode
     */
    private function renderJson($data, $statusCode = false)
    {
        $jsonFlag = JSON_UNESCAPED_UNICODE;
        if ($this->generalConfig->get('printPretty', false))
            $jsonFlag |= JSON_PRETTY_PRINT;
        $this->app->response->header('Content-Type', 'text/json; charset=utf-8');
        $this->output(json_encode($data, $jsonFlag), $statusCode);
    }

    /**
     * @param $data
     * @param bool $statusCode
     * @throws InternalError
     */
    private function renderXml($data, $statusCode = false)
    {
        if (empty($this->data) || !is_string($this->data))
            $this->data = $this->generalConfig->get('xmlRoot', 'root');
        $xml = Xml::createXML($this->data, $data);
        if ($this->generalConfig->get('printPretty', false)) {
            $xml->preserveWhiteSpace = false;
            $xml->formatOutput = true;
        } else {
            $xml->preserveWhiteSpace = true;
            $xml->formatOutput = false;
        }
        $this->app->response->header('Content-Type', 'text/xml; charset=utf-8');
        $this->output($xml->saveXML($xml->documentElement, LIBXML_NOEMPTYTAG), $statusCode);
    }

    /**
     * @param $data
     * @param bool $statusCode
     * @throws InternalError
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function renderTwig($data, $statusCode = false)
    {
        $loader = new FilesystemLoader();
        $this->addTwigPath($this->app->getRootPath() . 'Template');
        $this->addTwigPath($this->app->getRootPath() . 'App', 'App');
        $this->addTwigPath($this->app->getRootPath() . 'Template', 'Template');
        $this->addTwigPath($this->app->getRootPath(), 'Root');
        $this->twigPath = array_merge($this->app->config('Twig')->get('paths', []), $this->twigPath);
        foreach ($this->twigPath as $name => $path)
            if (file_exists($path)) {
                if (is_int($name))
                    $loader->addPath($path);
                else
                    $loader->addPath($path, $name);
            }
        $twig = new Environment($loader, $this->app->config('Twig')->get('environmentOption', []));

        if ($configExtensions = $this->app->config('Twig')->get('extension')) {
            if (!empty($configExtensions)) {
                if (is_array($configExtensions))
                    $this->twigExtension = array_merge($this->twigExtension, $configExtensions);
                else
                    $this->twigExtension[] = $configExtensions;
            }
        }
        foreach ($this->twigExtension as $extension) {
            if (is_string($extension) && !class_exists($extension))
                throw new InternalError("Twig Extension {$extension} not found");
            if (is_string($extension))
                $extension = new $extension($this->app, $twig);
            if (!is_object($extension))
                throw new InternalError("Twig Extension is not an object.\n" . get_class($extension));
            $twig->addExtension($extension);
        }
        if ($syntaxStyle = $this->app->config('Twig')->get('syntaxStyle'))
            $twig->setLexer(new Lexer($twig, $syntaxStyle));
        $appendHeader = $this->app->config('Twig')->get('appendHeader');
        $this->app->response->setHeaders($appendHeader);
        $this->app->response->header('Content-Type', 'text/html; charset=utf-8');
        $this->output($twig->render($this->data, $data), $statusCode);
    }

    /**
     * @param bool|int $statusCode
     * @return void
     * @throws InternalError
     */
    private function renderStatic($statusCode = false)
    {
        if (file_exists($this->data) && !is_dir($this->data)) {
            $routeConfig = $this->app->config('Route');
            $fileExt = pathinfo($this->data, PATHINFO_EXTENSION);
            $staticFileContentType = $routeConfig->get('staticFileContentType', []);
            $fileSize = filesize($this->data);
            $lastModified = filemtime($this->data);
            $eTag = sprintf('"%s-%s"', $lastModified, sha1_file($this->data));
            $requestLastModified = !empty($this->app->request->header("if_modified_since")) ? strtotime($this->app->request->header("if_modified_since")) : false;
            $requestETag = !empty($this->app->request->header("if_none_match")) ? trim($this->app->request->header("if_none_match")) : false;
            if (isset($staticFileContentType[$fileExt]))
                $contentType = $staticFileContentType[$fileExt];
            else {
                $contentType = $routeConfig->get('defaultStaticFileContentType', 'auto');
                if ($contentType === 'auto') {
                    $fifo = new finfo(FILEINFO_MIME_TYPE);
                    $contentType = $fifo->file($this->data);
                }
            }
            $expireTime = (int)$routeConfig->get('staticFileCacheTime', 0);
            if ($expireTime <= 0)
                $expireTime = 0;
            $cacheControl = "max-age={$expireTime}, must-revalidate";
            $method = $this->app->request->getMethod();

            $range = $this->staticRange($fileSize);
            if ($range['resume'] && $statusCode === false)
                $statusCode = 206;
            $baseFile = preg_match('/MSIE/', $this->app->request->header('user_agent')) ? str_replace('+', '%20', urlencode(basename($this->data))) : basename($this->data);
            $this->app->response->setHeaders([
                "Content-Dispositon" => "inline; filename={$baseFile}",
                "Content-Type" => $contentType,
                "Last-Modified" => gmdate("D, d M Y H:i:s", $lastModified) . " GMT",
                "Cache-Control" => $cacheControl,
                "Expires" => gmdate("D, d M Y H:i:s", (int)Common::timestamp() + $expireTime) . " GMT",
                'Content-Range', "bytes {$range['start']}-{$range['end']}/{$fileSize}",
                "Content-Length" => $method === 'OPTIONS' ? 0 : $range['length'],
            ], true);
            $output = true;
            if ($fileSize > 0)
                $this->app->response->header("ETag", $eTag, true);

            //Additional Header add-on
            $headers = $this->app->response->getHeaders();
            foreach ($headers as $headerName => $headerContent)
                header("{$headerName}: {$headerContent}");
            if ($method === 'OPTIONS')
                $output = false;

            if ((int)$statusCode > 0)
                http_response_code((int)$statusCode);
            if ($fileSize <= 0 && $statusCode === false)
                http_response_code(204);
            if ((int)$requestLastModified === (int)$lastModified && $eTag === $requestETag && $this->cache && $statusCode === false)
                http_response_code(304);
            else {
                if ($output) {
                    ignore_user_abort(true);
                    @set_time_limit(0);
                    $file = fopen($this->data, 'rb');
                    fseek($file, $range['start'], SEEK_SET);
                    $bufferSize = 4096;
                    $dataSize = $range['end'] - $range['start'];
                    while (!(connection_aborted() || connection_status() == 1) && $dataSize > 0) {
                        echo fread($file, $bufferSize);
                        $dataSize -= $bufferSize;
                        flush();
                    }
                }
                clearstatcache();
            }
        } elseif ($statusCode === false)
            http_response_code(404);
    }

    /**
     * @param $fileSize
     * @return array
     * @throws InternalError
     */
    private function staticRange($fileSize)
    {
        $range = explode('-', substr(preg_replace('/[\s|,].*/', '', $this->app->request->header('range')), 6));
        if (count($range) < 2)
            $range[1] = $fileSize;
        $range = array_combine(['start', 'end'], $range);
        if ((int)$range['start'] < 0)
            $range['start'] = 0;
        if (empty($range['end']) || (int)$range['end'] > $fileSize - 1)
            $range['end'] = $fileSize - 1;
        if ($range['start'] >= $range['end']) {
            $range['start'] = 0;
            $range['end'] = $fileSize - 1;
        }
        $range['length'] = $range['end'] - $range['start'] + 1;
        if ($this->app->config('Route')->get('downloadResume', false))
            $this->app->response->header('Accenpt-Ranges', 'bytes');
        $range['resume'] = !((int)$range['start'] === 0 && (int)$range['end'] === $fileSize - 1);
        return $range;
    }

    /**
     * @param string $content
     * @param bool $statusCode
     * @return bool
     */
    final public function output($content = '', $statusCode = false)
    {
        if (headers_sent())
            return false;

        switch (gettype($content)) {
            case 'integer':
            case 'double':
            case 'float':
            case 'string':
                $content = (string)$content;
                break;
            case 'array':
            case 'object':
                $content = json_encode($content);
                break;
            case 'resource':
                break;
            default:
                $content = '';
        }

        $webService = !in_array($this->app->getRoles(), ['cronjob', 'daemon'], true);
        if ($this->output || $webService) {

            $needOutput = true;

            //Check is web service
            if ($webService) {

                //Checking Accepted Compress Encoding and compress content
                $acceptEncode = !empty($this->app->request->header("accept_encoding")) ? $this->app->request->header("accept_encoding") : '';
                if (
                    strlen($content) > 1024 &&
                    strpos($acceptEncode, 'deflate') !== false &&
                    $this->generalConfig->get('compressOutput', false) === true
                ) {
                    $content = gzdeflate($content, 9);
                    $this->app->response->header("Content-Encoding", "deflate", true);
                }

                //Output content length and expire/cache control
                $length = strlen($content);
                $this->app->response->setHeaders([
                    "Cache-Control" => "max-age={$this->expiryTtl}, must-revalidate",
                    "Expires" => gmdate("D, d M Y H:i:s", (int)Common::timestamp() + $this->expiryTtl) . " GMT",
                    "Content-Length" => $length,
                ], true);

                //Response ETag for cache validate and check and return 304 if not modified
                if ($length > 0) {
                    $eTag = sprintf('%s-%s-%s', mb_strlen($content), sha1($content), hash('crc32b', $content));
                    $this->app->response->header("ETag", $eTag, true);
                    $requestETag = !empty($this->app->request->header("if_none_match")) ? trim($this->app->request->header("if_none_match")) : false;
                    if ($requestETag === $eTag && $this->cache && $statusCode === false) {
                        $statusCode = 304;
                        $needOutput = false;
                    }
                }

                //Send Http Status
                if (!empty($statusCode) && (int)$statusCode > 0)
                    http_response_code($statusCode);

                //Send Out Header
                $headers = $this->app->response->getHeaders();
                foreach ($headers as $headerName => $headerContent)
                    header("{$headerName}: {$headerContent}");
            }

            //StdOut if output needed
            if ($needOutput)
                echo $content;
        }

        ignore_user_abort(true);
        flush();
        clearstatcache();
        return true;
    }
}