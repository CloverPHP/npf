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
        private $initTime = 0;

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
         * @return array|boolean
         */
        public function fetch()
        {
            $Uri = $this->app->request->getUri();
            $profiler = [
                'memusage' => $this->memUsage(),
                'cpuusage' => file_exists('/proc/loadavg') ? substr(file_get_contents('/proc/loadavg'), 0, 4) : false,
                'timeusage' => [
                    'total' => $this->elapsed(true) . "ms",
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
         * @return mixed
         */
        public function memUsage()
        {
            return Common::fileSize2Unit(memory_get_usage());
        }

        /**
         * 计算程序执行时间(ms)
         * @param bool $milliSec
         * @return float
         */
        public function elapsed($milliSec = true)
        {
            if ($milliSec) {
                return round(((microtime(true)) - $this->initTime) * 1000, 2);
            } else {
                return round(microtime(true) - $this->initTime, 2);
            }
        }

        /**
         * Skype-Express Highlight Channel
         * @param $type
         * @param $content
         * @return bool|mixed
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
         * @return bool|mixed
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
         * @return bool|mixed
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
         * @return bool|mixed
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
         * @param $sTime
         * @param $category
         */
        public function saveQuery($queryStr, $sTime, $category)
        {
            try {
                if (!$this->enable || !$this->app->config('Profiler')->get("queryLog" . ucfirst($category), true))
                    return;

                $nowMilliSec = $this->elapsed();
                $milliSecond = round(($nowMilliSec + $sTime), 2);
                if (!isset($this->timeUsage[$category]))
                    $this->timeUsage[$category] = 0;
                $this->timeUsage[$category] += $milliSecond;
                $elapsed = round($nowMilliSec, 2);
                $start = round($nowMilliSec - $milliSecond, 2);
                $this->query[] = str_pad("({$milliSecond}ms On {$start}-{$elapsed}ms)", 30, " ", STR_PAD_RIGHT) . str_pad($category,
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