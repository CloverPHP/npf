<?php

namespace Npf\Core;

use JetBrains\PhpStorm\Pure;

class Polyfill
{
    /**
     * Polyfill constructor.
     */
    final public function __construct()
    {
        /********************************************************
         * PHP 7.0 Polyfill
         ********************************************************/

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
    }

    /********************************************************
     * General Polyfill
     ********************************************************/

    /**
     * @param string $pattern
     * @param string $string
     * @param int $flags
     * @return bool
     */
    public static function fnMatch(string $pattern, string $string, int $flags): bool
    {
        $modifiers = null;
        $transforms = [
            '\*' => '.*',
            '\?' => '.',
            '\[\!' => '[^',
            '\[' => '[',
            '\]' => ']',
            '\.' => '\.',
            '\\' => '\\\\'
        ];

        // Forward slash in string must be in pattern:
        if ($flags & FNM_PATHNAME) {
            $transforms['\*'] = '[^/]*';
        }

        // Back slash should not be escaped:
        if ($flags & FNM_NOESCAPE) {
            unset($transforms['\\']);
        }

        // Perform case insensitive match:
        if ($flags & FNM_CASEFOLD) {
            $modifiers .= 'i';
        }

        // Period at start must be the same as pattern:
        if ($flags & FNM_PERIOD) {
            if (str_starts_with($string, '.') && !str_starts_with($pattern, '.')) return false;
        }

        $pattern = '#^'
            . strtr(preg_quote($pattern, '#'), $transforms)
            . '$#'
            . $modifiers;

        return (boolean)preg_match($pattern, $string);
    }

    /********************************************************
     * PHP 8.1 Polyfill
     ********************************************************/

    /**
     * @param array $array
     * @return bool
     */
    public static  function arrayIsList(array $array): bool
    {
        if ([] === $array)
            return true;
        $nextKey = -1;
        foreach ($array as $k => $v)
            if ($k !== ++$nextKey)
                return false;
        return true;
    }
}