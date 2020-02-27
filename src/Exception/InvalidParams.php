<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class InvalidParams
     * @package Exception
     */
    class InvalidParams extends Exception
    {
        protected $error = 'invalid_params';
    }
}