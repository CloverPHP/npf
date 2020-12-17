<?php

namespace Npf\Core {

    /**
     * Class ExceptionNormal
     * @package Core
     */
    class Exception extends \Exception
    {
        protected $response;
        protected $status = '';
        protected $error = '';
        protected $sysLog = false;

        /**
         * ExceptionNormal constructor.
         * @param null|string $desc
         * @param string $code
         * @param string $status
         * @param array $extra
         * @internal param string $error
         */
        public function __construct($desc = '', $code = '', $status = 'error', array $extra = [])
        {
            parent::__construct($desc, 0);
            if (empty($status))
                $status = 'error';
            $output = [
                'status' => $status,
                'error' => !empty($this->error) ? $this->error : 'error',
                'profiler' => [
                    'desc' => $desc,
                    'trace' => $this->trace(),
                ],
            ];
            if (!empty($code))
                $output['code'] = $code;
            $output['profiler'] += $extra;
            $output += $extra;
            $this->response = new Response($output);
        }

        /**
         * @param $name
         * @param $arguments
         * @return bool|mixed
         */
        public function __call($name, $arguments)
        {
            if (method_exists($this->response, $name)) {
                return call_user_func_array([$this->response, $name], $arguments);
            } else
                return false;
        }

        /**
         * @return Response
         */
        public function response()
        {
            return $this->response;
        }

        /**
         * @return bool
         */
        public function sysLog()
        {
            return $this->sysLog;
        }

        /**
         * @return string
         */
        public function getErrorCode()
        {
            return $this->error;
        }

        private function trace()
        {
            $trace = explode("\n", $this->getTraceAsString());
            array_pop($trace);
            $length = count($trace);
            $result = [];
            for ($i = 0; $i < $length; $i++)
                $result[] = ($i + 1) . '.' . substr($trace[$i], strpos($trace[$i], ' '));
            return $result;
        }
    }

}
