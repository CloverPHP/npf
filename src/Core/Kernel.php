<?php

namespace Npf\Core {

    use ErrorException;

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

        private function createApp()
        {
            if ($this->app === null) {
                $app = new App(self::$appInfo['role'], self::$appInfo['env'], self::$appInfo['name']);
                $this->app = &$app;
            }
            return $this->app;
        }

        /**
         * Create App
         * @param array $appInfo
         * @return App
         */
        final public function buildApp(array $appInfo)
        {
            self::$appInfo = array_intersect_key($appInfo, array_fill_keys(['role', 'env', 'name'], true));
            if (empty(self::$appInfo['role']))
                self::$appInfo['roles'] = 'web';
            if (empty(self::$appInfo['env']))
                self::$appInfo['env'] = 'local';
            if (empty(self::$appInfo['name']))
                self::$appInfo['name'] = 'defaultApp';
            return $this->createApp();
        }

        /**
         * Critical Error Handle
         */
        final public function handleShutdown()
        {
            $this->createApp();
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
                $this->createApp();
                $this->app->handleCritical($error);
            }
        }

        /**
         * Exception Handle
         * @param \Exception $exception
         */
        final public function handleException($exception)
        {
            $this->createApp();
            $this->app->handleException($exception, true);
        }

        /**
         * Error Handle
         * @param $severity
         * @param $message
         * @param $file
         * @param $line
         */
        final public function handleError($severity, $message, $file, $line)
        {
            // This error code is not included in error_reporting
            if (!(error_reporting() & $severity))
                return;
            $this->app->handleException(new ErrorException($message, 0, $severity, $file, $line), true);
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