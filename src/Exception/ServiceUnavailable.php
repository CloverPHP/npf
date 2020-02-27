<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class ServiceUnavailable
     * @package Exception
     */
    class ServiceUnavailable extends Exception
    {
        protected $error = 'service_unavailable';
    }
}