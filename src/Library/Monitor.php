<?php

namespace Npf\Library {

    use Npf\Core\App;

    /**
     * Class Cmd
     * @package Library
     */
    class Monitor
    {
        /**
         * @var App
         */
        private $app;

        /**
         * Cmd constructor.
         * @param App $app
         */
        public function __construct(App &$app)
        {
            $this->app = &$app;
        }

        public function notice()
        {
            $opt = getopt('m:', ['monitor:']);
            $name = '';
            if (is_array($opt) && !empty($opt))
                $name = (string)reset($opt);
            if (!empty($name)) {
                $cmd = '/usr/local/bin/report_program_status.sh';
                if ($cmd)
                    $this->execute($cmd, [$name]);
            }
        }

        /**
         * Notice
         * @param $bin
         * @param array $params
         * @return array
         */
        public function execute($bin, $params = [])
        {
            if (!is_array($params))
                $params = [$params];
            array_walk($params, function (&$value) {
                escapeshellarg($value);
            });
            $cmd = "timeout 1s {$bin} " . implode(" ", $params);
            $retArray = $exitCode = null;
            exec($cmd, $retArray, $exitCode);
            return ['return' => $retArray, 'exitCode' => $exitCode];
        }
    }
}