<?php

namespace Npf\Core {

    use Npf\Exception\DBQueryError;
    use Npf\Exception\ErrorException;

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
            $stack = debug_backtrace(0);
            $trace = [];
            switch (true) {
                case $this instanceof DBQueryError:
                    $start = 3;
                    break;

                case $this instanceof ErrorException:
                    $start = 2;
                    break;
                default:
                    $start = 0;
            }
            $iPos = 0;
            for ($i = $start; $i < count($stack); $i++) {
                $iPos++;
                $trace[] = !empty($stack[$i]['file']) ?
                    "#{$iPos}. {$stack[$i]['file']}:{$stack[$i]['line']}" :
                    (
                    !empty($stack[$i]['class']) ?
                        "#{$iPos}. {$stack[$i]['class']}->{$stack[$i]['function']}" :
                        "#{$iPos}. Closure"
                    );
            }
            if (empty($status))
                $status = 'error';
            $output = [
                'status' => $status,
                'error' => !empty($this->error) ? $this->error : 'error',
                'profiler' => [
                    'desc' => $desc,
                    'trace' => $trace,
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
    }

}
