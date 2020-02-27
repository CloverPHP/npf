<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class DBQueryError
     * @package Exception
     */
    class DBQueryError extends Exception
    {
        protected $error = 'unexpected_error';
        protected $sysLog = true;
    }
}
