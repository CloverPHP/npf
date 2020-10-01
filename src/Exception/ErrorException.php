<?php

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
        protected $error = 'error_exception';
        /**
         * @var bool
         */
        protected $sysLog = true;
        /**
         * @var int
         */
        protected $severity = 0;

        final public function __construct($desc, $code, $status = 'error', $severity = 0)
        {
            $this->severity = $severity;
            parent::__construct($desc, $code, $status, []);
        }
    }
}
