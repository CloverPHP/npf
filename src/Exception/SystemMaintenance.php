<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class SystemMaintenance
     * @package Exception
     */
    class SystemMaintenance extends Exception
    {
        protected $error = 'system_maintenance';
    }
}
