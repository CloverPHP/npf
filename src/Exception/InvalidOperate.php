<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class InvalidOperate
     * @package Exception
     */
    class InvalidOperate extends Exception
    {
        protected string $error = 'invalid_operate';
    }
}
