<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class ServiceUnavailable
     * @package Exception
     */
    class ServiceUnavailable extends Exception
    {
        protected string $error = 'service_unavailable';
    }
}