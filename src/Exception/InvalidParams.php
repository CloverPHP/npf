<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class InvalidParams
     * @package Exception
     */
    class InvalidParams extends Exception
    {
        protected string $error = 'invalid_params';
    }
}