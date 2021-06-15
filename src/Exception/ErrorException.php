<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class DBQueryError
     * @package Exception
     */
    class ErrorException extends Exception
    {
        /**
         * @var string
         */
        protected string $error = 'error_exception';
        /**
         * @var bool
         */
        protected bool $sysLog = true;
        /**
         * @var int
         */
        protected int $severity = 0;

        final public function __construct($desc, $code, $status = 'error', $severity = 0)
        {
            $this->severity = $severity;
            parent::__construct($desc, $code, $status, []);
        }
    }
}
