<?php

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class VersionIncompatible
     * @package Exception
     */
    class VersionIncompatible extends Exception
    {
        protected $error = 'version_incompatible';
    }
}
