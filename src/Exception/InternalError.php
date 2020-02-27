<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class UnexpectedError
     * @package Exception
     */
    class InternalError extends Exception
    {
        /**
         * @var bool Want to system log or not
         */
        protected $sysLog = true;

        protected $error = 'unexpected_error';
    }
}
