<?php

/********************************************************
 * PHP 7.0 Polyfill
 ********************************************************/

use JetBrains\PhpStorm\Pure;
use Npf\Boot\Polyfill;

if (!function_exists('fnmatch')) {
    define('FNM_PATHNAME', 1);
    define('FNM_NOESCAPE', 2);
    define('FNM_PERIOD', 4);
    define('FNM_CASEFOLD', 16);
    /**
     * @param string $pattern
     * @param string $string
     * @param int $flags
     * @return bool
     */
    function fnmatch(string $pattern, string $string, int $flags = 0): bool
    {
        return Polyfill::fnMatch($pattern, $string, $flags);
    }
}

if (!function_exists('array_is_list')) {
    /**
     * @param array $array
     * @return bool
     */
    #[Pure] function array_is_list(array $array): bool
    {
        return Polyfill::arrayIsList($array);
    }
}