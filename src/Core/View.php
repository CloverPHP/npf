<?php
declare(strict_types=1);

namespace Npf\Core;

use Throwable;
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
     * @var string
     */
    private string $type = '';

    /**
     * @var mixed
     */
    private mixed $data = false;

    /**
     * @var array
     */
    private array $twigExtension = [];

    /**
     * @var array
     */
    private array $twigPath = [];

    /**
     * @var bool
     */
    private bool $output = false;

    /**
     * @var Container
     */
    private Container $generalConfig;

    /**
     * @var bool Cache Control
     */
    private bool $cache = true;

    /**
     * @var bool Lock Current View
     */
    private bool $lockView = false;

    /**
     * @var int Expiry Ttl
     */
    private int $expiryTtl = 0;

    /**
     * View constructor.
     * @param App $app
     */
    final public function __construct(private App $app)
    {
        if (in_array($app->getRoles(), ['daemon', 'cronjob'], true)) {
            $opts = getopt('o', ['output']);
            if (is_array($opts) && !empty($opts))
                $this->output = true;
        } else
            $this->output = (boolean)$app->response->get('output', false);
        try {
            $this->generalConfig = $app->config('General', true);
        } catch (Throwable) {
            $this->generalConfig = new Container();
        }
        $this->type = $this->generalConfig->get('defaultOutput', 'json');
    }

    /**
     * @return string
     */
    final public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return self
     */
    final public function cached(): self
    {
        $this->cache = true;
        return $this;
    }

    /**
     * @return self
     */
    final public function noCache(): self
    {
        $this->cache = false;
        return $this;
    }

    /**
     * @return self
     */
    final public function lock(): self
    {
        $this->lockView = true;
        return $this;
    }

    /**
     * @return self
     */
    final public function unlock(): self
    {
        $this->lockView = false;
        return $this;
    }

    /**
     * @param $expireTtl
     * @return self
     */
    final public function setViewExpiry($expireTtl): self
    {
        $this->expiryTtl = (int)$expireTtl;
        if ($this->expiryTtl < 0)
            $this->expiryTtl = 0;
        return $this;
    }

    /**
     * @param string|object|array $twigExtension
     * @return self
     */
    final public function addTwigExtension(string|object|array $twigExtension): self
    {
        if (!empty($twigExtension) && (is_string($twigExtension) || is_object($twigExtension)))
            $this->twigExtension[] = $twigExtension;
        if (is_array($twigExtension))
            $this->twigExtension = array_merge($this->twigExtension, $twigExtension);
        return $this;
    }

    /**
     * @param string $path
     * @param ?string $name
     */
    final public function addTwigPath(string $path, ?string $name = null)
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
     * @return self
     */
    final public function setNone(): self
    {
        if ($this->lockView)
            return $this;
        $this->type = 'none';
        $this->data = null;
        return $this;
    }

    /**
     * Set View as none type
     * @param $content
     * @return self
     */
    final public function setPlain($content): self
    {
        if ($this->lockView)
            return $this;
        if (!empty($content)) {
            $this->type = 'plain';
            $this->data = $content;
        }
        return $this;
    }

    /**
     * Set View as json type
     * @return self
     */
    final public function setJson(): self
    {
        if ($this->lockView)
            return $this;
        $this->type = 'json';
        $this->data = null;
        return $this;
    }

    /**
     * @param null $rooTag
     * @return self
     */
    final public function setXml($rooTag = null): self
    {
        if ($this->lockView)
            return $this;
        $this->type = 'xml';
        if (empty($rooTag) || !is_string($rooTag))
            $rooTag = $this->generalConfig->get('xmlRoot', 'root');
        $this->data = $rooTag;
        return $this;
    }

    /**
     * @param string $file
     * @param string|array|null $paths
     * @param int $viewLevel
     * @return self
     */
    final public function setTwig(string $file, string|array $paths = null, int $viewLevel = 1): self
    {
        if ($this->lockView)
            return $this;
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
        return $this;
    }

    /**
     * @param string|null $file
     * @return self
     * @throws InternalError
     */
    final public function setStatic(?string $file = null): self
    {
        if ($this->lockView)
            return $this;
        if (file_exists($file) && !is_dir($file)) {
            $this->type = 'static';
            $this->data = $file;
        } else
            throw new InternalError('Static File Not found on app.view.setView');
        return $this;
    }

    /**
     * Setup View File
     * @param string $type
     * @param mixed $data
     * @param int $viewLevel
     * @return self
     * @throws InternalError
     */
    final public function setView(string $type,
                                  string|array|null $data = null,
                                  int $viewLevel = 1): self
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
        return $this;
    }

    /**
     * @return self
     * @throws InternalError
     */
    final public function error(): self
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
        return $this;
    }

    /**
     * @return self
     * @throws InternalError
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    final public function render(): self
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
        return $this;
    }

    /**
     * Render None Response
     * @param bool|int $statusCode
     * @return void
     */
    private function renderNone(bool|int $statusCode = false): void
    {
        $this->output(false, $statusCode);
    }

    /**
     * Render Plan Text
     * @param bool $statusCode
     * @return void
     */
    private function renderPlan(bool $statusCode = false): void
    {
        $this->app->response->header('Content-Type', 'text/plain; charset=utf-8');
        $this->output($this->data, $statusCode);
    }

    /**
     * @param $data
     * @param bool $statusCode
     * @return void
     */
    private function renderJson($data, bool $statusCode = false): void
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
     * @return void
     * @throws InternalError
     */
    private function renderXml($data, bool $statusCode = false): void
    {
        if (empty($this->data) || !is_string($this->data))
            $this->data = $this->generalConfig->get('xmlRoot', 'root');
        try {
            $xml = Xml::createXML($this->data, $data);
        } catch (\Exception $ex) {
            throw new InternalError($ex->getMessage());
        }
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
     * @param array|string $data
     * @param bool|int $statusCode
     * @throws InternalError
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    private function renderTwig(array|string $data, bool|int $statusCode = false)
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
        if (!empty($appendHeader) && is_array($appendHeader))
            $this->app->response->setHeaders($appendHeader);
        $this->app->response->header('Content-Type', 'text/html; charset=utf-8');
        $this->output($twig->render($this->data, $data), $statusCode);
    }

    /**
     * @param bool|int $statusCode
     * @return void
     * @throws InternalError
     */
    private function renderStatic(bool|int $statusCode = false): void
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
                if ($contentType === 'auto')
                    $contentType = mime_content_type($this->data);
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
                'Content-Range' => "bytes {$range['start']}-{$range['end']}/{$fileSize}",
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
                    set_time_limit(0);
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
    private function staticRange($fileSize): array
    {
        $downloadResume = $this->app->config('Route')->get('downloadResume', false);
        if ($downloadResume) {
            $this->app->response->header('Accenpt-Ranges', 'bytes');
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
            $range['resume'] = !((int)$range['start'] === 0 && (int)$range['end'] === $fileSize - 1);
        } else
            $range = [
                'start' => 0,
                'end' => $fileSize - 1,
                'length' => $fileSize,
                'resume' => false,
            ];
        return $range;
    }

    /**
     * @param mixed $content
     * @param bool $statusCode
     * @return self
     */
    final public function output(mixed $content = '', bool|int $statusCode = false): self
    {
        if (headers_sent())
            return $this;

        switch (gettype($content)) {
            case 'integer':
            case 'double':
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
                    str_contains($acceptEncode, 'deflate') &&
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
        return $this;
    }
}