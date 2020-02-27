<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class SignatureMismatch
     * @package Exception
     */
    class SignatureMismatch extends Exception
    {
        protected $error = 'signature_mismatch';
    }
}

