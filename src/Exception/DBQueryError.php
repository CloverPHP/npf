<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class DBQueryError
     * @package Exception
     */
    class DBQueryError extends Exception
    {
        protected $error = 'db_query_error';
        protected $sysLog = true;
    }
}
