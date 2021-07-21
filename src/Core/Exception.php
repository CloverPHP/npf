<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\GeneralException;
    use Throwable;

    /**
     * Class ExceptionNormal
     * @package Core
     */
    class Exception extends \Exception
    {
        /**
         * @var Response
         */
        protected Response $response;
        /**
         * @var string
         */
        protected string $error = 'error';
        /**
         * @var bool
         */
        protected bool $sysLog = false;

        /**
         * @var Throwable
         */
        protected static Throwable $previous;

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
            parent::__construct($desc, is_numeric($code) ? (int)$code : 0, self::$previous ?? null);
            self::$previous = $this;
            if (empty($status))
                $status = 'error';
            $output = [
                'status' => $status,
                'error' => !empty($this->error) ? $this->error : 'error',
                'profiler' => [
                    'desc' => self::desc($this),
                    'trace' => self::trace($this),
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

        /**
         * @return string
         */
        public function __toString(): string
        {
            return json_encode($this->response->fetch());
        }

        /**
         * @param null $ex
         * @return array
         */
        final public static function trace($ex = null): array
        {
            $result = [];
            if (!$ex instanceof \ErrorException && !$ex instanceof GeneralException) {
                $trace = explode("\n", $ex->getTraceAsString());
                array_pop($trace);
                $length = count($trace);
                for ($i = 0; $i < $length; $i++)
                    $result[] = ($i + 1) . '.' . substr($trace[$i], strpos($trace[$i], ' '));
            }
            if ($previous = $ex->getPrevious())
                $result = array_merge($result, self::trace($previous));
            return $result;
        }

        /**
         * @param null $ex
         * @return array|false|string
         */
        final public static function desc($ex = null): array|false|string
        {
            $results = [];
            if (!$ex instanceof GeneralException)
                $results[] = "{$ex->getMessage()} at {$ex->getFile()} on line {$ex->getLine()}";
            if ($previous = $ex->getPrevious()) {
                $result = self::desc($previous);
                if (!empty($result)) {
                    if (!is_array($result))
                        $result = [$result];
                    $results = array_merge($results, $result);
                }
            }
            if (count($results) <= 1)
                $results = reset($results);
            return $results;
        }
    }

}