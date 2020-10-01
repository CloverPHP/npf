<?php

namespace Npf\Core {

    use Npf\Exception\ErrorException;

    /**
     * Kernel, handling error, exception, critical error.
     * Class Core
     * @package Core
     */
    final class Kernel
    {
        /**
         * @var array App Info
         */
        public static $appInfo = [];

        /**
         * App Object
         * @var App
         */
        public $app = null;

        /**
         * Core constructor.
         */
        final public function __construct()
        {
            //Initial Handling
            ini_set('display_errors', 1);
            ini_set('display_startup_errors', 1);
            register_shutdown_function([$this, 'handleShutdown']);
            set_exception_handler([$this, 'handleException']);
            set_error_handler([$this, 'handleError'], E_ALL);
        }

        /**
         * Create App
         * @param array $appInfo
         * @return App
         */
        final public function createApp(array $appInfo)
        {
            static::$appInfo = array_intersect_key($appInfo, array_fill_keys(['role', 'env', 'name'], true));
            if (empty(static::$appInfo['role']))
                static::$appInfo['roles'] = 'web';
            if (empty(static::$appInfo['env']))
                static::$appInfo['env'] = 'local';
            if (empty(static::$appInfo['name']))
                static::$appInfo['name'] = 'defaultApp';
            $app = new App(static::$appInfo['role'], static::$appInfo['env'], static::$appInfo['name']);
            $this->app = &$app;
            return $this->app;
        }

        /**
         * Critical Error Handle
         */
        final public function handleShutdown()
        {
            if (!isset($this->app))
                $this->app = new App(static::$appInfo['role'], static::$appInfo['env'], static::$appInfo['name']);
            $this->app->emit('shutdown', [&$this->app]);
            $this->handleCritical();
        }

        /**
         * Handle Critical Error
         */
        final public function handleCritical()
        {
            $error = error_get_last();
            if (!empty($error) && is_array($error)) {
                if (!isset($this->app))
                    $this->app = new App(static::$appInfo['role'], static::$appInfo['env'], static::$appInfo['name']);
                $this->app->handleCritical($error);
            }
        }

        /**
         * Exception Handle
         * @param \Exception $exception
         */
        final public function handleException($exception)
        {
            if (!isset($this->app))
                $this->app = new App(static::$appInfo['role'], static::$appInfo['env'], static::$appInfo['name']);
            $trace = $this->app->trace();
            $this->app->handleException($trace, $exception, true);
        }

        /**
         * Error Handle
         * @param $severity
         * @param $message
         * @throws ErrorException
         */
        final public function handleError($severity, $message)
        {
            // This error code is not included in error_reporting
            if (!(error_reporting() & $severity))
                return;
            throw new ErrorException($message, $severity);
        }

        /**
         * @param array $appInfo
         * @throws \Exception
         */
        final public function __invoke(array $appInfo = [])
        {
            try {
                $timezone = $this->app->config('General')->get('timezone');
            } catch (\Exception $e) {
                $timezone = 'UTC';
            }
            Common::initial($timezone);
            $this->app->__invoke();
        }
    }
}