<?php

//namespace %%Setup%%;

use Npf\Core\Exception;

/**
 * Class Model
 * @package Model
 */
abstract class Base extends Exception
{
    public function __construct($desc = '', $code = '', $status = 'error', array $extra = [])
    {
        parent::__construct($desc, $code, $status, $extra);
    }
}