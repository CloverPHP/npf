<?php

namespace Npf\Core {

    /**
     * 调试处理类
     */
    final class Profiler
    {
        /**
         * @var App
         */
        private $app;
        /**
         * @var App
         */
        private $enable = false;

        /**
         *
         * @var int
         */
        private $initTime;

        /**
         *
         * @var array
         */
        private $timeUsage = [];

        /**
         *
         * @var array
         */
        private $query = [];

        /**
         *
         * @var array
         */
        private $debug = [];

        /**
         * @var array
         */
        private $timer = [];

        /**
         * @var Container
         */
        private $config;

        private $maxLog = 0;

        /**
         * Profiler constructor.
         * @param App $app
         */
        public function __construct(App &$app)
        {
            $this->app = &$app;
            $this->initTime = INIT_TIMESTAMP;
            try {
                $this->config = $app->config('Profiler', true);
            } catch (\Exception $exception) {
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
        public function debug()
        {
            if ($this->enable)
                foreach (func_get_args() as $msg)
                    $this->debug[] = $msg;
        }

        /**
         * @return mixed
         */
        public function enable()
        {
            return $this->enable;
        }

        /**
         * @return array
         */
        public function fetch()
        {
            $Uri = $this->app->request->getUri();
            $profiler = [
                'memusage' => $this->memUsage(),
                'cpuusage' => file_exists('/proc/loadavg') ? substr(file_get_contents('/proc/loadavg'), 0, 4) : false,
                'timeusage' => [
                    'total' => $this->elapsed() . "ms",
                ],
                'debug' => $this->debug,
                'query' => $this->query,
                'uri' => !empty($Uri) ? $Uri : '',
                'params' => $this->app->request->get("*"),
                'headers' => $this->app->request->header("*"),
            ];

            foreach ($this->timeUsage as $key => $time) {
                $profiler['timeusage'][$key] = round($time, 2) . 'ms';
            }
            return $profiler;
        }

        /**
         * @return string
         */
        public function memUsage()
        {
            return Common::fileSize2Unit(memory_get_usage());
        }

        /**
         * elasped time second until now
         * @param bool $mili
         * @return float
         */
        public function elapsed($mili = true)
        {
            if ($mili)
                return round(microtime(true) * 1000 - $this->initTime, 2);
            else
                return floor(microtime(true) - $this->initTime / 1000);
        }

        /**
         * 计算程序执行时间(ms)
         * @param string $timer
         * @return void
         */
        public function timerStart($timer = 'default', $continue = false)
        {
            if (!$continue || !isset($this->timer[$timer]))
                $this->timer[$timer] = -1 * round(microtime(true) * 1000, 2);
        }

        /**
         * 计算程序执行时间(ms)
         * @param string $timer
         * @return float
         */
        public function timerRead($timer = 'default')
        {
            return round(microtime(true) * 1000 + (isset($this->timer[$timer]) ? $this->timer[$timer] : 0), 2);
        }

        /**
         * Skype-Express Highlight Channel
         * @param $type
         * @param $content
         * @return bool
         */
        public function logCritical($type, $content)
        {
            if ($this->config instanceof Container && $this->config->get('logCritical'))
                return $this->_log(LOG_CRIT, $type, $content);
            else
                return false;
        }

        /**d
         * @param $channel
         * @param $type
         * @param $content
         * @return bool
         */
        private function _log($channel, $type, $content)
        {
            if (!is_string($content))
                $content = json_encode($content);
            try {
                syslog($channel, "{$type}：{$content}");
                return true;
            } catch (\Exception $ex) {
                return false;
            }
        }

        /**
         * Skype-Express Highlight Channel
         * @param $type
         * @param $content
         * @return bool
         */
        public function logError($type, $content)
        {
            if ($this->config instanceof Container && $this->config->get('logError'))
                return $this->_log(LOG_ERR, $type, $content);
            else
                return false;
        }

        /**
         * Skype-Express Highlight Channel
         * @param $type
         * @param $content
         * @return bool
         */
        public function logInfo($type, $content)
        {
            if ($this->config instanceof Container && $this->config->get('logInfo'))
                return $this->_log(LOG_INFO, $type, $content);
            else
                return false;
        }

        /**
         * @param $type
         * @param $content
         * @return bool
         */
        public function logDebug($type, $content)
        {
            if ($this->config instanceof Container && $this->config->get('logDebug'))
                return $this->_log(LOG_DEBUG, $type, $content);
            else
                return false;
        }

        /**
         * @param $queryStr
         * @param $category
         */
        public function saveQuery($queryStr, $category)
        {
            try {
                if (!$this->enable || !$this->app->config('Profiler')->get("queryLog" . ucfirst($category), true))
                    return;

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
                return;
            } catch (Exception $e) {
                return;
            }
        }

        /**
         * @param $name
         * @param $arguments
         * @return null
         */
        public function __call($name, $arguments)
        {
            return null;
        }
    }

}