<?php
declare(strict_types=1);

namespace Npf\Core {

    use JetBrains\PhpStorm\ArrayShape;
    use JetBrains\PhpStorm\Pure;
    use Throwable;

    /**
     * Profiler
     */
    final class Profiler
    {
        /**
         * @var bool
         */
        private bool $enable = false;

        /**
         * @var float|string
         */
        private float|string $initTime;

        /**
         * @var array
         */
        private array $timeUsage = [];

        /**
         * @var array
         */
        private array $timer = [];

        /**
         *
         * @var array
         */
        private array $query = [];

        /**
         * @var array
         */
        private array $debug = [];

        /**
         * @var Container
         */
        private Container $config;

        /**
         * @var int
         */
        private int $maxLog = 100;

        /**
         * Profiler constructor.
         * @param App $app
         */
        public function __construct(private App $app)
        {
            $this->initTime = INIT_TIMESTAMP;
            try {
                $this->config = $app->config('Profiler', true);
            } catch (Throwable) {
                $this->config = new Container();
            }
            $this->maxLog = $this->config->get('maxLog', 100);
            if (in_array($app->getRoles(), ['daemon', 'cronjob'], true)) {
                $opts = getopt('p', ['profiler']);
                if (is_array($opts) && !empty($opts))
                    $this->enable = true;
            } else
                $this->enable = $this->config->get('enable', true);
        }

        /**
         * Add the debug note to the profiler
         */
        public function debug(): self
        {
            if ($this->enable)
                foreach (func_get_args() as $msg)
                    $this->debug[] = $msg;
            return $this;
        }

        /**
         * @return bool
         */
        public function enable(): bool
        {
            return $this->enable;
        }

        /**
         * @return array|bool
         */
        #[ArrayShape(['memusage' => "string", 'cpuusage' => "false|string", 'timeusage' => "array", 'uri' => "string", 'params' => "mixed", 'headers' => "mixed", 'debug' => "array", 'query' => "array", 'detail' => "array"])]
        public function fetch(): array|bool
        {
            $uri = $this->app->request->getUri();
            list($requestSec, $requestUsec) = explode('.', (string)$this->initTime);
            $requestUsec = substr($requestUsec, 0, 3);
            $profiler = [
                'memoryUsage' => [
                    'current' => $this->memUsage(),
                    'peak' => $this->memPeakUsage(),
                ],
                'cpuUsage' => file_exists('/proc/loadavg') ? substr(file_get_contents('/proc/loadavg'), 0, 4) : false,
                'requestTime' => date("H:i:s", (int)$requestSec) . ".{$requestUsec}",
                'timeUsage' => [
                    'total' => $this->elapsed() . "ms",
                ],
                'uri' => !empty($uri) ? $uri : '',
                'params' => $this->app->request->get("*"),
                'headers' => $this->app->request->header("*"),
                'debug' => $this->debug,
                'query' => $this->query,
            ];

            foreach ($this->timeUsage as $key => $time)
                $profiler['timeusage'][$key] = round($time, 2) . 'ms';

            return $profiler;
        }

        /**
         * @return string
         */
        #[Pure] public function memUsage(): string
        {
            return Common::fileSize2Unit(memory_get_usage());
        }

        /**
         * @return string
         */
        #[Pure] public function memPeakUsage(): string
        {
            return Common::fileSize2Unit(memory_get_peak_usage());
        }

        /**
         * 计算程序执行时间(ms)
         * @param bool $milliSec
         * @return float
         */
        #[Pure] public function elapsed(bool $milliSec = true): float
        {
            if ($milliSec) {
                return round(((microtime(true)) - $this->initTime) * 1000, 2);
            } else {
                return round(microtime(true) - $this->initTime, 2);
            }
        }

        /**
         * 计算程序执行时间(ms)
         * @param string $timer
         * @param bool $continue
         * @return void
         */
        public function timerStart(string $timer = 'default', bool $continue = false)
        {
            if (!$continue || !isset($this->timer[$timer]))
                $this->timer[$timer] = -1 * round(microtime(true) * 1000, 2);
        }

        /**
         * 计算程序执行时间(ms)
         * @param string $timer
         * @return float
         */
        public function timerRead(string $timer = 'default'): float
        {
            return round(microtime(true) * 1000 + ($this->timer[$timer] ?? 0), 2);
        }

        /**d
         * @param int $priority
         * @param string $type
         * @param mixed $content
         * @return void
         */
        private function _log(int $priority, string $type, mixed $content): void
        {
            if (!is_string($content))
                $content = json_encode($content);
            try {
                syslog($priority, "{$type}：{$content}");
            } catch (\Exception) {
            }
        }

        /**
         * Skype-Express Highlight Channel
         * @param string $type
         * @param mixed $content
         * @return self
         */
        public function logCritical(string $type, mixed $content): self
        {
            if ($this->config instanceof Container && $this->config->get('logCritical'))
                $this->_log(LOG_CRIT, $type, $content);
            return $this;
        }

        /**
         * Skype-Express Highlight Channel
         * @param string $type
         * @param mixed $content
         * @return self
         */
        public function logError(string $type, mixed $content): self
        {
            if ($this->config instanceof Container && $this->config->get('logError'))
                $this->_log(LOG_ERR, $type, $content);
            return $this;
        }

        /**
         * Skype-Express Highlight Channel
         * @param string $type
         * @param mixed $content
         * @return self
         */
        public function logInfo(string $type, mixed $content): self
        {
            if ($this->config instanceof Container && $this->config->get('logInfo'))
                $this->_log(LOG_INFO, $type, $content);
            return $this;
        }

        /**
         * @param string $type
         * @param mixed $content
         * @return self
         */
        public function logDebug(string $type, mixed $content): self
        {
            if ($this->config instanceof Container && $this->config->get('logDebug'))
                $this->_log(LOG_DEBUG, $type, $content);
            return $this;
        }

        /**
         * @param string $queryStr
         * @param string $category
         * @return Profiler
         */
        public function saveQuery(string $queryStr, string $category): self
        {
            try {
                if (!$this->enable || !$this->app->config('Profiler')->get("queryLog" . ucfirst($category), true))
                    return $this;

                $now = $this->elapsed();
                $start = $this->timer[$category];
                $elapsed = $this->timerRead($category);
                if (!isset($this->timeUsage[$category]))
                    $this->timeUsage[$category] = 0;
                $this->timeUsage[$category] += $elapsed;
                $this->query[] = str_pad("({$elapsed}ms On {$start}-{$now}ms)", 30, " ", STR_PAD_RIGHT) . str_pad($category,
                        5, " ", STR_PAD_LEFT) . ": $queryStr";
                if ($this->maxLog > 0 && count($this->query) > $this->maxLog)
                    $this->query = array_slice($this->query, -1 * $this->maxLog);
                return $this;
            } catch (Exception) {
                return $this;
            }
        }

        /**
         * @param string $name
         * @param array|null $arguments
         * @return null
         */
        public function __call(string $name, ?array $arguments)
        {
            return null;
        }
    }
}