<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class SystemMaintenance
     * @package Exception
     */
    class SystemMaintenance extends Exception
    {
        protected string $error = 'system_maintenance';
    }
}
