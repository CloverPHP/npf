<?PHP

/* * ********************************************************************
* PHP CLASS
* CLASS AUTHOR : LYK
* DESCRIPTION : Use the Class to Connect to Database
* DATABASE API : MYSQL
* DEVELOPMENT DATE : 2007-08-15
* ******************************************************************** */

namespace Npf\Core\Db {

    /**
     * Class DbDriver
     * @package Core\Db
     */
    abstract class DbDriver
    {
        public $lastQuery = '';
    }
}
