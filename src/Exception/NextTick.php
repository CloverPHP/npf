<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class UnexpectedError
     * @package Exception
     */
    class NextTick extends Exception
    {
        protected $error = 'daemon_next_tick';
    }
}
