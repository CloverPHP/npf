<?php
declare(strict_types=1);

namespace Npf\Core {

    use Throwable;

    /**
     * Class ExceptionNormal
     * @package Core
     */
    class Exception implements Throwable
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
                    'error' => $this->stats['code'] ?? '',
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
        public function getMessage(): string
        {
            return (string)$this->stats['desc'];
        }

        /**
         * @return int
         */
        public function getCode():int
        {
            return (int)$this->stats['code'];
        }

        public function getFile(): string
        {
            return $this->stats['file'];
        }

        /**
         * @return int
         */
        public function getLine(): int
        {
            return (int)$this->stats['line'];
        }

        /**
         * @return array
         */
        public function getTrace(): array
        {
            return $this->stats['trace'];
        }

        /**
         * @return string
         */
        public function getTraceAsString(): string
        {
            return $this->stats['trace'];
        }

        /**
         * @return null
         */
        public function getPrevious()
        {
            return null;
        }

        /**
         * @return string
         */
        public function __toString(): string
        {
            return json_encode($this->response->fetch());
        }
    }

}