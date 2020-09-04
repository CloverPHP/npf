<?php

namespace Npf\Core {

    use Composer\Autoload\ClassLoader;
    use Npf\Exception\DBQueryError;
    use Npf\Exception\InternalError;
    use Npf\Exception\UnknownClass;
    use ReflectionClass;
    use ReflectionException;
    use Twig\Error\LoaderError;
    use Twig\Error\RuntimeError;
    use Twig\Error\SyntaxError;

    /**
     * Class App
     * @package Core
     * @property Db $db
     * @property Redis $redis
     * @property Session $session
     * @property Cookie $cookie
     * @property Container $container
     * @property Request $request
     * @property Response $response
     * @property View $view
     * @property Profiler $profiler
     * @property Lock $lock
     * @property Library $library
     */
    final class App extends Event
    {
        /**
         * @var array
         */
        private $models = [];

        /**
         * @var array
         */
        private $modules = [];

        /**
         * @var array
         */
        private $config = [];

        /**
         * @var array
         */
        private $components = [];

        /**
         * @var Request
         */
        private $request;

        /**
         * @var Response
         */
        private $response;

        /**
         * @var View
         */
        private $view;

        /**
         * @var string Config Path
         */
        private $configPath;

        /**
         * @var string Root Path
         */
        private $rootPath = '';

        /**
         * @var string Base Path
         */
        private $basePath = '';

        /**
         * @var string Environment Name
         */
        private $appEnv = 'local';

        /**
         * @var string App Name
         */
        private $appName = 'defaultApp';

        /**
         * @var string Roles
         */
        private $roles = 'web';

        /**
         * @var bool Ignore Error
         */
        private $ignoreError = false;

        /**
         * @var bool Ignore Error
         */
        private $ignoreException = false;

        /**
         * App constructor.
         * @param string $roles
         * @param string $env
         * @param string $name
         */
        final public function __construct($roles = 'web', $env = 'local', $name = 'defaultApp')
        {
            parent::__construct();
            $this->roles = !empty($roles) ? $roles : 'web';
            $this->appEnv = !empty($env) ? $env : 'local';
            $this->appName = !empty($name) ? $name : 'defaultApp';
            $this->basePath = getcwd();
            $this->configPath = sprintf("Config\\%s\\%s\\", ucfirst($this->appEnv), ucfirst($this->appName));
            $this->request = new Request($this);
            $this->response = new Response(null);
            $this->view = new View($this);
            $this->components['request'] = &$this->request;
            $this->components['response'] = &$this->response;
            $this->components['view'] = &$this->view;
        }

        /**
         * Force redirect https if not secure connection
         * @throws DBQueryError
         * @throws InternalError
         * @throws LoaderError
         * @throws ReflectionException
         * @throws RuntimeError
         * @throws SyntaxError
         */
        final public function forceSecure()
        {
            if (!$this->request->isSecure() && isset($_SERVER["HTTP_HOST"]) && isset($_SERVER["REQUEST_URI"]))
                $this->redirect("https://{$_SERVER["HTTP_HOST"]}{$_SERVER["REQUEST_URI"]}");
        }

        /**
         * Force redirect https if not secure connection
         * @param $url
         * @param int $statsCode
         * @throws DBQueryError
         * @throws InternalError
         * @throws LoaderError
         * @throws ReflectionException
         * @throws RuntimeError
         * @throws SyntaxError
         */
        final public function redirect($url, $statsCode = 302)
        {
            if($this->request->isXHR()){
                $this->response->header('Go', $url, true);
            }else {
                $this->response->statusCode($statsCode);
                $this->response->header('Location', $url, true);
                if ($statsCode >= 300)
                    $this->view->setView('none');
            }
            $this->finishingApp();
        }

        /**
         * App Execute
         * @throws \Exception
         */
        final public function __invoke()
        {
            $this->emit('appStart', [&$this]);
            $route = new Route($this);
            $corsSupport = $this->config('General')->get('corsSupport', false);
            if ($corsSupport !== false)
                $this->corsSupport($corsSupport);
            $route();
            $this->finishingApp();
        }

        /**
         * @return mixed
         * @throws DBQueryError
         * @throws InternalError
         * @throws LoaderError
         * @throws ReflectionException
         * @throws RuntimeError
         * @throws SyntaxError
         */
        final private function finishingApp()
        {
            $profiler = $this->profiler->fetch();
            $this->response->add('profiler', $profiler);
            $this->emit('appEnd', [&$this, $profiler]);
            $this->commit();
            $this->emit('appBeforeClean', [&$this, $profiler]);
            $this->clean();
            $this->view->render();
            exit($this->getRoles() === 'daemon' ? 1 : 0);
        }

        /**
         * @param $name
         * @param bool $ignoreError
         * @return Container
         * @throws InternalError
         */
        final public function config($name, $ignoreError = false)
        {
            if (isset($this->config[$name]))
                return $this->config[$name];
            else {
                $configClass = $this->configPath . $name;
                if ($ignoreError || class_exists($configClass)) {
                    if (!class_exists($configClass))
                        $this->config[$name] = new Container([], true, true);
                    else {
                        $configObj = new $configClass($this);
                        $this->config[$name] = new Container($configObj, true, true);
                    }
                    return $this->config[$name];
                } else
                    throw new InternalError("Config Not Found: '{$name}''");
            }
        }

        /**
         * @param $origin
         * @throws InternalError
         */
        final private function corsSupport($origin)
        {
            $origin = $this->request->header('origin', $origin);
            $this->response->header('Access-Control-Allow-Origin', $origin, true);
            $this->response->header('Access-Control-Allow-Credentials', $this->config('General')->get('corsAllowCredentials', 'true'), true);
            $this->response->header('Access-Control-Allow-Methods', $this->config('General')->get('corsAllowMethod', 'POST,GET,OPTIONS'), true);
            $this->response->header('Access-Control-Allow-Headers', $this->request->header('access_control_request_headers', $origin), true);
            $this->response->header('Access-Control-Max-Age', $this->config('General')->get('corsAge', 3600), true);
        }

        /**
         * App Commit
         * @throws DBQueryError
         */
        final public function commit()
        {
            $this->emit('beforeCommit', [&$this]);
            $this->dbCommit();

            if (isset($this->components['session']) && $this->components['session'] instanceof Session)
                $session = $this->components['session'];

            if (isset($session) && $session instanceof Session)
                $session->close();

            $this->emit('afterCommit', [&$this]);
        }

        /**
         * DB Commit
         * @throws DBQueryError
         */
        final public function dbCommit()
        {
            $this->emit('dbCommit', [&$this]);
            if (isset($this->components['db']) && $this->components['db'] instanceof Db)
                $db = $this->components['db'];

            if (isset($db) && $db instanceof Db && $db->isConnected()) {
                if ($db->commit()) {
                    $this->emit('commitDone', [&$this]);
                    return true;
                } else {
                    $this->emit('commitFailed', [&$this]);
                    return true;
                }
            }
            return true;
        }

        /**
         * App Components Clean Up
         */
        final public function clean()
        {
            foreach ($this->components as $name => &$component) {
                if (!in_array($name, ['request', 'response', 'profiler'], true)) {
                    if (method_exists($component, '__destruct'))
                        $component->__destruct();
                    unset($this->components[$name]);
                }
            }
        }

        /**
         * @return string
         * @throws ReflectionException
         */
        final public function getRootPath()
        {
            if (empty($this->rootPath)) {
                $reflection = new ReflectionClass(ClassLoader::class);
                $this->rootPath = explode('/', str_replace('\\', '/', dirname(dirname($reflection->getFileName()))));
                array_pop($this->rootPath);
                $this->rootPath = implode("/", $this->rootPath) . '/';
            }
            return $this->rootPath;
        }

        /**
         * @return string
         */
        final public function getRoles()
        {
            return $this->roles;
        }

        /**
         * @return string
         */
        final public function getEnv()
        {
            return $this->appEnv;
        }

        /**
         * @param $modelName
         * @param array $params
         * @return mixed|Model
         * @throws InternalError
         */
        final public function model($modelName, $params = null)
        {
            $className = "\\Model\\" . $modelName;

            if (isset($this->models[$className])) {
                return $this->models[$className];
            } else {
                if (class_exists($className)) {
                    $parameters = [&$this];
                    if (!empty($params) && !is_array($params)) {
                        $parameters[] = &$params;
                    } elseif (!empty($params) && is_array($params)) {
                        foreach ($params as &$item)
                            $parameters[] = &$item;
                    }
                    try {
                        $refClass = new ReflectionClass($className);
                        $object = $refClass->newInstanceArgs($parameters);
                        if ($object instanceof Model) {
                            $this->models[$className] = $object;
                            return $object;
                        } else {
                            throw new InternalError("Model Invalid:{$className}");
                        }
                    } catch (ReflectionException $ex) {
                        throw new InternalError($ex->getMessage());
                    }
                } else {
                    throw new InternalError("Model Not Found:{$className}");
                }
            }
        }

        /**
         * @param $moduleName
         * @param array $params
         * @return mixed|Model
         * @throws InternalError
         */
        final public function module($moduleName, array $params = [])
        {
            $className = "\\Module\\" . $moduleName;
            if (isset($this->modules[$className])) {
                return $this->modules[$className];
            } else {
                if (class_exists($className)) {
                    $parameters = [&$this];
                    if (!empty($params) && !is_array($params)) {
                        $parameters[] = &$params;
                    } elseif (!empty($params) && is_array($params)) {
                        foreach ($params as &$item)
                            $parameters[] = &$item;
                    }
                    try {
                        $refClass = new ReflectionClass($className);
                        $object = $refClass->newInstanceArgs($parameters);
                        $this->modules[$className] = $object;
                        return $object;
                    } catch (ReflectionException $ex) {
                        throw new InternalError($ex->getMessage());
                    }
                } else {
                    throw new InternalError("Module Not Found:{$className}");
                }
            }
        }

        /**
         * Setup View File
         * @param string $viewType
         * @param array $twigExtension
         * @throws InternalError
         */
        final public function view($viewType, array $twigExtension = [])
        {
            $viewInfo = null;
            if (!in_array($viewType, ['json', 'xml', 'none', 'static', 'twig'], true)) {
                $viewInfo = $viewType;
                $viewType = 'twig';
            }
            $this->view->setView($viewType, $viewInfo, 2);
            $this->view->addTwigExtension($twigExtension);
        }

        /**
         * @param int $seek
         * @return array|null
         */
        public final function getCallerInfo($seek = 1)
        {
            $bt = debug_backtrace();
            return !isset($bt[$seek]['file']) ? null : ['file' => str_replace('\\', '/', $bt[$seek]['file']), 'line' => $bt[$seek]['line'], 'class' => isset($bt[$seek]['class']) ? $bt[$seek]['class'] : null, 'function' => isset($bt[$seek]['function']) ? $bt[$seek]['function'] : null];
        }

        /**
         * @param $trace
         * @param $errNo
         * @param $errMsg
         */
        final public function handleError($trace, $errNo, $errMsg)
        {
            if (!$this->ignoreError) {
                try {
                    $this->profiler->logError("Code Error", "Error: ({$errNo})\n{$errMsg}\nTrace:\n" . implode(",", $trace));
                    $profiler = [
                            'desc' => $errMsg,
                            'trace' => $trace,
                            'params' => $this->request->get("*"),
                            'headers' => $this->request->header("*"),
                        ] + $this->profiler->fetch();
                    $this->response = new Response([
                        'status' => 'error',
                        'error' => 'unexpected_error',
                        'code' => 'UNHANDLE_ERROR',
                        'profiler' => $profiler,
                    ]);
                    $this->rollback();
                    $this->view->error();
                    $this->emit('sysReport', [&$this, $profiler]);
                    $this->emit('codeError', [&$this, $profiler]);
                    $this->emit('error', [&$this, $profiler]);
                    $this->emit('appBeforeClean', [&$this, $profiler]);
                    $this->clean();
                    $this->view->render();
                    exit(2);
                } catch (\Exception $exception) {
                    $this->handleException($this->trace(), $exception, false);
                }
            }
        }

        /**
         * @param array $trace
         * @param \Exception $exception
         * @param bool $event
         */
        final public function handleException(array $trace, $exception, $event = false)
        {
            try {
                if ($exception instanceof Exception) {
                    $this->response = $exception->response();
                    $this->rollback();
                    $this->view->error();
                    $this->response->add('profiler', $this->profiler->fetch());
                    $profiler = $this->response->get('profiler');
                    if ($exception->sysLog()) {
                        $desc = is_string($profiler['desc']) ? $profiler['desc'] : json_encode($profiler['desc'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        $this->profiler->logError($this->response->get('error', ''), "{$desc}\nTrace:\n" . implode(",", $profiler['trace']));
                        if ($event)
                            $this->emit('sysReport', [&$this, $profiler]);
                    }
                    if ($event) {
                        $this->emit('appException', [&$this, $profiler]);
                        $this->emit('exception', [&$this, $profiler]);
                    }
                    $exitCode = 3;
                } else {
                    $message = '';
                    if (method_exists($exception, 'getMessage'))
                        $message = $exception->getMessage();
                    $profiler = [
                            'desc' => $message,
                            'trace' => $trace,
                            'params' => $this->request->get("*"),
                            'headers' => $this->request->header("*"),
                        ] + $this->profiler->fetch();
                    $output = [
                        'status' => 'error',
                        'error' => 'unexpected_error',
                        'code' => get_class($exception),
                        'profiler' => $profiler,
                    ];
                    $this->response = new Response($output);
                    $this->rollback();
                    $this->view->error();
                    $this->profiler->logError('PHP Exception', "Message: " . implode("\n", $trace));
                    if ($event) {
                        $this->emit('sysReport', [&$this, $profiler]);
                        $this->emit('codeException', [&$this, $profiler]);
                        $this->emit('exception', [&$this, $profiler]);
                    }
                    $exitCode = 4;
                }
                if ($event)
                    $this->emit('appBeforeClean', [&$this, $profiler]);
                $this->clean();
                $this->view->render();
                exit($exitCode);
            } catch (\Exception $ex) {
                if (!$this->ignoreException) {
                    $this->ignoreException = true;
                    $this->handleException($this->trace(), $ex, false);
                } else {
                    if ($ex instanceof Exception) {
                        $profiler = $this->response->get('profiler');
                        $message = $profiler['desc'];
                        $exitCode = 3;
                    } else {
                        $message = '';
                        if (method_exists($ex, 'getMessage'))
                            $message = $ex->getMessage();
                        $exitCode = 4;
                    }
                    echo($message);
                    exit($exitCode);
                }
            }
        }

        /**
         * App Rollback
         * @throws DBQueryError
         */
        final public function rollback()
        {
            $this->emit('beforeRollback', [&$this]);

            $this->dbRollback();

            if (isset($this->components['session']) && $this->components['session'] instanceof Session)
                $session = $this->components['session'];

            if (isset($session) && $session instanceof Session)
                $session->rollback();

            $this->emit('afterRollback', [&$this]);
        }

        /**
         * DB Rollback
         * @throws DBQueryError
         */
        final public function dbRollback()
        {
            $this->emit('dbRollback', [&$this]);
            if (isset($this->components['db']) && $this->components['db'] instanceof Db)
                $db = $this->components['db'];

            if (isset($db) && $db instanceof Db && $db->isConnected())
                return $db->rollback();
            return true;
        }

        /**
         * Return Debug Trace
         * @param int $seek
         * @return array
         */
        final public function trace($seek = 1)
        {
            $stack = debug_backtrace(0);
            $trace = [];
            $iPos = 0;
            for ($i = $seek; $i < count($stack); $i++) {
                $iPos++;
                $trace[] = isset($stack[$i]['file']) ?
                    "#{$iPos}. {$stack[$i]['file']}:{$stack[$i]['line']}" :
                    (
                    !empty($stack[$i]['class']) ?
                        "#{$iPos}. {$stack[$i]['class']}->{$stack[$i]['function']}" :
                        "#{$iPos}. Closure"
                    );
            }
            return $trace;
        }

        /**
         * @param array $error
         */
        final public function handleCritical(array $error)
        {
            try {
                $trace = ["#1. {$error['file']}:{$error['line']}"];
                $this->profiler->logCritical('PHP Critical', "Error Message: ({$error["type"]})\n{$error["message"]}\nTrace:\n" . implode("\n", $trace));
                $profiler = [
                        'desc' => $error["message"],
                        'trace' => $trace,
                        'params' => $this->request->get("*"),
                        'headers' => $this->request->header("*"),
                    ] + $this->profiler->fetch();
                $this->response = new Response([
                    'status' => 'error',
                    'error' => 'critical',
                    'code' => 'FATAL',
                    'profiler' => $profiler,
                ]);
                $this->rollback();
                $this->view->error();
                $this->emit('sysReport', [&$this, $profiler]);
                $this->emit('criticalError', [&$this, $profiler]);
                $this->emit('critical', [&$this, $profiler]);
                $this->emit('appBeforeClean', [&$this, $profiler]);
                $this->clean();
                $this->view->render();
            } catch (\Exception $e) {
                exit(6);
            }
            exit(5);
        }

        /**
         * Ignore Error Report
         */
        final public function ignoreError()
        {
            $this->ignoreError = (boolean)true;
        }

        /**
         * Attention Error
         */
        final public function noticeError()
        {
            $this->ignoreError = (boolean)false;
        }

        /**
         * Create a new DB Component
         * @param Container|string $host
         * @param int $port
         * @param string $user
         * @param string $pass
         * @param string $name
         * @param bool $event
         * @param int $timeOut
         * @param string $characterSet
         * @param string $collate
         * @param bool $persistent
         * @return mixed
         * @throws DBQueryError
         * @throws InternalError
         * @throws UnknownClass
         */
        final public function createDb($host = 'localhost', $port = 3306, $user = 'root', $pass = '',
                                       $name = '', $event = false,
                                       $timeOut = 10, $characterSet = 'UTF8MB4',
                                       $collate = 'UTF8MB4_UNICODE_CI', $persistent = false)
        {
            if ($host instanceof Container)
                $config = $host;
            else {
                $dbConfig = $this->config('Db');
                $config = new Container([
                    'driver' => $dbConfig->get('driver', 'DbMysqli'),
                    'tran' => $dbConfig->get('tran'),
                    'host' => $host,
                    'hosts' => [$host],
                    'port' => $port,
                    'user' => $user,
                    'pass' => $pass,
                    'name' => $name,
                    'event' => $event,
                    'timeOut' => $timeOut,
                    'characterSet' => $characterSet,
                    'collate' => $collate,
                    'persistent' => $persistent,
                ]);
            }
            return new Db($this, $config);
        }

        /**
         * Magic function Get Lazy Load Component
         * @param $name
         * @return mixed
         * @throws InternalError
         */
        final public function __get($name)
        {
            $name = strtolower($name);
            if (!empty($this->components[$name])) {
                return $this->components[$name];
            } elseif (class_exists(($class = "\\Npf\\Core\\" . ucfirst($name)))) {
                $this->components[$name] = ($name === 'container') ? new $class([], false, true) : new $class($this);
                return $this->components[$name];
            } else
                throw new InternalError("Component Not Found: {$name}");
        }

        /**
         * @param $name
         * @return bool
         */
        final public function __isset($name)
        {
            return isset($this->components[$name]);
        }

        /**
         * @param $name
         */
        final public function __unset($name)
        {
            if (isset($this->components[$name]))
                unset($this->components[$name]);
        }

        /**
         * @return string
         */
        final public function getAppName()
        {
            return $this->appName;
        }

        /**
         * @return string
         */
        final public function getBasePath()
        {
            return $this->basePath;
        }

        /**
         * @param Container $response
         */
        final public function replaceResponse(Container &$response)
        {
            $this->response = &$response;
        }

        /**
         * Class Destruct, Close DB Connection
         */
        final public function __destruct()
        {
            if (isset($this->db) && is_object($this->db))
                $this->db->close();
            if (isset($this->redis) && is_object($this->redis))
                $this->session->close();
            if (isset($this->redis) && is_object($this->redis))
                $this->redis->close();
        }
    }
}