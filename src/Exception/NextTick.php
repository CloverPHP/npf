<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class UnexpectedError
     * @package Exception
     */
    class NextTick extends Exception
    {
        protected string $error = 'daemon_next_tick';
    }
}
