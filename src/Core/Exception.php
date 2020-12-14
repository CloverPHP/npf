<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\DBQueryError;
    use Npf\Exception\ErrorException;

    /**
     * Class ExceptionNormal
     * @package Core
     */
    class Exception extends \Exception
    {
        protected Response $response;
        protected string $status = '';
        protected string $error = '';
        protected bool $sysLog = false;

        /**
         * ExceptionNormal constructor.
         * @param null|string $desc
         * @param string $code
         * @param string $status
         * @param array $extra
         * @internal param string $error
         */
        public function __construct(?string $desc = '',
                                    string $code = '',
                                    string $status = 'error',
                                    array $extra = [])
        {
            parent::__construct($desc);
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
         * @param string $name
         * @param array $arguments
         * @return mixed
         */
        public function __call(string $name, array $arguments): mixed
        {
            if (method_exists($this->response, $name)) {
                return call_user_func_array([$this->response, $name], $arguments);
            } else
                return false;
        }

        /**
         * @return Response
         */
        public function response(): Response
        {
            return $this->response;
        }

        /**
         * @return bool
         */
        public function sysLog(): bool
        {
            return $this->sysLog;
        }

        /**
         * @return string
         */
        public function getErrorCode(): string
        {
            return $this->error;
        }
    }

}
