<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class SignatureMismatch
     * @package Exception
     */
    class SignatureMismatch extends Exception
    {
        protected string $error = 'signature_mismatch';
    }
}

