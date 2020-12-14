<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\ErrorException;
    use Throwable;

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
        public static array $appInfo = [];

        /**
         * App Object
         * @var App
         */
        public App $app;

        /**
         * Core constructor.
         */
        final public function __construct()
        {
            //Initial Handling
            ini_set('display_errors', '1');
            ini_set('display_startup_errors', '1');
            register_shutdown_function([$this, 'handleShutdown']);
            set_exception_handler([$this, 'handleException']);
            set_error_handler([$this, 'handleError'], E_ALL);
        }

        /**
         * Create App
         * @param array $appInfo
         * @return App
         */
        final public function createApp(array $appInfo): App
        {
            self::$appInfo = array_intersect_key($appInfo, array_fill_keys(['role', 'env', 'name'], true));
            if (empty(self::$appInfo['role']))
                self::$appInfo['roles'] = 'web';
            if (empty(self::$appInfo['env']))
                self::$appInfo['env'] = 'local';
            if (empty(self::$appInfo['name']))
                self::$appInfo['name'] = 'defaultApp';
            $app = new App(self::$appInfo['role'], self::$appInfo['env'], self::$appInfo['name']);
            $this->app = &$app;
            return $this->app;
        }

        /**
         * Critical Error Handle
         */
        final public function handleShutdown(): void
        {
            if (!isset($this->app))
                $this->app = new App(self::$appInfo['role'], self::$appInfo['env'], self::$appInfo['name']);
            $this->app->emit('shutdown', [&$this->app]);
            $this->handleCritical();
        }

        /**
         * Handle Critical Error
         */
        final public function handleCritical(): void
        {
            $error = error_get_last();
            if (!empty($error) && is_array($error)) {
                if (!isset($this->app))
                    $this->app = new App(self::$appInfo['role'], self::$appInfo['env'], self::$appInfo['name']);
                $this->app->handleCritical($error);
            }
        }

        /**
         * Exception Handle
         * @param Throwable $exception
         */
        final public function handleException(Throwable $exception): void
        {
            if (!isset($this->app))
                $this->app = new App(self::$appInfo['role'], self::$appInfo['env'], self::$appInfo['name']);
            $trace = $this->app->trace();
            $this->app->handleException($trace, $exception, true);
        }

        /**
         * Error Handle
         * @param int $severity
         * @param string $message
         * @throws ErrorException
         */
        final public function handleError(int $severity, string $message)
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
        final public function __invoke(array $appInfo = []): void
        {
            try {
                $timezone = $this->app->config('General')->get('timezone');
            } catch (\Exception) {
                $timezone = 'UTC';
            }
            Common::initial($timezone);
            $this->app->__invoke();
        }
    }
}