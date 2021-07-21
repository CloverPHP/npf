<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\GeneralException;
    use \Throwable;

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
         * @var int
         */
        protected int $severity = 0;
        /**
         * @var array
         */
        private array $stats;

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
            parent::__construct($desc);
            $stack = debug_backtrace(0);
            $this->stats = [
                'desc' => (string)$desc,
                'error' => $this->error,
                'status' => $status,
                'code' => $code,
            ];
            $iPos = 0;
            for ($i = 0; $i < count($stack); $i++) {
                $iPos++;
                if (empty($this->stats['file'])) {
                    $this->stats['line'] = $stack[$i]['line'];
                    $this->stats['file'] = $stack[$i]['file'];
                }
                $this->stats['trace'][] = !empty($stack[$i]['file']) ?
                    "#{$iPos}. {$stack[$i]['file']}:{$stack[$i]['line']}" :
                    (
                    !empty($stack[$i]['class']) ?
                        "#{$iPos}. {$stack[$i]['class']}->{$stack[$i]['function']}" :
                        "#{$iPos}. Closure"
                    );
            }
            $this->response = new Response([
                    'status' => $this->stats['status'] ?? 'error',
                    'error' => $this->stats['error'] ?? '',
                    'code' => $this->stats['code'] ?? '',
                    'profiler' => [
                        'desc' => $this->stats['desc'],
                        'trace' => $this->stats['trace'],
                        'extra' => $extra,
                    ],
                ] + $extra);
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
         * @return int
         */
        public function getSeverity(): int
        {
            return $this->severity;
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