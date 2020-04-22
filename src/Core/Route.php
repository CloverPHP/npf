<?php

namespace Npf\Core {

    use Npf\Exception\DBQueryError;
    use Npf\Exception\InternalError;
    use Npf\Exception\NextTick;
    use Npf\Exception\UnknownClass;
    use ReflectionClass;
    use ReflectionException as ReflectionExceptionAlias;
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
         * @var App
         */
        private $app;
        /**
         * @var Container
         */
        private $generalConfig;
        /**
         * @var Container
         */
        private $routeConfig;
        /**
         * @var string
         */
        private $routeClass = '';
        /**
         * @var string
         */
        private $appFile = '';
        /**
         * @var array
         */
        private $appPath = [];
        /**
         * @var string Root Directory
         */
        private $indexFile = 'Index';
        /**
         * @var string Root Directory
         */
        private $homeDirectory = 'Index';
        /**
         * @var string Root Directory
         */
        private $rootDirectory = 'App';
        /**
         * @var string Default Web Route
         */
        private $defaultWebRoute = '';

        /**
         * @var bool Is Static Route
         */
        private $isStatic = false;

        /**
         * Route constructor.
         * @param App $app
         * @throws DBQueryError
         * @throws InternalError
         * @throws LoaderError
         * @throws ReflectionExceptionAlias
         * @throws RuntimeError
         * @throws SyntaxError
         */
        final public function __construct(App &$app)
        {
            $this->app = &$app;
            $this->generalConfig = $this->app->config('General');
            $this->routeConfig = $this->app->config('Route');
            if (
                !in_array($app->getRoles(), ['cronjob', 'daemon'], true) &&
                (boolean)$this->routeConfig->get('forceSecure', false)
            )
                $app->forceSecure();
            $this->rootDirectory = $this->routeConfig->get('rootDirectory', 'App');
            $this->homeDirectory = $this->routeConfig->get('homeDirectory', 'Index');
            $this->indexFile = $this->routeConfig->get('indexFile', 'Index');
            $this->defaultWebRoute = !in_array($app->getRoles(), ['cronjob', 'daemon'], true) ? (string)$this->routeConfig->get('defaultWebRoute', '') : '';
            $pathInfo = isset($_SERVER['REQUEST_URI']) ? explode("?", $_SERVER['REQUEST_URI'])[0] : '';
            $pathInfo = preg_replace('#^/\w+\.php#', '', $pathInfo);
            $pathInfo = (!$pathInfo || $pathInfo === '/') ?
                $this->indexFile : (substr($pathInfo, 0, 1) === '/' ? substr($pathInfo, 1) : $pathInfo);
            $this->proceedAppPath($pathInfo);
            clearstatcache();
        }

        /**
         * @param $pathInfo
         * @throws ReflectionExceptionAlias
         */
        final private function proceedAppPath($pathInfo)
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
            if (count($this->appPath) > 1 && $addHomeDirectory && $appRootPath === $this->homeDirectory) {
                if (
                    !$this->isExistsAppClass($this->appFile) &&
                    !$this->isExistsStaticFile($this->appFile) &&
                    !empty($this->homeDirectory) &&
                    $addHomeDirectory
                ) {
                    array_shift($this->appPath);
                    $this->appFile = implode("\\", $this->appPath);
                }
            }
            if (
                $this->routeConfig->get('routeStaticFile', false) &&
                $this->isExistsStaticFile($this->appFile) &&
                !$this->isExistsAppClass($this->appFile)
            )
                $this->isStatic = true;
            $this->routeClass = (!empty($appRootPath) ? "{$appRootPath}\\" : "") . "Router";
            if (!$this->isExistsAppClass($this->routeClass))
                $this->routeClass = "Router";
        }

        /**
         * @throws InternalError
         * @throws ReflectionExceptionAlias
         * @throws UnknownClass
         */
        final public function __invoke()
        {
            if ($this->app->request->getMethod() === 'OPTIONS') {
                $this->app->view->setView('none');
                return;
            }
            $appRouteClass = "{$this->rootDirectory}\\{$this->routeClass}";
            $routerObj = new $appRouteClass($this->app, $this);

            //Route Default Uri if not found
            $appFile = $this->appFile;
            if (!$this->isStatic && !$this->isExistsAppClass($this->appFile) && !empty($this->defaultWebRoute))
                $this->proceedAppPath($this->defaultWebRoute);
            if (!$this->isExistsAppClass($this->routeClass))
                throw new UnknownClass("URI app router class not found: {$this->routeClass}", '', 'error', ['uri' => $appFile]);

            $this->app->request->setUri($this->appFile);
            if ($this->isStatic)
                $this->routeStatic();
            elseif (method_exists($routerObj, '__invoke')) {

                //Execute Route Class for Sub App Prepare parameter
                $params = $routerObj->__invoke($this->app);

                //Prepare Parameter
                $parameters = [&$this->app];
                if (!empty($params) && !is_array($params)) {
                    $parameters[] = &$params;
                } elseif (!empty($params) && is_array($params)) {
                    foreach ($params as &$item)
                        $parameters[] = &$item;
                } elseif (!is_array($params))
                    throw new InternalError('Router.__invoke must return array for action parameters');

                //Launch App Class
                $this->launchApp($parameters);

                //Remove Router Object
                unset($routerObj);

            } else
                throw new UnknownClass('Router is not available.');
        }

        /**
         * @throws InternalError
         * @throws ReflectionExceptionAlias
         * @throws UnknownClass
         */
        final private function routeStatic()
        {
            if ($this->isExistsStaticFile($this->appFile)) {
                $staticFile = $this->app->getRootPath() . str_replace("\\", "/", "{$this->rootDirectory}\\{$this->appFile}");
                $this->app->view->setView('static', $staticFile);
            } else
                throw new UnknownClass("URI static file not found: {$this->appFile}");
        }

        /**
         * @param array $parameters
         * @throws InternalError
         * @throws UnknownClass
         */
        final private function launchApp(array &$parameters = [])
        {
            try {
                if ($this->isExistsAppClass($this->appFile)) {
                    $this->app->request->setUri($this->appFile);
                    $refClass = new ReflectionClass("{$this->rootDirectory}\\{$this->appFile}");
                } else {
                    if (!empty($this->defaultWebRoute))
                        $this->proceedAppPath($this->defaultWebRoute);
                    if (empty($this->defaultWebRoute) || !$this->isExistsAppClass($this->appFile))
                        throw new UnknownClass("URI app class not found: {$this->appFile}");
                    else
                        $refClass = new ReflectionClass("{$this->rootDirectory}\\{$this->appFile}");
                }
            } catch (ReflectionExceptionAlias $ex) {
                throw new UnknownClass($ex->getMessage());
            }
            switch ($this->app->getRoles()) {

                case 'cronjob':
                    $this->launchCronjobRole($refClass, $parameters);
                    break;

                case 'daemon':
                    $this->launchDaemonRole($refClass, $parameters);
                    break;

                default:
                    $this->launchWebRole($refClass, $parameters);
                    break;
            }
        }

        /**
         * @param ReflectionClass $refClass
         * @param array $parameters
         * @throws UnknownClass
         * @throws InternalError
         */
        final private function launchCronjobRole(ReflectionClass &$refClass, array &$parameters = [])
        {
            $cronLock = $this->app->config('Redis')->get('enable', false) && $this->generalConfig->get('cronLock', false);
            $cronBlock = sha1($this->app->request);
            $lockName = "cronjob:{$this->app->getEnv()}:{$this->app->getAppName()}:{$this->rootDirectory}\\{$this->appFile}:{$cronBlock}";
            if ($cronLock && !$this->app->lock->waitAcquireDone($lockName, 60, $this->generalConfig->get('cronMaxWait', 60)))
                return;
            if ($cronLock)
                $this->app->on('appBeforeClean', function (App $app) use ($lockName) {
                    $app->lock->release($lockName, true);
                });
            $actionObj = $refClass->newInstanceArgs($parameters);
            $cronjobTtl = property_exists($actionObj, 'cronjobTtl') ? (int)$actionObj->cronjobTtl : (int)$this->generalConfig->get('cronjobTtl', 300);
            if ($cronLock && !empty($cronjobTtl))
                $this->app->lock->expire($lockName, $cronjobTtl);
            if (method_exists($actionObj, '__invoke')) {
                call_user_func_array([$actionObj, '__invoke'], $parameters);
                unset($actionObj);
            } else
                throw new UnknownClass('Class(' . get_class($actionObj) . ') __invoke Not Found');
        }

        /**
         * @param ReflectionClass $refClass
         * @param array $parameters
         * @throws InternalError
         * @throws UnknownClass
         */
        final private function launchDaemonRole(ReflectionClass &$refClass, array &$parameters = [])
        {
            set_time_limit(0);
            $daemonBlock = sha1($this->app->request);
            $lockName = "daemon:{$this->app->getEnv()}:{$this->app->getAppName()}:{$this->rootDirectory}\\{$this->appFile}:{$daemonBlock}";
            $daemonLock = $this->app->config('Redis')->get('enable', false) && $this->generalConfig->get('daemonLock', false);
            if ($daemonLock && !$this->app->lock->waitAcquireDone($lockName, 60, $this->generalConfig->get('daemonMaxWait', 180)))
                return;
            $this->app->on('appBeforeClean', function (App $app) use ($lockName) {
                $app->lock->release($lockName, true);
            });
            $this->app->onTermSignal(function (App $app) use ($lockName) {
                $app->lock->release($lockName, true);
            });

            $actionObj = $refClass->newInstanceArgs($parameters);
            $daemonTtl = property_exists($actionObj, 'daemonTtl') ? (int)$actionObj->daemonTtl : (int)$this->generalConfig->get('daemonTtl', 300);
            $daemonInterval = property_exists($actionObj, 'daemonInterval') ? (int)$actionObj->daemonInterval : (int)$this->generalConfig->get('daemonInterval', 1000);
            if (method_exists($actionObj, '__invoke')) {
                $this->app->onTick(function () use ($daemonLock, $lockName, $daemonTtl, $actionObj, $parameters) {
                    try {
                        call_user_func_array([$actionObj, '__invoke'], $parameters);
                        $this->app->dbCommit();
                        if ($daemonLock)
                            $this->app->lock->expire($lockName, $daemonTtl);
                    } catch (NextTick $ex) {
                        $this->app->dbRollback();
                    }
                }, 1, 'loop');
                if ($daemonLock)
                    $this->app->lock->expire($lockName, $daemonTtl);
                $this->app->launchTimer($daemonTtl, $daemonInterval);
                unset($actionObj);
            } else
                throw new UnknownClass('Class(' . get_class($actionObj) . ') __invoke Not Found');
        }

        /**
         * @param ReflectionClass $refClass
         * @param array $parameters
         * @throws UnknownClass
         */
        final private function launchWebRole(ReflectionClass &$refClass, array &$parameters = [])
        {
            $actionObj = $refClass->newInstanceArgs($parameters);
            if (method_exists($actionObj, '__invoke')) {
                call_user_func_array([$actionObj, '__invoke'], $parameters);
                unset($actionObj);
            } else
                throw new UnknownClass('Class(' . get_class($actionObj) . ') __invoke Not Found');
        }

        /**
         * @param $className
         * @return string
         */
        final public function isExistsAppClass($className)
        {
            return class_exists("{$this->rootDirectory}\\{$className}");
        }

        /**
         * @param $fileName
         * @return string
         * @throws ReflectionExceptionAlias
         */
        final public function isExistsStaticFile($fileName)
        {
            $staticFile = $this->app->getRootPath() . str_replace("\\", "/", "{$this->rootDirectory}\\{$fileName}");
            return file_exists($staticFile) && !is_dir($staticFile);
        }

        /**
         * @return string
         */
        final public function getStaticFile()
        {
            return $this->isStatic ? $this->appFile : "";
        }

        /**
         * @return string
         */
        final public function getAppClass()
        {
            return $this->isStatic ? "" : $this->appFile;
        }

        /**
         * @param $className
         */
        final public function setAppClass($className)
        {
            $this->appFile = $className;
            $this->isStatic = false;
        }

        /**
         * @param $fileName
         */
        final public function setStaticFile($fileName)
        {
            $this->appFile = $fileName;
            $this->isStatic = true;
        }

        /**
         * @return string
         */
        final public function getAppPath()
        {
            return implode('\\', $this->appPath);
        }
    }
}