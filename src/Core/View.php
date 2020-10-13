<?php


namespace Npf\Core;

use finfo;
use Npf\Exception\InternalError;
use Npf\Library\Xml;
use ReflectionException;
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
    private $type = 'json';

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
        $this->app->ignoreError();
        $errorDisplay = $this->app->config('Profiler')->get('errorOutput');
        if ($errorDisplay === 'auto')
            $errorDisplay = $this->app->request->isXHR() ? 'json' : 'twig';
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
        $this->app->noticeError();
    }

    /**
     * @throws InternalError
     * @throws LoaderError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    final public function render()
    {
        $response = $this->app->response->fetch();
        if ($this->type === 'none' && !$response['statusCode'])
            $response['statusCode'] = 204;
        $data = $response['body'];
        if (!$this->app->profiler->enable())
            unset($data['profiler']);
        elseif (isset($content['profiler']['debug']))
            $data['profiler']['debug'] = array_values($data['profiler']['debug']);

        switch ($this->type) {

            case 'plain':
                $this->renderPlan(false, $response['statusCode']);
                break;

            case 'xml':
                $this->renderXml($data, false, $response['statusCode']);
                break;

            case 'json':
                $this->renderJson($data, false, $response['statusCode']);
                break;

            case 'none':
                $this->renderNone($response['statusCode']);
                break;

            case 'static':
                $this->renderStaticFile($response['statusCode']);
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
                    $this->renderTwig($data, false, $response['statusCode']);
                } else
                    throw new InternalError('View Twig info is incomplete on app.view.render');
                break;

            default:
                throw new InternalError('Unknown View Type on app.view.render');
        }
    }

    /**
     * Render None Response
     * @param bool $statusCode
     */
    final private function renderNone($statusCode = false)
    {
        $this->output(false, $statusCode);
    }

    /**
     * Render Plan Text
     * @param bool $statusCode
     * @param bool $headerOverWrite
     */
    final private function renderPlan($headerOverWrite = false, $statusCode = false)
    {
        $this->app->response->header('Content-Type', 'text/plain; charset=utf-8', $headerOverWrite);
        $this->output($this->data, $statusCode);
    }

    /**
     * @param $data
     * @param bool $headerOverWrite
     * @param bool $statusCode
     */
    final private function renderJson($data, $headerOverWrite = false, $statusCode = false)
    {
        $jsonFlag = JSON_UNESCAPED_UNICODE;
        if ($this->generalConfig->get('printPretty', false))
            $jsonFlag |= JSON_PRETTY_PRINT;
        $this->app->response->header('Content-Type', 'text/json; charset=utf-8', $headerOverWrite);
        $this->output(json_encode($data, $jsonFlag), $statusCode);
    }

    /**
     * @param $data
     * @param bool $headerOverWrite
     * @param bool $statusCode
     */
    final private function renderXml($data, $headerOverWrite = false, $statusCode = false)
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
        $this->app->response->header('Content-Type', 'text/xml; charset=utf-8', $headerOverWrite);
        $this->output($xml->saveXML($xml->documentElement, LIBXML_NOEMPTYTAG), $statusCode);
    }

    /**
     * @param $data
     * @param bool $headerOverWrite
     * @param bool $statusCode
     * @throws InternalError
     * @throws LoaderError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    final private function renderTwig($data, $headerOverWrite = false, $statusCode = false)
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
        $this->app->response->header('Content-Type', 'text/html; charset=utf-8', $headerOverWrite);
        $this->output($twig->render($this->data, $data), $statusCode);
    }

    /**
     * @param bool $statusCode
     * @throws InternalError
     */
    final private function renderStaticFile($statusCode = false)
    {
        if (!empty($statusCode) && (int)$statusCode > 0)
            http_response_code($statusCode);
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

            $this->app->response->setHeaders([
                "Content-Type" => $contentType,
                "Last-Modified" => gmdate("D, d M Y H:i:s", $lastModified) . " GMT",
                "Cache-Control" => $cacheControl,
                "Expires" => gmdate("D, d M Y H:i:s", (int)Common::timestamp() + $expireTime) . " GMT",
                "Content-Length" => $fileSize,
            ], true);
            if ($fileSize > 0)
                $this->app->response->header("ETag", $eTag, true);

            //Additional Header add-on
            $headers = $this->app->response->getHeaders();
            foreach ($headers as $headerName => $headerContent)
                header("{$headerName}: {$headerContent}");
            if ($fileSize <= 0 && $statusCode === false)
                http_response_code(204);
            if ((int)$requestLastModified === (int)$lastModified && $eTag === $requestETag && $this->cache && $statusCode === false)
                http_response_code(304);
            else {
                ignore_user_abort(true);
                readfile($this->data);
                clearstatcache();
            }
        } elseif ($statusCode === false)
            http_response_code(404);
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

                if (!empty($statusCode) && (int)$statusCode > 0)
                    http_response_code($statusCode);

                //Checking Accepted Compress Encoding and compress content
                $acceptEncode = !empty($this->app->request->header("accept_encoding")) ? $this->app->request->header("accept_encoding") : '';
                if (
                    strlen($content) > 1024 &&
                    strpos($acceptEncode, 'deflate') !== (boolean)false &&
                    $this->generalConfig->get('compressOutput', false) === true
                ) {
                    $content = gzdeflate($content, 9);
                    $this->app->response->header("Content-Encoding", "deflate", true);
                }

                //Output content length and expire/cache control
                $length = strlen($content);
                $this->app->response->setHeaders([
                    "Cache-Control" => "max-age=0, must-revalidate",
                    "Expires" => gmdate("D, d M Y H:i:s", (int)Common::timestamp()) . " GMT",
                    "Content-Length" => $length,
                ], true);

                //Additional Header add-on
                $headers = $this->app->response->getHeaders();
                foreach ($headers as $headerName => $headerContent)
                    header("{$headerName}: {$headerContent}");

                //Response ETag for cache validate and check and return 304 if not modified
                if ($length > 0) {
                    $eTag = sprintf('%s-%s-%s', mb_strlen($content), sha1($content), hash('crc32b', $content));
                    $this->app->response->header("ETag", $eTag, true);
                    $requestETag = !empty($this->app->request->header("if_none_match")) ? trim($this->app->request->header("if_none_match")) : false;
                    if ($requestETag === $eTag && $this->cache && $statusCode === false) {
                        http_response_code(304);
                        $needOutput = false;
                    }
                }
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