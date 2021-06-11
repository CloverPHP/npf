<?php

namespace Npf\Core;

use __PHP_Incomplete_Class;
use ArrayIterator;
use ArrayObject;
use Countable;
use ReflectionClass;
use ReflectionException;
use ResourceBundle;
use SimpleXMLElement;
use function chr;
use function defined;
use function function_exists;
use function get_class;
use function gettype;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_object;
use function is_resource;
use function is_string;
use function ord;
use function strlen;
use const DIRECTORY_SEPARATOR;
use const E_USER_WARNING;
use const FILTER_VALIDATE_BOOLEAN;
use const PASSWORD_BCRYPT;
use const PHP_INT_SIZE;
use const PHP_OS;
use const PHP_VERSION_ID;
use const PREG_BACKTRACK_LIMIT_ERROR;
use const PREG_BAD_UTF8_ERROR;
use const PREG_BAD_UTF8_OFFSET_ERROR;
use const PREG_INTERNAL_ERROR;
use const PREG_NO_ERROR;
use const PREG_RECURSION_LIMIT_ERROR;
use const PREG_SPLIT_DELIM_CAPTURE;
use const PREG_SPLIT_NO_EMPTY;

class Polyfill
{

    private static $startAt = 1533462603;

    /**
     * Polyfill constructor.
     */
    final public function __construct()
    {
        /********************************************************
         * General Polyfill (Incase Unavaliable)
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
            function fnmatch($pattern, $string, $flags = 0)
            {
                return Polyfill::fnMatch($pattern, $string, $flags);
            }
        }

        /********************************************************
         * PHP 7.2 Polyfill
         ********************************************************/
        if (PHP_VERSION_ID < 70200) {

            if (!defined('PHP_FLOAT_DIG')) {
                define('PHP_FLOAT_DIG', 15);
            }
            if (!defined('PHP_FLOAT_EPSILON')) {
                define('PHP_FLOAT_EPSILON', 2.2204460492503E-16);
            }
            if (!defined('PHP_FLOAT_MIN')) {
                define('PHP_FLOAT_MIN', 2.2250738585072E-308);
            }
            if (!defined('PHP_FLOAT_MAX')) {
                define('PHP_FLOAT_MAX', 1.7976931348623157E+308);
            }
            if (!defined('PHP_OS_FAMILY')) {
                define('PHP_OS_FAMILY', $this->php_os_family());
            }

            if ('\\' === DIRECTORY_SEPARATOR && !function_exists('sapi_windows_vt100_support')) {
                /**
                 * @param mixed $stream
                 * @param null $enable
                 * @return bool
                 */
                function sapi_windows_vt100_support($stream, $enable = null)
                {
                    return $this->sapi_windows_vt100_support($stream, $enable);
                }
            }

            if (!function_exists('stream_isatty')) {
                /**
                 * @param mixed $stream
                 * @return bool
                 */
                function stream_isatty($stream)
                {
                    return $this->streamIsatty($stream);
                }
            }
            if (!function_exists('utf8_encode')) {
                /**
                 * @param string $string
                 * @return false|string
                 */
                function utf8_encode($string)
                {
                    return $this->utf8Encode($string);
                }
            }
            if (!function_exists('utf8_decode')) {
                /**
                 * @param string $string
                 * @return false|string
                 */
                function utf8_decode($string)
                {
                    return $this->utf8Decode($string);
                }
            }
            if (!function_exists('mb_ord')) {
                /**
                 * @param string $string
                 * @param string|null $encoding
                 * @return int|mixed
                 */
                function mb_ord($string, $encoding = null)
                {
                    return $this->mbOrd($string, $encoding);
                }
            }
            if (!function_exists('mb_chr')) {
                /**
                 * @param int $codepoint
                 * @param null $encoding
                 * @return array|false|string
                 */
                function mb_chr($codepoint, $encoding = null)
                {
                    return $this->mbChr($codepoint, $encoding);
                }
            }
            if (!function_exists('mb_scrub')) {
                /**
                 * @param string $string
                 * @param null $encoding
                 * @return array|false|string
                 */
                function mb_scrub($string, $encoding = null)
                {
                    $encoding = null === $encoding ? mb_internal_encoding() : $encoding;
                    return mb_convert_encoding($string, $encoding, $encoding);
                }
            }
        }

        /********************************************************
         * PHP 7.3 Polyfill
         ********************************************************/
        if (PHP_VERSION_ID < 70300) {
            if (!function_exists('is_countable')) {
                function is_countable($value)
                {
                    return is_array($value) || $value instanceof Countable || $value instanceof ResourceBundle || $value instanceof SimpleXmlElement;
                }
            }
            if (!function_exists('hrtime')) {
                self::$startAt = (int)microtime(true);
                /**
                 * @param false $as_number
                 * @return array|float|int
                 */
                function hrtime($as_number = false)
                {
                    return $this->hrtime($as_number);
                }
            }
            if (!function_exists('array_key_first')) {
                /**
                 * @param array $array
                 * @return int|string|bool
                 */
                function array_key_first(array $array)
                {
                    foreach ($array as $key => $value)
                        return $key;
                    return false;
                }
            }

            if (!function_exists('array_key_last')) {
                /**
                 * @param array $array
                 * @return int|string|null
                 */
                function array_key_last(array $array)
                {
                    return key(array_slice($array, -1, 1, true));
                }
            }
        }

        /********************************************************
         * PHP 7.4 Polyfill
         ********************************************************/
        if (PHP_VERSION_ID < 70400) {
            if (!function_exists('get_mangled_object_vars')) {
                /**
                 * @param object $object
                 * @return array|false|null
                 */
                function get_mangled_object_vars($object)
                {
                    return $this->getMangledObjectVars($object);
                }
            }
            if (!function_exists('mb_str_split') && function_exists('mb_substr')) {
                /**
                 * @param string $string
                 * @param int $length
                 * @param null $encoding
                 * @return array|false|string[]|null
                 */
                function mb_str_split($string, $length = 1, $encoding = null)
                {
                    return $this->mbStrSplit($string, $length, $encoding);
                }
            }
            if (!function_exists('password_algos')) {
                /**
                 * @return array
                 */
                function password_algos()
                {
                    return $this->passwordAlgos();
                }
            }
        }

        /********************************************************
         * PHP 8.0 Polyfill
         ********************************************************/
        if (PHP_VERSION_ID < 80000) {

            if (!defined('FILTER_VALIDATE_BOOL') && defined('FILTER_VALIDATE_BOOLEAN')) {
                define('FILTER_VALIDATE_BOOL', FILTER_VALIDATE_BOOLEAN);
            }

            if (!function_exists('fdiv')) {
                /**
                 * @param float $num1
                 * @param float $num2
                 * @return float|int
                 */
                function fdiv($num1, $num2)
                {
                    return $this->fdiv($num1, $num2);
                }
            }
            if (!function_exists('preg_last_error_msg')) {
                /**
                 * @return string
                 */
                function preg_last_error_msg()
                {
                    return $this->pregLastErrorMsg();
                }
            }
            if (!function_exists('get_debug_type')) {
                /**
                 * @param mixed $value
                 */
                function get_debug_type($value)
                {
                    return $this->getDebugType($value);
                }
            }
            if (!function_exists('get_resource_id')) {
                /**
                 * @param mixed $resource
                 * @return int
                 */
                function get_resource_id($resource)
                {
                    return $this->getResourceId($resource);
                }
            }
            if (!function_exists('str_contains')) {
                /**
                 * @param string $haystack
                 * @param string $needle
                 * @return bool
                 */
                function str_contains($haystack, $needle)
                {
                    return $this->strContains($haystack, $needle);
                }
            }
            if (!function_exists('str_starts_with')) {
                /**
                 * @param string $haystack
                 * @param string $needle
                 * @return bool
                 */
                function str_starts_with($haystack, $needle)
                {
                    return $this->strStartsWith($haystack, $needle);
                }
            }
            if (!function_exists('str_ends_with')) {
                /**
                 * @param string $haystack
                 * @param string $needle
                 * @return bool
                 */
                function str_ends_with($haystack, $needle)
                {
                    return $this->strEndsWith($haystack, $needle);
                }
            }
        }

        /********************************************************
         * PHP 8.1 Polyfill
         ********************************************************/
        if (PHP_VERSION_ID < 80100) {
            if (defined('MYSQLI_REFRESH_SLAVE') && !defined('MYSQLI_REFRESH_REPLICA')) {
                define('MYSQLI_REFRESH_REPLICA', 64);
            }

            if (!function_exists('array_is_list')) {
                /**
                 * @param array $array
                 * @return bool
                 */
                function array_is_list(array $array)
                {
                    return $this->arrayIsList($array);
                }
            }
            if (!function_exists('enum_exists')) {
                /**
                 * @param string $enum
                 * @return bool
                 */
                function enum_exists($enum)
                {
                    return class_exists($enum);
                }
            }
        }

    }

    /********************************************************
     * PHP 7.0 Polyfill
     ********************************************************/

    /**
     * @param $pattern
     * @param $string
     * @param int $flags
     * @return bool
     */
    public static function fnMatch($pattern, $string, $flags)
    {
        $modifiers = null;
        $transforms = array(
            '\*' => '.*',
            '\?' => '.',
            '\[\!' => '[^',
            '\[' => '[',
            '\]' => ']',
            '\.' => '\.',
            '\\' => '\\\\'
        );

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
            if (strpos($string, '.') === 0 && strpos($pattern, '.') !== 0) return false;
        }

        $pattern = '#^'
            . strtr(preg_quote($pattern, '#'), $transforms)
            . '$#'
            . $modifiers;

        return (boolean)preg_match($pattern, $string);
    }

    /********************************************************
     * PHP 7.2 Polyfill
     ********************************************************/

    public static function utf8Encode($s)
    {
        $s .= $s;
        $len = strlen($s);
        for ($i = $len >> 1, $j = 0; $i < $len; ++$i, ++$j) {
            switch (true) {
                case $s[$i] < "\x80":
                    $s[$j] = $s[$i];
                    break;
                case $s[$i] < "\xC0":
                    $s[$j] = "\xC2";
                    $s[++$j] = $s[$i];
                    break;
                default:
                    $s[$j] = "\xC3";
                    $s[++$j] = chr(ord($s[$i]) - 64);
                    break;
            }
        }
        return substr($s, 0, $j);
    }

    public static function utf8Decode($s)
    {
        $s = (string)$s;
        $len = strlen($s);
        for ($i = 0, $j = 0; $i < $len; ++$i, ++$j) {
            switch ($s[$i] & "\xF0") {
                case "\xC0":
                case "\xD0":
                    $c = (ord($s[$i] & "\x1F") << 6) | ord($s[++$i] & "\x3F");
                    $s[$j] = $c < 256 ? chr($c) : '?';
                    break;

                case "\xF0":
                    ++$i;
                // no break

                case "\xE0":
                    $s[$j] = '?';
                    $i += 2;
                    break;

                default:
                    $s[$j] = $s[$i];
            }
        }
        return substr($s, 0, $j);
    }

    public static function php_os_family()
    {
        if ('\\' === DIRECTORY_SEPARATOR) {
            return 'Windows';
        }

        $map = [
            'Darwin' => 'Darwin',
            'DragonFly' => 'BSD',
            'FreeBSD' => 'BSD',
            'NetBSD' => 'BSD',
            'OpenBSD' => 'BSD',
            'Linux' => 'Linux',
            'SunOS' => 'Solaris',
        ];

        return isset($map[PHP_OS]) ? $map[PHP_OS] : 'Unknown';
    }

    /**
     * @param $stream
     * @param null $enable
     * @return bool
     */
    public static function sapi_windows_vt100_support($stream, $enable)
    {
        if (!is_resource($stream)) {
            trigger_error('sapi_windows_vt100_support() expects parameter 1 to be resource, ' . gettype($stream) . ' given', E_USER_WARNING);
            return false;
        }
        $meta = stream_get_meta_data($stream);
        if ('STDIO' !== $meta['stream_type']) {
            trigger_error('sapi_windows_vt100_support() was not able to analyze the specified stream', E_USER_WARNING);
            return false;
        }
        // We cannot actually disable vt100 support if it is set
        if (false === $enable || !self::streamIsatty($stream))
            return false;

        // The native function does not apply to stdin
        $meta = array_map('strtolower', $meta);
        $stdin = 'php://stdin' === $meta['uri'] || 'php://fd/0' === $meta['uri'];

        return !$stdin
            && (false !== getenv('ANSICON')
                || 'ON' === getenv('ConEmuANSI')
                || 'xterm' === getenv('TERM')
                || 'Hyper' === getenv('TERM_PROGRAM'));
    }

    public static function streamIsatty($stream)
    {
        if (!is_resource($stream)) {
            trigger_error('stream_isatty() expects parameter 1 to be resource, ' . gettype($stream) . ' given', E_USER_WARNING);
            return false;
        }
        if ('\\' === DIRECTORY_SEPARATOR) {
            $stat = @fstat($stream);
            // Check if formatted mode is S_IFCHR
            return $stat && 0020000 === ($stat['mode'] & 0170000);
        }
        return function_exists('posix_isatty') && @posix_isatty($stream);
    }

    /**
     * @param int $code
     * @param string|null $encoding
     * @return array|false|string
     */
    public static function mbChr($code, $encoding)
    {
        if (0x80 > $code %= 0x200000)
            $s = chr($code);
        elseif (0x800 > $code)
            $s = chr(0xC0 | $code >> 6) . chr(0x80 | $code & 0x3F);
        elseif (0x10000 > $code)
            $s = chr(0xE0 | $code >> 12) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F);
        else
            $s = chr(0xF0 | $code >> 18) . chr(0x80 | $code >> 12 & 0x3F) . chr(0x80 | $code >> 6 & 0x3F) . chr(0x80 | $code & 0x3F);
        if ('UTF-8' !== $encoding = isset($encoding) ? $encoding : mb_internal_encoding())
            $s = mb_convert_encoding($s, $encoding, 'UTF-8');
        return $s;
    }

    /**
     * @param $s
     * @param $encoding
     * @return int|mixed
     */
    public static function mbOrd($s, $encoding)
    {
        if (null === $encoding)
            $s = mb_convert_encoding($s, 'UTF-8');
        elseif ('UTF-8' !== $encoding)
            $s = mb_convert_encoding($s, 'UTF-8', $encoding);
        if (1 === strlen($s))
            return ord($s);
        $code = ($s = unpack('C*', substr($s, 0, 4))) ? $s[1] : 0;
        if (0xF0 <= $code)
            return (($code - 0xF0) << 18) + (($s[2] - 0x80) << 12) + (($s[3] - 0x80) << 6) + $s[4] - 0x80;
        if (0xE0 <= $code)
            return (($code - 0xE0) << 12) + (($s[2] - 0x80) << 6) + $s[3] - 0x80;
        if (0xC0 <= $code)
            return (($code - 0xC0) << 6) + $s[2] - 0x80;
        return $code;
    }

    /********************************************************
     * PHP 7.3 Polyfill
     ********************************************************/

    /**
     * @param bool $asNum
     * @return array|float|int
     */
    public static function hrtime($asNum)
    {
        $ns = microtime(false);
        $s = substr($ns, 11) - self::$startAt;
        $ns = 1E9 * (float)$ns;

        if ($asNum) {
            $ns += $s * 1E9;

            return PHP_INT_SIZE === 4 ? $ns : (int)$ns;
        }

        return [$s, (int)$ns];
    }

    /********************************************************
     * PHP 7.4 Polyfill
     ********************************************************/
    /**
     * @param object $obj
     * @return array|false|null
     * @throws ReflectionException
     */
    public static function getMangledObjectVars($obj)
    {
        if (!is_object($obj)) {
            trigger_error('get_mangled_object_vars() expects parameter 1 to be object, ' . gettype($obj) . ' given', E_USER_WARNING);
            return null;
        }

        if ($obj instanceof ArrayIterator || $obj instanceof ArrayObject) {
            $reflector = new ReflectionClass($obj instanceof ArrayIterator ? 'ArrayIterator' : 'ArrayObject');
            $flags = $reflector->getMethod('getFlags')->invoke($obj);
            $reflector = $reflector->getMethod('setFlags');
            $reflector->invoke($obj, ($flags & ArrayObject::STD_PROP_LIST) ? 0 : ArrayObject::STD_PROP_LIST);
            $arr = (array)$obj;
            $reflector->invoke($obj, $flags);
        } else
            $arr = (array)$obj;
        return array_combine(array_keys($arr), array_values($arr));
    }

    /**
     * @param string $string
     * @param int $splitLength
     * @param string|null $encoding
     * @return array|false|string[]|null
     */
    public static function mbStrSplit($string, $splitLength, $encoding)
    {
        if (null !== $string && !is_scalar($string) && !(is_object($string) && method_exists($string, '__toString'))) {
            trigger_error('mb_str_split() expects parameter 1 to be string, ' . gettype($string) . ' given', E_USER_WARNING);
            return null;
        }
        if (1 > $splitLength = (int)$splitLength) {
            trigger_error('The length of each segment must be greater than zero', E_USER_WARNING);
            return false;
        }
        if (null === $encoding)
            $encoding = mb_internal_encoding();
        if ('UTF-8' === $encoding || in_array(strtoupper($encoding), ['UTF-8', 'UTF8'], true))
            return preg_split("/(.{{$splitLength}})/u", $string, null, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        $result = [];
        $length = mb_strlen($string, $encoding);
        for ($i = 0; $i < $length; $i += $splitLength)
            $result[] = mb_substr($string, $i, $splitLength, $encoding);
        return $result;
    }

    /**
     * @return array
     */
    public static function passwordAlgos()
    {
        $algos = [];
        if (defined('PASSWORD_BCRYPT'))
            $algos[] = PASSWORD_BCRYPT;
        if (defined('PASSWORD_ARGON2I'))
            $algos[] = PASSWORD_ARGON2I;
        if (defined('PASSWORD_ARGON2ID'))
            $algos[] = PASSWORD_ARGON2ID;
        return $algos;
    }

    /********************************************************
     * PHP 8.0 Polyfill
     ********************************************************/

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strContains($haystack, $needle)
    {
        return '' === $needle || false !== strpos($haystack, $needle);
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strStartsWith($haystack, $needle)
    {
        return 0 === strncmp($haystack, $needle, strlen($needle));
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return bool
     */
    public static function strEndsWith($haystack, $needle)
    {
        return '' === $needle || ('' !== $haystack && 0 === substr_compare($haystack, $needle, -strlen($needle)));
    }

    /**
     * @param float $dividend
     * @param float $divisor
     * @return float|int
     */
    public static function fdiv($dividend, $divisor)
    {
        return @($dividend / $divisor);
    }

    /**
     * @param mixed $value
     * @return string
     */
    public static function getDebugType($value)
    {
        switch (true) {
            case null === $value:
                return 'null';
            case is_bool($value):
                return 'bool';
            case is_string($value):
                return 'string';
            case is_array($value):
                return 'array';
            case is_int($value):
                return 'int';
            case is_float($value):
                return 'float';
            case is_object($value):
                break;
            case $value instanceof __PHP_Incomplete_Class:
                return '__PHP_Incomplete_Class';
            default:
                if (null === $type = @get_resource_type($value))
                    return 'unknown';
                if ('Unknown' === $type)
                    $type = 'closed';
                return "resource ($type)";
        }
        $class = get_class($value);
        if (false === strpos($class, '@'))
            return $class;
        return (get_parent_class($class) ?: key(class_implements($class)) ?: 'class') . '@anonymous';
    }

    /**
     * @param $res
     * @return int
     * @throws \Exception
     */
    public static function getResourceId($res)
    {
        if (!is_resource($res) && null === @get_resource_type($res))
            throw new \Exception(sprintf('Argument 1 passed to get_resource_id() must be of the type resource, %s given', get_debug_type($res)));
        return (int)$res;
    }

    /**
     * @return string
     */
    public static function pregLastErrorMsg()
    {
        switch (preg_last_error()) {
            case PREG_INTERNAL_ERROR:
                return 'Internal error';
            case PREG_BAD_UTF8_ERROR:
                return 'Malformed UTF-8 characters, possibly incorrectly encoded';
            case PREG_BAD_UTF8_OFFSET_ERROR:
                return 'The offset did not correspond to the beginning of a valid UTF-8 code point';
            case PREG_BACKTRACK_LIMIT_ERROR:
                return 'Backtrack limit exhausted';
            case PREG_RECURSION_LIMIT_ERROR:
                return 'Recursion limit exhausted';
            case PREG_NO_ERROR:
                return 'No error';
            default:
                return 'Unknown error';
        }
    }

    /********************************************************
     * PHP 8.1 Polyfill
     ********************************************************/

    /**
     * @param array $array
     * @return bool
     */
    public static function arrayIsList(array $array)
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