<?php
declare(strict_types=1);

namespace Npf\Core {

    use JetBrains\PhpStorm\Pure;
    use Npf\Exception\DBQueryError;
    use Npf\Exception\InternalError;
    use Npf\Exception\NextTick;
    use Npf\Exception\UnknownClass;
    use ReflectionClass;
    use ReflectionException;
    use Twig\Error\LoaderError;
    use Twig\Error\RuntimeError;
    use Twig\Error\SyntaxError;

    /**
     * Class Lock
     * @package Library
     */
    class Route
    {
        /**
         * @var Container
         */
        private Container $generalConfig;
        /**
         * @var Container
         */
        private Container $routeConfig;
        /**
         * @var string
         */
        private string $appFile;
        /**
         * @var array
         */
        private array $appPath = [];
        /**
         * @var string Root Directory
         */
        private string $indexFile;
        /**
         * @var string Root Directory
         */
        private string $homeDirectory;
        /**
         * @var string Root Directory
         */
        private string $rootDirectory;
        /**
         * @var string Default Web Route
         */
        private string $defaultWebRoute;

        /**
         * @var bool Is Static Route
         */
        private bool $isStatic = false;

        private ?array $routeTable = null;

        /**
         * Route constructor.
         * @param App $app
         * @throws DBQueryError
         * @throws InternalError
         * @throws LoaderError
         * @throws RuntimeError
         * @throws SyntaxError
         */
        final public function __construct(private App $app)
        {
            $this->generalConfig = $this->app->config('General');
            $this->routeConfig = $this->app->config('Route');
            if (
                !in_array($app->getRoles(), ['cronjob', 'daemon'], true) &&
                $this->routeConfig->get('forceSecure', false)
            )
                $app->forceSecure();
            $this->rootDirectory = $this->routeConfig->get('rootDirectory', 'App');
            $this->homeDirectory = $this->routeConfig->get('homeDirectory', 'Index');
            $this->indexFile = $this->routeConfig->get('indexFile', 'Index');
            $this->defaultWebRoute = !in_array($app->getRoles(), ['cronjob', 'daemon'], true) ? (string)$this->routeConfig->get('defaultWebRoute', '') : '';
            $pathInfo = isset($_SERVER['REQUEST_URI']) ? explode("?", $_SERVER['REQUEST_URI'])[0] : '';
            $this->app->request->setPathInfo($pathInfo);
            $pathInfo = preg_replace('#^/\w+\.php#', '', $pathInfo);
            $pathInfo = (!$pathInfo || $pathInfo === '/') ?
                $this->indexFile : (str_starts_with($pathInfo, '/') ? substr($pathInfo, 1) : $pathInfo);
            $this->proceedAppPath($pathInfo);
            clearstatcache();
        }

        /**
         * @param string $pathInfo
         */
        private function proceedAppPath(string $pathInfo)
        {
            $this->appPath = array_values(explode("\\", str_replace('/', '\\', $pathInfo)));
            if (empty($this->appPath))
                $this->appPath[] = $this->indexFile;
            $endPath = end($this->appPath);
            if (empty($endPath))
                $this->appPath[key($this->appPath)] = $this->indexFile;
            $appRootPath = reset($this->appPath);
            $addHomeDirectory = false;
            if (count($this->appPath) <= 1 && !empty($this->homeDirectory)) {
                $addHomeDirectory = true;
                $appRootPath = $this->homeDirectory;
                array_unshift($this->appPath, $this->homeDirectory);
            }

            $this->appFile = implode("\\", $this->appPath);
            $this->routeTableMap();
            if (count($this->appPath) > 1 && $addHomeDirectory && $appRootPath === $this->homeDirectory) {
                if (
                    !$this->isExistsAppClass($this->appFile) &&
                    !$this->isExistsStaticFile($this->appFile) &&
                    !empty($this->homeDirectory)
                ) {
                    array_shift($this->appPath);
                    $this->appFile = implode("\\", $this->appPath);
                    $this->routeTableMap();
                }
            }
            if (
                $this->routeConfig->get('routeStaticFile', false) &&
                $this->isExistsStaticFile($this->appFile) &&
                !$this->isExistsAppClass($this->appFile)
            )
                $this->isStatic = true;
        }

        /**
         * Route Table Mapping
         */
        private function routeTableMap()
        {
            if ($this->routeTable === null) {
                $this->routeTable = [];
                $routeTableFile = $this->app->getRootPath() . "route.table";
                if (is_file($routeTableFile) && ($routeTable = file($routeTableFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)))
                    $this->routeTable = array_combine(array_map('strtolower', $routeTable), $routeTable);
            }
            $lowerCase = strtolower("{$this->rootDirectory}\\{$this->appFile}");
            do {
                if (!empty($this->routeTable[$lowerCase])) {
                    $appFile = str_replace("{$this->rootDirectory}\\", "", $this->routeTable[$lowerCase]);
                    if (str_ends_with($appFile, "\\"))
                        $appFile .= $this->indexFile;
                    $this->appFile = $appFile;
                    $this->appPath = explode("\\", $appFile);
                    break;
                }
                $lowerCase = substr($lowerCase, 0, (int)strrpos($lowerCase, "\\", -2)) . "\\";
            } while ($lowerCase != '\\');
        }

        /**
         * @throws InternalError
         * @throws UnknownClass
         */
        final public function __invoke()
        {
            //App Router
            $routePath = explode("\\", "{$this->rootDirectory}\\{$this->appFile}");
            $routerFound = false;
            do {
                array_pop($routePath);
                $routeClass = implode("\\", $routePath) . "\\Router";
                if (class_exists($routeClass)) {
                    $routerObj = new $routeClass($this->app, $this);
                    if (!empty($routerObj) && method_exists($routerObj, '__invoke')) {
                        $params = $routerObj($this->app, $this);
                        unset($routerObj);
                        $routerFound = true;
                        break;
                    }
                }
            } while (!empty($routePath));
            if (!$routerFound)
                throw new UnknownClass("URI Router class not found: {$this->appFile}");

            $this->app->request->setUri($this->appFile);

            //CORS Support
            if ($this->app->request->getMethod() === 'OPTIONS') {
                $this->app->view->setNone();
                $this->app->view->lock();
                $this->app->response->statusCode($this->isExistsAppClass($this->appFile) ? 200 : 404);
                return;
            }

            //Route Static File
            if ($this->isStatic)
                $this->routeStatic();
            else {
                //Route to app

                //Route Default Uri if not found
                if (!$this->isExistsAppClass($this->appFile) && !empty($this->defaultWebRoute))
                    $this->proceedAppPath($this->defaultWebRoute);
                if (!$this->isExistsAppClass($this->appFile))
                    throw new UnknownClass("URI app class not found: {$this->appFile}");

                //Prepare Parameter
                $parameters = [&$this->app];
                if (!empty($params) && !is_array($params))
                    $parameters[] = &$params;
                elseif (!empty($params) && is_array($params))
                    foreach ($params as &$item)
                        $parameters[] = &$item;

                //Launch App Class
                $this->launchApp($parameters);
            }
        }

        /**
         * @throws InternalError
         * @throws UnknownClass
         */
        private function routeStatic(): void
        {
            if ($this->isExistsStaticFile($this->appFile)) {
                $staticFile = file_exists($this->appFile) ?
                    $this->appFile :
                    $this->app->getRootPath() .
                    str_replace("\\", "/", "{$this->rootDirectory}\\{$this->appFile}");
                $this->app->view->setView('static', $staticFile);
                $this->app->view->lock();
            } else
                throw new UnknownClass("URI static file not found: {$this->appFile}");
        }

        /**
         * @param array $parameters
         * @throws InternalError
         * @throws UnknownClass
         */
        private function launchApp(array $parameters = []): void
        {
            $this->app->request->setUri($this->appFile);
            $refClass = new ReflectionClass("{$this->rootDirectory}\\{$this->appFile}");
            switch ($this->app->getRoles()) {

                case 'cronjob':
                    $this->launchCronjob($refClass, $parameters);
                    break;

                case 'daemon':
                    $this->launchDaemon($refClass, $parameters);
                    break;

                default:
                    $this->launchWeb($refClass, $parameters);
                    break;
            }
        }

        /**
         * @param ReflectionClass $refClass
         * @param array $parameters
         * @throws UnknownClass
         * @throws InternalError
         */
        private function launchCronjob(ReflectionClass $refClass, array $parameters = [])
        {
            $cronLock = $this->app->config('Redis')->get('enable', false) && $this->generalConfig->get('cronLock', false);
            $cronBlock = sha1($refClass->getFileName() . $this->app->request);
            $lockName = "cronjob:{$this->app->getAppEnv()}:{$this->app->getAppName()}:{$cronBlock}";
            if ($cronLock && !$this->app->lock->waitAcquireDone($lockName, 60, $this->generalConfig->get('cronMaxWait', 60)))
                return;
            if ($cronLock)
                $this->app->on('appBeforeClean', function (App $app) use ($lockName) {
                    $app->lock->release($lockName, true);
                });
            try {
                $actionObj = $refClass->newInstanceArgs($parameters);
            } catch (ReflectionException $ex) {
                throw new UnknownClass($ex->getMessage());
            }
            $cronjobTtl = property_exists($actionObj, 'cronjobTtl') ? (int)$actionObj->cronjobTtl : (int)$this->generalConfig->get('cronjobTtl', 300);
            if ($cronLock && !empty($cronjobTtl))
                $this->app->lock->expire($lockName, $cronjobTtl);
            if (method_exists($actionObj, '__invoke')) {
                $actionObj->__invoke(...$parameters);
                unset($actionObj);
            } else
                throw new UnknownClass('Class(' . get_class($actionObj) . ') __invoke Not Found');
        }

        /**
         * @param ReflectionClass $refClass
         * @param array $parameters
         * @throws InternalError|UnknownClass
         */
        private function launchDaemon(ReflectionClass $refClass, array $parameters = [])
        {
            set_time_limit(0);
            $daemonBlock = sha1($refClass->getFileName() . $this->app->request);
            $lockName = "daemon:{$this->app->getAppEnv()}:{$this->app->getAppName()}:{$daemonBlock}";
            $daemonLock = $this->app->config('Redis')->get('enable', false) && $this->generalConfig->get('daemonLock', false);
            if ($daemonLock && !$this->app->lock->waitAcquireDone($lockName, 60, $this->generalConfig->get('daemonMaxWait', 60)))
                return;
            $this->app->on('appBeforeClean', function (App $app) use ($lockName) {
                $app->lock->release($lockName, true);
            });
            $this->app->onTermSignal(function (App $app) use ($lockName) {
                $app->lock->release($lockName, true);
            });

            try {
                $actionObj = $refClass->newInstanceArgs($parameters);
            } catch (ReflectionException $ex) {
                throw new UnknownClass($ex->getMessage());
            }
            $daemonTtl = property_exists($actionObj, 'daemonTtl') ? (int)$actionObj->daemonTtl : (int)$this->generalConfig->get('daemonTtl', 300);
            $daemonInterval = property_exists($actionObj, 'daemonInterval') ? (int)$actionObj->daemonInterval : (int)$this->generalConfig->get('daemonInterval', 1000);
            if (method_exists($actionObj, '__invoke')) {
                $this->app->onTick(function () use ($daemonLock, $lockName, $daemonTtl, $actionObj, $parameters) {
                    try {
                        if ($daemonLock)
                            $this->app->lock->extend($lockName, $daemonTtl);
                        $actionObj->__invoke(...$parameters);
                        $this->app->dbCommit();
                    } catch (NextTick) {
                        $this->app->dbRollback();
                    }
                }, 1, 'loop');
                $this->app->launchTimer($daemonTtl, $daemonInterval);
                unset($actionObj);
            } else
                throw new InternalError('Class(' . get_class($actionObj) . ') __invoke Not Found');
        }

        /**
         * @param ReflectionClass $refClass
         * @param array $parameters
         * @throws UnknownClass|
         */
        private function launchWeb(ReflectionClass $refClass, array $parameters = [])
        {
            try {
                //CORS Support
                if ($this->app->request->getMethod() === 'OPTIONS') {
                    $this->app->view->setNone();
                    $this->app->response->statusCode(200);
                    $this->app->view->lock();
                    return;
                }
                $actionObj = $refClass->newInstanceArgs($parameters);
            } catch (ReflectionException $ex) {
                throw new UnknownClass($ex->getMessage());
            }
            if (method_exists($actionObj, '__invoke')) {
                call_user_func_array([$actionObj, '__invoke'], $parameters);
                unset($actionObj);
            } else
                throw new UnknownClass('Class(' . get_class($actionObj) . ') __invoke Not Found');
        }

        /**
         * @param $className
         * @return bool
         */
        final public function isExistsAppClass($className): bool
        {
            return class_exists("{$this->rootDirectory}\\{$className}");
        }

        /**
         * @param $fileName
         * @return bool
         */
        final public function isExistsStaticFile($fileName): bool
        {
            if (file_exists($fileName) && !is_dir($fileName))
                return true;
            $staticFile = $this->app->getRootPath() . str_replace("\\", "/", "{$this->rootDirectory}\\{$fileName}");
            return file_exists($staticFile) && !is_dir($staticFile);
        }

        /**
         * @return string
         */
        final public function getRequestUri(): string
        {
            return '/' . str_replace('\\', '/', $this->appFile);
        }

        /**
         * @return string
         */
        final public function getStaticFile(): string
        {
            return $this->isStatic ? $this->appFile : "";
        }

        /**
         * @return string
         */
        final public function getAppClass(): string
        {
            return $this->isStatic ? "" : $this->appFile;
        }

        /**
         * @param string $className
         * @return self
         */
        final public function setAppClass(string $className): self
        {
            $this->appFile = $className;
            $this->isStatic = false;
            return $this;
        }

        /**
         * @param string $fileName
         * @return self
         */
        final public function setStaticFile(string $fileName): self
        {
            $this->appFile = $fileName;
            $this->isStatic = true;
            return $this;
        }

        /**
         * @return string
         */
        #[Pure] final public function getAppPath(): string
        {
            return implode('\\', $this->appPath);
        }
    }
}