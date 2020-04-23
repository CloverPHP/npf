<?php


namespace Npf\Core;

use Aptoma\Twig\Extension\MarkdownEngine\MichelfMarkdownEngine;
use Aptoma\Twig\Extension\MarkdownExtension;
use finfo;
use Npf\Exception\InternalError;
use ReflectionException;
use SimpleXMLElement;
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

    private $cache = true;

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
     * @param $twigExtension
     */
    final public function addTwigExtension($twigExtension)
    {
        if (empty($twigExtension) && is_string($twigExtension) || is_object($twigExtension))
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
     * Setup View File
     * @param string $type
     * @param null $data
     * @param int $viewLevel
     * @throws InternalError
     */
    final public function setView($type, $data = null, $viewLevel = 1)
    {
        if (!empty($type) && is_string($type)) {
            switch ($type) {
                case 'json':
                case 'xml':
                case 'none':
                case false:
                    $this->type = $type;
                    if ($this->type === false)
                        $this->type = 'none';
                    $this->data = null;
                    break;
                case'plain':
                    $this->type = 'plain';
                    $this->data = $data;
                    break;
                case'static':
                    if (file_exists($data) && !is_dir($data)) {
                        $this->type = 'static';
                        $this->data = $data;
                    } else
                        throw new InternalError('Static File Not found on app.view.setView');
                    break;
                case 'twig':
                    $this->type = 'twig';
                    if (empty($path) && $callerInfo = $this->app->getCallerInfo($viewLevel))
                        $path = Common::strPop('/', $callerInfo['file']);
                    if (!file_exists($path))
                        $path = '';
                    $this->data = [
                        'file' => $data,
                        'path' => $path,
                    ];
                    break;
                default:
                    throw new InternalError('Unknown View Type on app.view.setView');
                    break;
            }
        }
    }

    /**
     * @throws InternalError
     * @throws LoaderError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    final public function error()
    {
        $response = $this->app->response->fetch();
        if (false !== $response['statusCode'])
            http_response_code($response['statusCode']);
        $data = $response['body'];
        if (!$this->app->profiler->enable())
            unset($data['profiler']);
        $errorDisplay = $this->app->config('Profiler')->get('errorOutput');
        switch ($errorDisplay) {

            case 'html':
            case 'twig':
                $corePath = Common::strPop('/', str_replace('\\', '/', __FILE__));
                $mainPath = Common::strPop('/', $corePath);
                $this->addTwigPath($mainPath);
                $this->data = $this->app->config('Profiler')->get('errorTwig', 'error.twig');
                $this->renderTwig($data);
                break;

            case 'xml':
                $this->renderXml($data);
                break;

            case 'none':
                $this->renderNone(500);
                break;

            case 'static':
                $this->renderStaticFile();
                break;

            default:
                $this->renderJson($data);
                break;
        }
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
        if (false !== $response['statusCode'])
            http_response_code($response['statusCode']);
        $data = $response['body'];
        if (!$this->app->profiler->enable())
            unset($data['profiler']);

        switch ($this->type) {

            case 'plain':
                $this->renderPlan();
                break;

            case 'xml':
                $this->renderXml($data);
                break;

            case 'json':
                $this->renderJson($data);
                break;

            case 'none':
                $this->renderNone(204);
                break;

            case 'static':
                $this->renderStaticFile();
                break;

            case 'twig':
                if (!empty($this->data['file']) && !empty($this->data['path'])) {
                    $this->addTwigPath($this->data['path']);
                    $this->data = $this->data['file'];
                    $this->renderTwig($data);
                } else
                    throw new InternalError('View Twig info is incomplete on app.view.render');
                break;

            default:
                throw new InternalError('Unknown View Type on app.view.render');
        }
    }

    /**
     * Render None Response
     * @param int $statusCode
     */
    final private function renderNone($statusCode = 204)
    {
        $this->app->response->statusCode($statusCode);
        $this->output();
    }

    /**
     * Render Plan Text
     */
    final private function renderPlan()
    {
        $this->app->response->header('Content-Type', 'text/plain; charset=utf-8', true);
        $this->output($this->data);
    }

    /**
     * @param $data
     */
    final private function renderXml($data)
    {
        $rootName = $this->generalConfig->get('xmlRoot', 'root');
        $xml = new SimpleXMLElement("<{$rootName}/>");
        array_walk_recursive($data, [$xml, 'addChild']);
        $this->app->response->header('Content-Type', 'text/xml; charset=utf-8', true);
        $this->output($xml->asXML());
    }

    /**
     * @param $data
     */
    final private function renderJson($data)
    {
        $jsonFlag = JSON_UNESCAPED_UNICODE;
        if ($this->app->getEnv() !== 'production' && $this->generalConfig->get('printPretty', false))
            $jsonFlag |= JSON_PRETTY_PRINT;
        $this->app->response->header('Content-Type', 'application/json; charset=utf-8', true);
        $this->output(json_encode($data, $jsonFlag));
    }

    /**
     * @throws InternalError
     */
    final private function renderStaticFile()
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
            if ($fileSize <= 0)
                http_response_code(204);
            if ((int)$requestLastModified === (int)$lastModified && $eTag === $requestETag && $this->cache)
                http_response_code(304);
            else {
                ignore_user_abort(true);
                readfile($this->data);
                clearstatcache();
            }
        } else
            http_response_code(404);
    }

    /**
     * @param $data
     * @throws InternalError
     * @throws LoaderError
     * @throws ReflectionException
     * @throws RuntimeError
     * @throws SyntaxError
     */
    final private function renderTwig($data)
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
        $this->twigExtension[] = new MarkdownExtension(new MichelfMarkdownEngine());

        if ($configExtensions = $this->app->config('Twig')->get('extension')) {
            if (!empty($configExtensions)) {
                if (is_array($configExtensions))
                    $this->twigExtension = array_merge($this->twigExtension, $configExtensions);
                else
                    $this->twigExtension[] = $configExtensions;
            }
        }
        foreach ($this->twigExtension as $extension) {
            if (is_string($extension))
                if (!class_exists($extension))
                    throw new InternalError("Twig Extension {$extension} not found");
            if (!is_object($extension))
                $extension = new $extension($this, $twig);
            if (!is_object($extension))
                throw new InternalError("Twig Extension is not an object.\n" . json_encode($extension));
            $twig->addExtension($extension);
        }
        if ($syntaxStyle = $this->app->config('Twig')->get('syntaxStyle'))
            $twig->setLexer(new Lexer($twig, $syntaxStyle));
        $appendHeader = $this->app->config('Twig')->get('appendHeader');
        $this->app->response->setHeaders($appendHeader);
        $this->app->response->header('Content-Type', 'text/html; charset=utf-8');
        $this->output($twig->render($this->data, $data));
    }

    /**
     * @param $content
     * @return bool
     */
    final public function output($content = '')
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

        $acceptEncode = !empty($this->app->request->header("accept_encoding")) ? $this->app->request->header("accept_encoding") : '';
        if (
            strlen($content) > 1024 &&
            strpos($acceptEncode, 'deflate') !== (boolean)false &&
            $this->generalConfig->get('compressOutput', false) === true
        ) {
            $content = gzdeflate($content, 9);
            $this->app->response->header("Content-Encoding", "deflate", true);
        }

        $webService = !in_array($this->app->getRoles(), ['cronjob', 'daemon'], true);
        $eTag = sprintf('%s-%s-%s', mb_strlen($content), sha1($content), hash('crc32b', $content));
        if ($webService) {
            $length = strlen($content);
            $this->app->response->setHeaders([
                "Cache-Control" => "max-age=0, must-revalidate",
                "Expires" => gmdate("D, d M Y H:i:s", (int)Common::timestamp()) . " GMT",
                "Content-Length" => $length,
            ], true);
            if ($length > 0)
                $this->app->response->header("ETag", $eTag, true);
        }

        //Additional Header add-on
        $headers = $this->app->response->getHeaders();
        foreach ($headers as $headerName => $headerContent)
            header("{$headerName}: {$headerContent}");

        if ($this->output || $webService) {
            $output = true;
            if ($webService) {
                $requestETag = !empty($this->app->request->header("if_none_match")) ? trim($this->app->request->header("if_none_match")) : false;
                if ($requestETag === $eTag && $this->cache) {
                    $responseCode = http_response_code();
                    if ($responseCode === 200 || $responseCode === false)
                        http_response_code(304);
                    $output = false;
                }
            }
            if ($output)
                echo $content;
        }

        ignore_user_abort(true);
        flush();
        clearstatcache();
        return true;
    }
}