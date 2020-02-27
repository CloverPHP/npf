<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class UnknownClass
     * @package Exception
     */
    class UnknownClass extends Exception
    {
        protected $error = 'unknown_action';
    }
}