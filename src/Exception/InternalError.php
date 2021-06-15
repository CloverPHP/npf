<?php
declare(strict_types=1);

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
        protected bool $sysLog = true;

        protected string $error = 'unexpected_error';
    }
}
