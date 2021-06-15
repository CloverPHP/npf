<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class DBQueryError
     * @package Exception
     */
    class DBQueryError extends Exception
    {
        protected string $error = 'db_query_error';
        protected bool $sysLog = true;
    }
}
