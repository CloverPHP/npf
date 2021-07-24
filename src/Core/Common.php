<?php
declare(strict_types=1);

namespace Npf\Core {

    use DateInterval;
    use DatePeriod;
    use DateTime;
    use DateTimeZone;
    use JetBrains\PhpStorm\Pure;

    /**
     * Class Common
     * @package Utils
     */
    class Common
    {
        private static float $timestamp = 0;
        private static string $datetime = '';
        private static string $date = '';
        private static string $time = '';
        private static string $timezone = '';

        /**
         * Check is Utf8
         * @param string $content
         * @return bool
         */
        public static function isUtf8(string $content): bool
        {
            return boolval(preg_match('%(?:'
                . '[\xC2-\xDF][\x80-\xBF]'                // non-overlong 2-byte
                . '|\xE0[\xA0-\xBF][\x80-\xBF]'           // excluding overlongs
                . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'    // straight 3-byte
                . '|\xED[\x80-\x9F][\x80-\xBF]'           // excluding surrogates
                . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'        // planes 1-3
                . '|[\xF1-\xF3][\x80-\xBF]{3}'            // planes 4-15
                . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'        // plane 16
                . ')+%xs', $content));
        }

        /**
         * @param string $content
         * @param int $minSize
         * @return string
         */
        public static function compressContent(string $content, int $minSize = 13000): string
        {
            if (strlen($content) >= $minSize)
                return 'base64://' . base64_encode(@gzdeflate($content, 9));
            else
                return $content;
        }

        /**
         * @param string $compressed
         * @return string
         */
        public static function unCompressContent(string $compressed): string
        {
            if (substr($compressed, 0, 9) === 'base64://') {
                $compressed = base64_decode(substr($compressed, 9));

                $errLvl = error_reporting(0);
                $content = @gzinflate($compressed);
                error_reporting($errLvl);
                return $content;
            }
            return $compressed;
        }

        /**
         * Weighted Randomizer, using probability array pick a item from item array
         * @param array $items
         * @param array $weightedArray
         * @return mixed
         */
        public static function weightRndItem(array $items, array $weightedArray): mixed
        {
            if (!is_array($items) || !is_array($weightedArray) && count($items) !== count($weightedArray))
                return false;
            $sum = (int)array_sum($weightedArray);
            if ($sum === 0)
                return $items[array_rand($items)];
            $randomNumber = self::randomInt(1, $sum);
            foreach ($weightedArray as $key => $value) {
                $randomNumber -= $value;
                if ($randomNumber <= 0 && isset($items[$key]))
                    return $items[$key];
            }
            return false;
        }

        /**
         * Shuffle for associative arrays, preserves key=>value pairs
         * @param array $array
         * @return void
         */
        public static function shuffleAssoc(array &$array): void
        {
            $keys = array_keys($array);
            shuffle($keys);
            $new = [];
            foreach ($keys as $key)
                $new[$key] = $array[$key];
            $array = $new;
        }

        /**
         * @param int $min
         * @param int $max
         * @return int|false
         */
        public static function randomInt(int $min, int $max): bool|int
        {
            try {
                return random_int($min, $max);
            } catch (\Exception) {
                return false;
            }
        }

        /**
         * Weighted Randomizer,using probability to sort then items and return the array
         * @param array $items Item Array to sort via weighted randomizer
         * @param array $weightedArray Weighted Probability
         * @param bool $unique Return unique the items value.
         * @return array|bool
         */
        public static function weightRndArray(array $items,
                                              array $weightedArray = [],
                                              bool $unique = true): array|bool
        {
            $rndAry = [];
            if ($unique === true) {
                $weightAry = [];
                $Base = (double)abs(!empty($weightedArray) ? (double)min($weightedArray) : 0);
                foreach ($items as $value)
                    $weightAry[$value] = $Base + (isset($weightedArray[$value]) ? (double)$weightedArray[$value] :
                            0);
                $count = count($weightAry);
                for ($i = 0; $i < $count; $i++) {
                    $rKey = self::weightRndKey($weightAry);
                    unset($weightAry[$rKey]);
                    array_push($rndAry, $rKey);
                }
            } else {
                if (count($items) !== count($weightedArray))
                    return false;
                else {
                    $count = count($weightedArray);
                    for ($i = 0; $i < $count; $i++) {
                        $rKey = self::weightRndKey($weightedArray);
                        unset($weightedArray[$rKey]);
                        array_push($rndAry, $items[$rKey]);
                    }
                }
            }
            return $rndAry;
        }

        /**
         * Weighted Randomizer, using the assoc probability to pick a key
         * @param array $weightedArray
         * @return int|bool|array|string
         */
        public static function weightRndKey(array $weightedArray): int|bool|array|string
        {
            if (!is_array($weightedArray))
                return false;
            $sum = (int)array_sum($weightedArray);
            if ($sum === 0)
                return array_rand($weightedArray);
            $randomNumber = self::randomInt(1, $sum);
            foreach ($weightedArray as $key => $value) {
                $randomNumber -= $value;
                if ($randomNumber <= 0)
                    return $key;
            }
            return false;
        }

        /**
         * @param float $rate
         * @return boolean
         */
        public static function getRateWeight(float $rate): bool
        {
            $rate = round($rate, 7);
            if ($rate > 1)
                return true;
            $decimalLength = strlen(substr(strrchr((string)$rate, "."), 1));
            $power = pow(10, empty($decimalLength) ? 1 : $decimalLength);
            $rate *= $power;
            return (boolean)self::weightRndKey([$power - $rate, $rate]);
        }

        /**
         * @param int|float $odd
         * @param float $commission
         * @param float $factor
         * @return boolean
         */
        public static function getOddWeight(int|float $odd,
                                            float $commission = 0,
                                            float $factor = 1): bool
        {
            $commission = !empty($commission) ? $commission / 100 : 0;
            $rate = empty($odd) ? 0 : round((1 / $odd) * (1 - $commission) * $factor, 7);
            return self::getRateWeight($rate);
        }

        /**
         * @param array $oddItems
         * @param float $defaultCommission
         * @param float $defaultFactor
         * @param bool $probabilityOnly
         * @return int|string|array
         */
        public static function getOddsWeightedKey(array $oddItems,
                                                  float $defaultCommission = 0,
                                                  float $defaultFactor = 1,
                                                  bool $probabilityOnly = false): int|string|array
        {
            $decimalLengths = [];
            $rates = [];
            foreach ($oddItems as $key => $item) {
                $commission = isset($item['commission']) ? (double)$item['commission'] : (double)$defaultCommission;
                $commission = !empty($commission) ? $commission / 100 : 0;
                $factor = (double)($item['factor'] ?? $defaultFactor);
                $oddNow = (double)($item['odd'] ?? $item);
                $rate = empty($oddNow) ? 1 : round((1 / $oddNow) * (1 - $commission) * $factor, 7);
                $decimalLengths[] = strlen(substr((string)strrchr((string)$rate, "."), 1));
                $rates[$key] = $rate;
            }
            $power = pow(10, max($decimalLengths));
            $probability = [];
            foreach ($rates as $key => $rate)
                $probability[$key] = $rate * $power;
            return $probabilityOnly ? $probability : self::weightRndKey($probability);
        }

        /**
         * Check array is Assoc
         * @param string $prefix
         * @return string
         */
        public static function getTempFile(string $prefix = ''): string
        {
            return tempnam(sys_get_temp_dir(), $prefix);
        }

        /**
         * Check array is Assoc
         * @param array $arr
         * @return bool
         */
        #[Pure] public static function isAssocArray(array $arr): bool
        {
            if ([] === $arr) return false;
            return array_keys($arr) !== range(0, count($arr) - 1);
        }

        /**
         * Convert from string csv to array, all array value will only number.
         * @param string $content String to convert
         * @param string $dataType Number Data Type (int | double)
         * @return array
         */
        public static function strListNumeric(string $content, string $dataType = 'double'): array
        {
            $list = explode(",", str_replace(" ", "", $content));
            foreach ($list as $key => $val)
                if ($dataType === 'int')
                    $list[$key] = (int)trim($val);
                else
                    $list[$key] = (double)trim($val);
            return $list;
        }

        /**
         * Convert from string to array, all array value is string, and also decoration is upper string
         * @param string $content
         * @param string $decoration
         * @param string $delimiter
         * @return array
         */
        #[Pure] public static function strListString(string $content, string $decoration = 'upper', string $delimiter = ','): array
        {
            if (is_array($content))
                $list = $content;
            else
                $list = explode($delimiter, $content);
            switch ($decoration) {
                case 'upper':
                    foreach ($list as &$val)
                        $val = strtoupper(trim($val));
                    break;

                case 'lower':
                    foreach ($list as &$val)
                        $val = strtolower(trim($val));
                    break;

                default:
                    foreach ($list as &$val)
                        $val = trim($val);
                    break;
            }
            return $list;
        }

        /**
         * Convert from string to array, all array value is string, and also decoration is upper string
         * @param string $glue
         * @param string $content
         * @return string
         */
        public static function strPop(string $glue, string $content): string
        {
            if (!empty($glue) && !empty($content)) {
                $array = explode($glue, $content);
                array_pop($array);
                $content = implode($glue, $array);
            }
            return $content;
        }

        /**
         * Validate Array Data with the given validate pattern
         * @param string|array $patterns
         * @param array $data
         * @return array
         */
        public static function validator(string|array $patterns, array $data): array
        {
            if (!is_array($patterns))
                $patterns = [$patterns];
            $needed = [];
            foreach ($patterns as $key => $validate) {
                if (is_int($key)) {
                    $key = $validate;
                    $validate = 'must';
                }
                $value = $data[$key] ?? null;
                if (!empty($validate) && !self::validateValue($value, $validate))
                    $needed[$key] = ['validate' => $validate, 'given' => $value];
            }
            return $needed;
        }


        /**
         * Validate 1 Value
         * @param mixed $value
         * @param array|string $validates
         * @return bool
         */
        final public static function validateValue(mixed $value, array|string $validates): bool
        {
            $pass = true;
            $optional = false;
            if (!is_array($validates))
                $validates = !empty($validates) ? [$validates] : [];
            foreach ($validates as $key => $validate) {
                $extend = '';
                if (!is_int($key)) {
                    $type = $key;
                    $extend = $validate;
                } else {
                    if (!str_contains($validate, ":"))
                        $type = $validate;
                    else
                        list($type, $extend) = explode(":", $validate, 2);
                }
                $type = strtolower($type);
                switch ($type) {

                    case 'in':
                        if (!is_array($extend))
                            $extend = !empty($extend) ? [$extend] : [];
                        $pass = self::inArray($value, $extend, true);
                        break;

                    case 'like':
                        if (!is_array($extend))
                            $extend = !empty($extend) ? [$extend] : [];
                        $pass = self::inArray($value, $extend);
                        break;

                    case 'date':
                        $subfix = '';
                        $extend = (string)$extend;
                        $value = (string)$value;
                        if ($extend === 'time') $subfix = '\s\d{2}:\d{2}:\d{2}';
                        $pass = preg_match("/^((19[7-9]\d|2\d{3})-\d{2}-\d{2}){$subfix}$/", $value, $match) && strtotime($value) > 0;
                        break;

                    case 'match':
                        if ($extend != null)
                            $pass = $value === $extend;
                        break;

                    case 'array':
                        $pass = is_array($value);
                        break;

                    case 'array+':
                        $pass = is_array($value) && !empty($value);
                        break;

                    case 'boolean':
                        $pass = (boolean)filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;

                    case 'number':
                        $pass = is_numeric($value);
                        break;

                    case 'number+':
                        $pass = is_numeric($value) && !empty($value);
                        break;

                    case 'alphabet':
                        $pass = match ($extend) {
                            'upper' => ctype_upper($value),
                            'lower' => ctype_lower($value),
                            default => ctype_alpha($value),
                        };
                        break;

                    case 'alphanumber':
                        $pass = ctype_alnum($value);
                        break;

                    case 'ip':
                        $pass = match ($extend) {
                            'v4' => (boolean)filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4),
                            'v6' => (boolean)filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6),
                            default => (boolean)filter_var($value, FILTER_VALIDATE_IP),
                        };
                        break;

                    case 'email':
                        $pass = (boolean)filter_var($value, FILTER_VALIDATE_EMAIL);
                        break;

                    case 'url':
                        $pass = (boolean)filter_var($value, FILTER_VALIDATE_URL);
                        break;

                    case 'must':
                        $pass = !($value === null);
                        break;

                    case 'must+':
                        $pass = !empty($value);
                        break;

                    case 'min':
                        $extend = (int)$extend;
                        if (!empty($extend)) {
                            if (is_array($value) || is_object($value))
                                $pass = count($value) >= $extend;
                            else
                                $pass = strlen($value) >= $extend;
                        }
                        break;

                    case 'mbmin':
                        $extend = (int)$extend;
                        if (!empty($extend)) {
                            if (is_array($value) || is_object($value))
                                $pass = count($value) >= $extend;
                            else
                                $pass = mb_strlen($value) >= $extend;
                        }
                        break;

                    case 'max':
                        $extend = (int)$extend;
                        if (!empty($extend)) {
                            if (is_array($value) || is_object($value))
                                $pass = count($value) <= $extend;
                            else
                                $pass = strlen($value) <= $extend;
                        }
                        break;

                    case 'mbmax':
                        $extend = (int)$extend;
                        if (!empty($extend)) {
                            if (is_array($value) || is_object($value))
                                $pass = count($value) <= $extend;
                            else
                                $pass = mb_strlen($value) <= $extend;
                        }
                        break;

                    case 'len':
                        $extend = (int)$extend;
                        if (!empty($extend)) {
                            if (is_array($value) || is_object($value))
                                $pass = count($value) === $extend;
                            else
                                $pass = strlen($value) === $extend;
                        }
                        break;

                    case 'mblen':
                        $extend = (int)$extend;
                        if (!empty($extend)) {
                            if (is_array($value) || is_object($value))
                                $pass = count($value) === $extend;
                            else
                                $pass = mb_strlen($value) === $extend;
                        }
                        break;

                    case 'username':
                        $pass = (boolean)preg_match("/^[a-zA-Z][\w!@#$%^&*()+=]*$/", $value);
                        break;

                    case 'printable':
                        $pass = (boolean)preg_match("/^[[:print:]]+$/u", $value);
                        break;

                    case 'content':
                        $extend = (string)$extend;
                        $extend = preg_match("/^\s+$/", $extend) ? $extend : '';
                        $pass = static::safeUtf8($value, $extend);
                        break;

                    case 'amount':
                        $pass = (boolean)preg_match('/^\d{1,10}(\.\d{0,2})?$/', $value);
                        break;

                    case 'optional':
                        if ($value === NULL)
                            $optional = true;
                        continue 2;

                    case 'regexp':
                    case 'regex':
                        $pass = (boolean)preg_match($extend, $value);
                        break;

                    default:
                        if (is_array($validate))
                            $pass = in_array($value, $validate);
                        else
                            $pass = (boolean)preg_match($extend, $value);
                }
                if (!$pass)
                    return $optional;
            }
            return true;
        }

        /**
         * Convert File size to File Size Unit.
         * @param mixed $needle
         * @param array $haystack
         * @param bool $strict
         * @return bool is in_array
         */
        #[Pure] public static function inArray(mixed $needle, array $haystack, bool $strict = false): bool
        {
            if ($strict)
                return in_array($needle, $haystack, $strict);
            else {
                foreach ($haystack as $item)
                    if ($needle == $item)
                        return true;
                return false;
            }
        }

        /**
         * Check is Utf8
         * @param string $content
         * @param string $space
         * @return bool
         */
        public static function safeUtf8(string $content, string $space = '\x20'): bool
        {
            return boolval(preg_match('/^(?:'
                . '[\x21-\x7E' . $space . ']'
                . '|[\xC2-\xDF][\x80-\xBF]'                // non-overlong 2-byte
                . '|\xE0[\xA0-\xBF][\x80-\xBF]'           // excluding overlongs
                . '|[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}'    // straight 3-byte
                . '|\xED[\x80-\x9F][\x80-\xBF]'           // excluding surrogates
                . '|\xF0[\x90-\xBF][\x80-\xBF]{2}'        // planes 1-3
                . '|[\xF1-\xF3][\x80-\xBF]{3}'            // planes 4-15
                . '|\xF4[\x80-\x8F][\x80-\xBF]{2}'        // plane 16
                . ')+$/xs', $content));
        }

        /**
         * Convert File size to File Size Unit.
         * @param int $bytes Number of Bytes
         * @return string File Size with Unit
         */
        public static function fileSize2Unit(int $bytes): string
        {
            if ($bytes >= 1099511627776)
                return number_format($bytes / 1073741824, 2) . ' TB';
            elseif ($bytes >= 1073741824)
                return number_format($bytes / 1073741824, 2) . ' GB';
            elseif ($bytes >= 1048576)
                return number_format($bytes / 1048576, 2) . ' MB';
            elseif ($bytes >= 1024)
                return number_format($bytes / 1024, 2) . ' KB';
            elseif ($bytes > 1)
                return $bytes . ' bytes';
            elseif ($bytes == 1)
                return $bytes . ' byte';
            else
                return '0 bytes';
        }

        /**
         * Same as array_search, but will search entire array return those found keys.
         * @param mixed $needle
         * @param array $arrayHaystack
         * @return array
         */
        public static function arraySearchAll(mixed $needle, array $arrayHaystack): mixed
        {
            $result = [];
            foreach ($arrayHaystack as $key => $value)
                if ($value === $needle)
                    $result[] = $key;
            return (count($result) > 1 ? $result : $result[0]);
        }

        /**
         * Randomly generate given length ascii safe string
         * @param int $length
         * @param string $replaceCodeBase
         * @return string
         */
        public static function genCode(int $length = 8, string $replaceCodeBase = ''): string
        {
            $result = '';
            $codeBase = is_string($replaceCodeBase) && !empty($replaceCodeBase) ? $replaceCodeBase :
                '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $max = strlen($codeBase) - 1;
            if ($max < 1)
                return '';
            for ($i = 0; $i < $length; ++$i)
                $result .= $codeBase[self::randomInt(0, $max)];
            return $result;
        }

        /**
         * Generate UUID of version 4
         * @return string
         */
        public static function genUuidV4(): string
        {
            return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
        }

        /**
         * @param string $content
         * @param array $data
         * @param null $default
         * @param string $pattern
         * @return string
         */
        public static function strTemplateApply(string $content, array $data, $default = null, string $pattern = '%([\w]+)[^%]*%'): string
        {
            $matches = [];
            if (!is_array($data))
                $data = [];
            if ($default !== null && !is_string($default))
                $default = json_encode($default);
            if (preg_match_all("/({$pattern})/", $content, $matches, PREG_PATTERN_ORDER)) {
                $search = [];
                $replace = [];
                foreach ($matches[0] as $index => $match) {
                    $keyword = $matches[2][$index];
                    if (isset($data[$keyword])) {
                        if (!is_string($data[$keyword]))
                            $data[$keyword] = json_encode($data[$keyword]);
                        $search[] = $match;
                        $replace[] = $data[$keyword];
                    } elseif ($default !== null) {
                        $search[] = $match;
                        $replace[] = $default;
                    }
                }
                $content = str_replace($search, $replace, $content);
            }
            return $content;
        }

        /**
         * Read from file and decompress and deserialize
         * @param string $fileName
         * @param bool $compress
         * @return mixed
         */
        public static function fileToData(string $fileName, bool $compress = true): mixed
        {
            $Content = self::fileContents($fileName);
            if (!empty($Content)) {
                if ($compress)
                    return json_decode(gzuncompress($Content), true);
                else
                    return json_decode($Content, true);
            } else
                return $Content;
        }

        /**
         * Read file to data or write data to file
         * @param string $fileName
         * @param null $data
         * @return int|string|null
         */
        public static function fileContents(string $fileName, $data = null): int|string|null
        {
            if (!$data && file_exists($fileName))
                return file_get_contents($fileName);
            elseif ($data)
                return file_put_contents($fileName, $data);
            else
                return null;
        }

        /**
         * Serialize and compress data and write to file
         * @param string $fileName
         * @param mixed $data
         * @param bool $compress
         * @return int|string|null
         */
        public static function dataToFile(string $fileName, mixed $data, bool $compress = true): int|string|null
        {
            if ($compress)
                $data = @gzcompress(json_encode($data), 9);
            else
                $data = json_encode($data);
            return self::fileContents($fileName, $data);
        }

        /**
         * Get Server IP from $_SERVER global values.
         * @return string
         */
        public static function getServerIp(): string
        {
            if (isset($_SERVER["SERVER_ADDR"]))
                return $_SERVER["SERVER_ADDR"];
            else {
                if (stristr(PHP_OS, 'WIN')) {
                    exec('ipconfig /all', $catch);
                    foreach ($catch as $line) {
                        $pmatch = [];
                        if (preg_match('/IPv4 Address/i', $line, $pmatch)) {
                            $match = [];
                            if (preg_match('/(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})/i', $line, $match))
                                return $match[1];
                        }
                    }
                    return '';
                } else
                    return exec("ifconfig | grep -Eo 'inet (addr:)?([0-9]*\.){3}[0-9]*' | grep -Eo '([0-9]*\.){3}[0-9]*' | grep -v '127.0.0.1'");
            }
        }

        /**
         * Get Client IP from $_SERVER global value, will resolve proxy or direct.
         * @return string
         */
        public static function getClientIp(): string
        {
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $xForward = explode(",", $_SERVER['HTTP_X_FORWARDED_FOR']);
                return $xForward[0];
            } else
                return $_SERVER['REMOTE_ADDR'] ?? '';
        }

        /**
         * Get Origin Domain
         * @return string
         */
        #[Pure] public static function getOriginDomain(): string
        {
            if (array_key_exists('HTTP_ORIGIN', $_SERVER))
                $origin = $_SERVER['HTTP_ORIGIN'];
            elseif (array_key_exists('HTTP_REFERER', $_SERVER))
                $origin = $_SERVER['HTTP_REFERER'];
            else
                $origin = $_SERVER['REMOTE_ADDR'];
            $domain = parse_url($origin, PHP_URL_HOST);
            if (empty($domain))
                $domain = $origin;
            return $domain;
        }

        /**
         * Same as array_rand but is return value or array if more then 1.
         * @param array $array
         * @param int $picked
         * @return mixed
         */
        public static function arrayRandom(array $array, int $picked = 1): mixed
        {
            shuffle($array);
            $picked = $picked > count($array) ? count($array) : $picked;
            $result = [];
            for ($i = 0; $i < $picked; $i++)
                $result[] = $array[$i];
            return $picked === 1 ? $result[0] : $result;
        }

        /**
         * Same as arrayRandom but is return value or assoc array if more then 1.
         * @param array $array
         * @param int $picked
         * @return mixed
         */
        public static function arrayRandomAssoc(array $array, int $picked = 1): mixed
        {
            $total = count($array);
            if ($picked > $total)
                $picked = $total;
            $keys = array_keys($array);
            shuffle($keys);
            $result = [];
            for ($i = 0; $i < $picked; $i++)
                $result[$keys[$i]] = $array[$keys[$i]];
            return $picked === 1 ? reset($result) : $result;
        }

        /**
         * Array walk calculation, require
         * @param array $array
         * @param float $number
         * @param string $operate
         * @return array
         */
        public static function arrayCalcUp(array $array, float $number, string $operate = '*'): array
        {
            if (!is_array($array) || empty($array))
                return $array;

            foreach ($array as $key => $value) {
                switch (gettype($value)) {

                    case 'array':
                        $array[$key] = self::arrayCalcUp($value, $number, $operate);
                        break;

                    case 'integer':
                    case 'double':
                        switch ($operate) {
                            case '+':
                                $array[$key] += $number;
                                break;
                            case '-':
                                $array[$key] -= $number;
                                break;
                            case '*':
                                $array[$key] *= $number;
                                break;
                            case '/':
                                $array[$key] /= $number;
                                break;
                            case '\\':
                                $array[$key] = round($value / $number);
                                break;
                            case '^':
                                $array[$key] = pow($value, $number);
                                break;
                            case '%':
                                $array[$key] %= $number;
                                break;
                            case '`':
                                $array[$key] = pow($value, 1 / $number);
                                break;
                            case '<<':
                                $array[$key] <<= $number;
                                break;
                            case '>>':
                                $array[$key] >>= $number;
                                break;
                        }
                        break;
                }
            }
            return $array;
        }

        /**
         * Array Number Add Up
         * @param array|float $data
         * @param array|float $append
         * @param array $expectedKey
         * @return array|float
         */
        final static public function arrayNumAddUp(array|float $data,
                                                   array|float $append,
                                                   array $expectedKey = []): array|float
        {
            if (!is_array($expectedKey))
                $expectedKey = !empty($expectedKey) ? [$expectedKey] : [];
            if (is_array($append)) {
                if (!is_array($data))
                    $data = [];
                foreach ($append as $label => $value) {
                    if (!in_array($label, $expectedKey, true)) {
                        if (is_array($value)) {
                            if (!isset($data[$label]))
                                $data[$label] = [];
                            $data[$label] = self::arrayNumAddUp($data[$label], $value, $expectedKey);
                        } else {
                            if (!isset($data[$label]))
                                $data[$label] = (double)$value;
                            else {
                                $data[$label] = (double)$data[$label];
                                $data[$label] += $value;
                            }
                        }
                    }
                }
            } else {
                $data = (float)$data;
                $data += $append;
            }
            return $data;
        }

        /**
         * Array Number SubDown
         * @param array|float $data
         * @param array|float $subtrahend
         * @param bool $negative
         * @param bool $removeEmpty
         * @param array $expectedKey
         * @return array|float
         */
        final static public function arrayNumCutDown(array|float $data,
                                                     array|float $subtrahend,
                                                     bool $negative = false,
                                                     bool $removeEmpty = true,
                                                     array $expectedKey = []): array|float
        {
            if (!is_array($expectedKey))
                $expectedKey = !empty($expectedKey) ? [$expectedKey] : [];
            if (is_array($subtrahend)) {
                if (!is_array($data))
                    $data = [];
                foreach ($subtrahend as $label => $value) {
                    if (!in_array($label, $expectedKey, true)) {
                        if (is_array($value)) {
                            if (!isset($data[$label]))
                                $data[$label] = [];

                            $data[$label] = self::arrayNumCutDown($data[$label], $value, $negative, $removeEmpty, $expectedKey);
                            if ($removeEmpty === true && empty($data[$label]))
                                unset($data[$label]);
                        } else {
                            if (!isset($data[$label]))
                                $data[$label] = -1 * (double)$value;
                            else
                                $data[$label] += -1 * (double)$value;

                            if ($negative === false && $data[$label] < 0)
                                $data[$label] = 0;

                            if ($removeEmpty === true && $data[$label] === 0)
                                unset($data[$label]);
                        }
                    }
                }
            } else {
                $data = -1 * (double)$data;
                $data += -1 * (double)$subtrahend;
                if ($negative === false && $data < 0)
                    $data = 0;
            }
            return $data;
        }

        /**
         * Mosaic to string
         * @param string $content
         * @param int $start
         * @param int $end
         * @param int $length
         * @param string $toReplace
         * @return string
         */
        #[Pure] public static function strMosaic(string $content,
                                                 int $start = 0,
                                                 int $end = 6,
                                                 int $length = 0,
                                                 string $toReplace = 'x'): string
        {
            $subLength = strlen(substr($content, $start, $end));
            $strLength = $length !== 0 ? $length - (strlen($content) - $subLength) : $subLength;
            $strLength = $strLength <= 0 ? 1 : $strLength;
            return substr_replace($content, str_repeat($toReplace, $strLength), $start, $end);
        }

        /**
         * @param int $processId
         * @return array|bool
         */
        public static function processCPUTime(int $processId): array|bool
        {
            if (!$processId)
                return false;
            $path = "/proc/{$processId}/stat";
            if (!file_exists($path))
                return false;
            $info = @file_get_contents($path);
            if (!$info)
                return false;
            $data = explode(" ", $info);
            if (!isset($data[13]) || !isset($data[14]) || !isset($data[15]) || !isset($data[16]))
                return false;
            return [
                'utime' => (int)$data[13],
                'stime' => (int)$data[14],
            ];
        }

        /**
         * @param array $array
         */
        public static function arrayValNum(array &$array): void
        {
            array_walk($array, function (&$value) {
                if (is_numeric($value)) $value = (double)$value;
            });
        }

        /**
         * Get Specify datetime by timezone
         * @param string $timeZone
         * @param string $format
         * @param string|int|float|null $time
         * @return string|bool
         */
        public static function specifyTimeZone(string $timeZone,
                                               string $format = 'Y-m-d H:i:s',
                                               string|int|float|null $time = null): string|bool
        {
            try {
                $dateTime = new DateTime(is_string($time) ? $time : 'now', new DateTimeZone($timeZone));
                if (is_integer($time))
                    $dateTime->setTimestamp($time);
                return $dateTime->format($format);
            } catch (\Exception) {
                return false;
            }
        }

        /**
         * Get Specify datetime by timezone
         * @param string $fromTimeZone
         * @param string $targetTimeZone
         * @param string $format
         * @param string|int|float|null $time
         * @return string|bool
         */
        public static function convertTimeZone(string $fromTimeZone,
                                               string $targetTimeZone,
                                               string $format = 'Y-m-d H:i:s',
                                               string|int|float|null $time = null): bool|string
        {
            try {
                $dateTime = new DateTime(is_string($time) ? $time : 'now', new DateTimeZone($fromTimeZone));
                if (is_integer($time))
                    $dateTime->setTimestamp($time);
                $dateTime->setTimezone(new DateTimeZone($targetTimeZone));
                return $dateTime->format($format);
            } catch (\Exception) {
                return false;
            }
        }

        /**
         * Get current Timezone
         * @return string
         */
        #[Pure] public static function timezone(): string
        {
            return self::$timezone;
        }

        /**
         * Get Initial Date or current Date
         * @param bool $current
         * @return string
         */
        #[Pure] public static function date(bool $current = false): string
        {
            return $current === true ? date("Y-m-d") : self::$date;
        }

        /**
         * Get Initial Time or current Time
         * @param bool $current
         * @return string
         */
        #[Pure] public static function time(bool $current = false): string
        {
            return $current === true ? date("H:i:s") : self::$time;
        }

        /**
         * Get Initial Timestamp or current Timestamp
         * @param bool $current
         * @return float
         */
        #[Pure] public static function timestamp(bool $current = false): float
        {
            return $current === true ? microtime(true) : self::$timestamp;
        }

        /**
         * Delay the timestamp
         * @param int|float|string $second
         */
        public static function delay(int|float|string $second = 0): void
        {
            sleep($second);
            self::initial(self::$timezone);
        }

        /**
         * Initialize Date Time
         * @param string $timezone
         */
        public static function initial(string $timezone): void
        {
            if (empty(self::$timestamp))
                self::$timestamp = microtime(true);
            if (!empty($timezone) && is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
                date_default_timezone_set($timezone);
                self::$timezone = $timezone;
                self::$datetime = date("Y-m-d H:i:s");
                self::$date = date("Y-m-d");
                self::$time = date("H:i:s");
            }
        }

        /**
         * Convert Second to Time
         * @param int $seconds
         * @param int|null $size
         * @return string
         */
        #[Pure] public static function secondToTime(int $seconds = 0, ?int $size = null): string
        {
            switch ($size) {
                case 1:
                    $secs = floor($seconds / 60);
                    return sprintf('%02d:%02d', $secs, $secs);
                case 2:
                    $minutes = floor($seconds / 60);
                    $secs = floor($seconds % 60);
                    return sprintf('%02d:%02d', $minutes, $secs);
                default:
                    $hours = floor($seconds / 3600);
                    $minutes = floor($seconds / 60 % 60);
                    $secs = floor($seconds % 60);
                    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
            }
        }

        /**
         * Convert Time to Second
         * @param string $time H:i:s format
         * @return int
         */
        public static function timeToSecond(string $time): int
        {
            $hours = 0;
            $minutes = 0;
            $seconds = 0;
            $time = preg_replace("/^([\d]{1,2}):([\d]{2})$/", "00:$1:$2", $time);
            sscanf($time, "%d:%d:%d", $hours, $minutes, $seconds);
            return $hours * 3600 + $minutes * 60 + $seconds;
        }

        /**
         * Sort Multi Dimension Array
         * @param array $array Data to sort
         * @param mixed $orderColumns Order Method, key is field, value is sort method
         * @param bool $maintainKey maintain index association
         * @param int|array $defaultSortFlag
         */
        final static public function multiArraySort(array &$array,
                                                    mixed $orderColumns,
                                                    bool $maintainKey = false,
                                                    int|array $defaultSortFlag = SORT_ASC): void
        {
            $args = [];
            if (is_string($orderColumns))
                $orderColumns = [$orderColumns];
            if (!is_array($orderColumns))
                return;
            foreach ($orderColumns as $field => $orderFlags) {
                if (is_string($orderFlags)) {
                    $field = $orderFlags;
                    $orderFlags = $defaultSortFlag;
                }
                if (!is_array($orderFlags))
                    $orderFlags = [$orderFlags];
                $orderBy = [];
                foreach ($orderFlags as $orderMethod)
                    if (in_array($orderMethod, [SORT_ASC, SORT_DESC, SORT_REGULAR, SORT_NUMERIC,
                        SORT_STRING, SORT_LOCALE_STRING, SORT_NATURAL, SORT_FLAG_CASE,], true))
                        $orderBy[] = $orderMethod;
                if (!empty($orderBy)) {
                    $sortCol = [];
                    foreach ($array as $key => $row)
                        if (isset($row[$field]))
                            $sortCol[$key] = $row[$field];
                    $args[] = $sortCol;
                    foreach ($orderBy as $flag)
                        $args[] = $flag;
                }
            }
            if ($maintainKey) {
                $keys = array_keys($array);
                $args[] = &$array;
                $args[] = &$keys;
                call_user_func_array('array_multisort', $args);
                $array = array_combine($keys, $array);
            } else {
                $args[] = &$array;
                call_user_func_array('array_multisort', $args);
            }
        }

        /**
         * Get numbers of decimal without round up or down.
         * @param float $number
         * @param int $decimal
         * @return float
         */
        #[Pure] public static function leaveDecimal(float $number, int $decimal = 2): float
        {
            $decimal = $decimal + 1;
            $string = strval($number);
            $point = strpos($string, ".");
            $number = $point !== false ? (double)substr($string, 0, $point + $decimal) : $number;
            return round($number, $decimal);
        }

        /**
         * @param float|int $amount
         * @param float|int $divNum
         * @param int $decimal
         * @return float|int
         */
        #[Pure] public static function calDiv(float|int $amount, float|int $divNum, int $decimal = 2): float|int
        {
            $base = pow(10, $decimal);
            $module = (($amount * $base) % $divNum) / $base;
            return $amount - $module;
        }

        /**
         * sorting
         * @param array $array
         * @param int $sort_flags
         * @return void
         */
        public static function kSortRecursive(array &$array,
                                              int $sort_flags = SORT_REGULAR): void
        {
            ksort($array, $sort_flags);
            foreach ($array as &$item)
                if (is_array($item))
                    self::kSortRecursive($item, $sort_flags);
        }

        /**
         * Split the array to any available combination array, e.g. $Array = (1,2,3), $Choose = 2  return = (1,2),(1,3),(2,3)
         * @param array $array Array to split
         * @param int $number Number to split
         * @return array Split Result
         */
        public static function arrayCombination(array $array, int $number): array
        {
            /**
             * @param array $result
             * @param array $composed
             * @param int $start
             * @param int $number
             * @param array $array
             * @param int $size
             */
            $composer = function (array &$result,
                                  array &$composed,
                                  int $start,
                                  int $number,
                                  array $array,
                                  int $size) use (&$composer) {
                if ($number == 0)
                    array_push($result, $composed);
                else
                    for ($i = $start; $i <= $size - $number; ++$i) {
                        array_push($composed, $array[$i]);
                        if ($number - 1 == 0)
                            array_push($result, $composed);
                        else
                            $composer($result, $composed, $i + 1, $number - 1, $array, $size);
                        array_pop($composed);
                    }
            };

            $size = count($array);
            $combination = [];
            $composed = [];
            $composer($combination, $composed, 0, $number, $array, $size);
            return $combination;
        }

        /**
         * Get Current Date -1 Days if current time < $offset.
         * @param string $offset
         * @param string $dateTime
         * @return bool|string
         */
        public static function offsetDate(string $offset = '+1 day', string $dateTime = ''): bool|string
        {
            try {
                $date = new DateTime($dateTime);
                $date->modify($offset);
                return $date->format('Y-m-d');
            } catch (\Exception) {
                return false;
            }
        }

        /**
         * @param string $fromDate
         * @param string $toDate
         * @return int
         */
        #[Pure] public static function dateDiff(string $fromDate, string $toDate): int
        {
            $fromDate = date_create($fromDate);
            $toDate = date_create($toDate);
            $diff = date_diff($fromDate, $toDate, true);
            return $diff->days;
        }

        /**
         * @param string $start
         * @param string $end
         * @return array
         */
        public static function dateRange(string $start, string $end): array
        {
            $result = [];
            try {
                $begin = new DateTime($start);
                $end = new DateTime($end);
                $end = $end->modify('+1 day');

                $interval = DateInterval::createFromDateString('1 day');
                $period = new DatePeriod($begin, $interval, $end);

                foreach ($period as $dt)
                    /**
                     * @var $dt DateTime
                     */
                    $result[] = $dt->format("Y-m-d");
            } catch (\Exception) {
                $result = [];
            }
            return $result;
        }


        /**
         * Get Initial DateTime or current DateTime
         * @param bool $current
         * @return string
         */
        #[Pure] public static function datetime(bool $current = false): string
        {
            return $current === true ? date("Y-m-d H:i:s") : self::$datetime;
        }

        /**
         * Array Add Up
         * @param array $data
         * @param array $append
         * @return mixed
         */
        #[Pure] final static public function addAppend(mixed $data, mixed $append): mixed
        {
            switch (gettype($append)) {
                case 'integer':
                case 'double':
                    $data = (double)$data;
                    $data += (double)$append;
                    break;
                case 'string':
                    $data = (string)$data;
                    $data .= $append;
                    break;
                default:
                    $data = $append;
            }
            return $data;
        }

        /**
         * @param string $content
         * @param bool $toFullAngle
         * @param bool $includeSymbol
         * @return string
         */
        final static public function convertUtfAngle(string $content,
                                                     bool $toFullAngle = false,
                                                     bool $includeSymbol = false): string
        {
            $fullAngle = [
                'alphanumeric' => [
                    '０', '１', '２', '３', '４', '５', '６', '７', '８', '９',
                    'Ａ', 'Ｂ', 'Ｃ', 'Ｄ', 'Ｅ', 'Ｆ', 'Ｇ', 'Ｈ', 'Ｉ', 'Ｊ',
                    'Ｋ', 'Ｌ', 'Ｍ', 'Ｎ', 'Ｏ', 'Ｐ', 'Ｑ', 'Ｒ', 'Ｓ', 'Ｔ',
                    'Ｕ', 'Ｖ', 'Ｗ', 'Ｘ', 'Ｙ', 'Ｚ', 'ａ', 'ｂ', 'ｃ', 'ｄ',
                    'ｅ', 'ｆ', 'ｇ', 'ｈ', 'ｉ', 'ｊ', 'ｋ', 'ｌ', 'ｍ', 'ｎ',
                    'ｏ', 'ｐ', 'ｑ', 'ｒ', 'ｓ', 'ｔ', 'ｕ', 'ｖ', 'ｗ', 'ｘ',
                    'ｙ', 'ｚ'
                ],
                'symbol' => [
                    '－', '　', '：', '．', '，', '／', '％', '＃', '！', '＠',
                    '＆', '（', '）', '＜', '＞', '＂', '＇', '？', '［', '］',
                    '｛', '｝', '＼', '｜', '＋', '＝', '＿', '＾', '￥', '￣',
                    '｀'
                ],
            ];
            $semiAngle = [
                'alphanumeric' => [ //半角
                    '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
                    'A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J',
                    'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T',
                    'U', 'V', 'W', 'X', 'Y', 'Z', 'a', 'b', 'c', 'd',
                    'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n',
                    'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x',
                    'y', 'z'
                ],
                'symbol' => [
                    '-', ' ', ':', '.', ',', '/', '%', '#', '!', '@',
                    '&', '(', ')', '<', '>', '"', '\'', '?', '[', ']',
                    '{', '}', '\\', '|', '+', '=', '_', '^', '$', '~',
                    '`'
                ],
            ];
            if ($includeSymbol) {
                $fullAngle = array_merge($fullAngle['alphanumeric'], $fullAngle['symbol']);
                $semiAngle = array_merge($semiAngle['alphanumeric'], $semiAngle['symbol']);
            } else {
                $fullAngle = $fullAngle['alphanumeric'];
                $semiAngle = $semiAngle['alphanumeric'];
            }
            if ($toFullAngle === false)
                return str_replace($fullAngle, $semiAngle, $content);  //全角到半角
            else
                return str_replace($semiAngle, $fullAngle, $content);  //半角到全角
        }
    }
}