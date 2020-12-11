<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class UnknownClass
     * @package Exception
     */
    class UnknownClass extends Exception
    {
        protected string $error = 'unknown_action';
    }
}