<?php
declare(strict_types=1);

namespace Npf\Core\Db {

    /**
     * Class DbDriver
     * @package Core\Db
     */
    abstract class DbDriver
    {
        public string $lastQuery;
    }
}
