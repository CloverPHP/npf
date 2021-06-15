<?php
declare(strict_types=1);

namespace Npf\Exception {

    use Npf\Core\Exception;

    /**
     * Class VersionIncompatible
     * @package Exception
     */
    class VersionIncompatible extends Exception
    {
        protected string $error = 'version_incompatible';
    }
}
