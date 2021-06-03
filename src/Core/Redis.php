<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Core\Redis\RedisBase;
    use Npf\Exception\InternalError;

    /**
     * Class ShadingRedis
     *
     * Key Timing
     * @method ttl(string $name): int
     * @method pTtl(string $name): int
     * @method expireAt(string $name, int $timestamp)
     * @method pExpire(string $name, int $milliSecond)
     * @method pExpireAt(string $name, int $milliSecondTimestamp)
     * @method expire(string $name, int $second): boolean
     *
     * Standard key
     * @method move(string $name, int $dbIndex): int
     * @method object(string $method, string $name): mixed
     * @method type(string $name): string
     * @method exists(string $name): int
     * @method persist(string $name): int
     * @method get(string $name)
     * @method strLen(string $name): int
     * @method getUnserialise(string $name)
     * @method set(string $name, string|int|float $value, int $lifetime = 0, boolean $noExists = null)
     * @method setEx(string $name, string|int|float $value, int $lifetime = 0)
     * @method setSerialise(string $name, mixed $value, int $lifetime = 0, boolean $noExists = null)
     * @method setNx(string $name, string|int|float $value, int $lifetime = 0)
     * @method setNxSerialise(string $name, mixed $value, int $lifetime = 0)
     * @method pSetEx(string $name, int $milliSec): bool
     * @method getSet(string $name, string $value): mixed
     * @method append(string $name, string $value): int
     * @method randomKey(): string
     * @method rename(string $oriKey, string $newKey): boolean
     * @method renameNx(string $oriKey, string $newKey): boolean
     * @method incr(string $name): int
     * @method incrBy(string $name, int $value): int
     * @method incrByFloat(string $name, float $value): float
     * @method decr(string $name): int
     * @method decrBy(string $name, int $value): int
     * @method getRange(string $name, int $start, int $end)
     * @method setRange(string $name, int $offset, string $value)
     * @method getBit(string $name, int $offset): int
     * @method setBit(string $name, int $offset, int $value): int
     *
     * M Series (Multi)
     * @method mGet(string ...$name): array
     * @method mSet(string ...$args): bool
     * @method mSetNx(string ...$args): int
     *
     * L Series (List)
     * @method sort(string $name, ...$arg): array
     * @method lPush(string $name, string ...$value): int
     * @method rPush(string $name, string ...$value): int
     * @method lPushX(string $name, string $value): int
     * @method rPushX(string $name, string $value): int
     * @method lPop(string $name): string
     * @method rPop(string $name): string
     * @method lLen(string $name): int
     * @method lRem(string $name, int $index, string $value): int
     * @method lTrim(string $name, int $start, int $end): bool
     * @method lIndex(string $name, int $index): null|bool|string
     * @method lRange(string $name, int $start, int $end): array
     * @method lSet(string $name, int $index, string $value): bool
     * @method lInsert(string $name, string $position, string $search, string $value): int
     *
     * S Series
     * @method sAdd(string $name, string ...$member): int
     * @method sRem(string $name, string ...$member): int
     * @method sCard(string $name): int
     * @method sMove(string $oriName, string $newName, string $member): int
     * @method sPop(string $name, int $count): array
     * @method sRandMember(string $name, int $count): array
     * @method sMembers(string $name): array
     * @method sDiff (string $source, string $destination): array
     * @method sDiffStore(string $name, string $source, string $destination): int
     * @method sIsMember(string $name, string $value): int
     * @method sInter(string $source, string $destination): array
     * @method sInterStore(string $name, string $source, string $destination): int
     * @method sUnion(string $source, string $destination): array
     * @method sUnionStore(string $name, string $source, string $destination): int
     *
     * H Series (Hash Map)
     * @method hGet(string $name, string $field): string|null
     * @method hSet(string $name, string $field, string $value): int
     * @method hSetNx(string $name, string $field, string $value): int
     * @method hDel(string $name, string ...$field): int
     * @method hLen(string $name): int
     * @method hKeys(string $name): array
     * @method hVals(string $name): array
     * @method hGetAll(string $name): array
     * @method hExists(string $name, string $field): int
     * @method hIncrBy(string $name, string $field, int $number): int
     * @method hIncrByFloat(string $name, string $field, float $number): float
     * @method hMSet(string $name, string ...$args): bool
     * @method hMGet(string $name, string ...$args): array
     *
     * Z Series (Z Score)
     * @method zAdd(string $name, string ...$args): int
     * @method zRem(string $name, string $member): int
     * @method zRange(string $name, int $start, int $end, string $withScores)
     * @method zRangeByScore(string $name, int $min, int $max, string $withScores): array
     * @method zRevRangeByScore(string $name, int $max, int $min, string $withScores): array
     * @method zCount(string $name, int $min, int $max): int
     * @method zCard(string $name): int
     * @method zScore(string $name, string $member): string
     * @method zRank(string $name, string $member): int|null
     * @method zRevRank(string $name, string $member): int|null
     * @method zInter(int $numberKey, string ...$args): array
     * @method zInterStore(string $destination, int $numberKey, string ...$args): array
     * @method zUnion(int $numberKey, string ...$args): array
     * @method zUnionStore(string $name, int $numberKey, string ...$args): int
     * @method zIncrBy(string $name, int $increment, string $member)
     * @method rPopLPush(string $source, string $destination): string
     *
     * Db Function
     * @method flushDB(int $dbIndex): bool
     * @method flushAll(): bool
     * @method dbSize(): int
     * @method time(): array
     * @method dump(string $name): string
     * @method getLastError()
     * @method clearLastError()
     * @method _prefix()
     *
     * Transaction (Watch/Unwatch)
     * @method watch()
     * @method unwatch()
     *
     * Redis server Control/Info
     * @method info(string $section): string
     * @method save(): bool
     * @method lastSave(): int
     * @method close()
     */
    class Redis
    {
        /**
         * @var array
         */
        private array $redis;
        private array $instance;
        private int $size = 0;
        private int $db = 0;
        private int $timeout = 0;
        private int $rwTimeout = 0;
        private string $authPass = '';
        private bool $allowReconnect = true;
        private bool $persistent = false;
        private string $postHash = '';
        private string $tempHash = '';
        private string $hashRegex = "/\{(?<key>\w+)\}/";
        private array $restrictFnc = ['PING', 'SAVE', 'SCAN', 'OBJECT', 'CONNECT', 'OPEN',
            'PCONNECT', 'POPEN', 'AUTH', 'ECHO', 'BGREWRITEAOF', 'BGSAVE', 'CONFIG',
            'FLUSHALL', 'RESETSTAT', 'SLAVEOF', 'SLOWLOG', 'PEXPIRE', 'PEXPIREAT',
            'BITCOUNT', 'BITOP', 'PTTL', 'MIGRATE', 'SSCAN', 'ZSCAN', 'PSUBSCRIBE',
            'PUBLISH', 'SUBSCRIBE', 'PUBSUB', 'EVAL', 'EVALSHA', 'SCRIPT', 'GETLASTERROR',
            'CLEARLASTERROR', '_SERIALIZE', '_UNSERIALIZE', 'GETOPTION', 'DBSIZE', 'MGET',
            'GETMULTIPLE', 'GETMATCH', 'GETCOUNT', 'KEYS', 'GETKEYS', 'MULTI', 'WATCH',
            'EXEC'];

        /**
         * Redis constructor.
         * @param App $app
         * @throws InternalError
         */
        final public function __construct(private App $app)
        {
            $config = $app->config('Redis');
            if (!$config->get('enable', false))
                throw new InternalError('Redis is not enable.');
            $this->setPostHash($config->get('postHash'));
            if (!empty($config->instance) && isset($config->db) &&
                !empty($config->timeout) && isset($config->authPass) &&
                !empty($config->rwTimeout)
            ) {
                $size = count($config->instance);
                $range = range(0, $size - 1);
                $this->allowReconnect = (bool)$config->get('allowReconnect');
                if (array_keys($config->instance) === $range) {
                    $this->persistent = isset($config->persistent) && $config->persistent;
                    $this->instance = $config->instance;
                    $this->size = $size;
                    $this->db = (int)$config->db;
                    $this->authPass = (string)$config->authPass;
                    $this->timeout = (int)$config->timeout;
                    $this->rwTimeout = (int)$config->rwTimeout;
                }
            }
        }

        /**
         * @param string $postHash
         * @return self
         */
        final public function setPostHash(string $postHash): self
        {
            if (!empty($postHash))
                $this->postHash = substr($postHash, 0, 1) === "{" ? $postHash : "{{$postHash}}";
            return $this;
        }

        /**
         */
        final public function __destruct()
        {
            if (!empty($this->redis)) {
                $this->app->profiler->timerStart("redis");
                foreach ($this->redis as $redis)
                    if (method_exists($redis, '__destruct'))
                        $redis->__destruct();
                $this->redis = [];
                $this->app->profiler->saveQuery("close", "redis");
            }
        }

        /**
         * @param $tempHash
         * @return self
         */
        final public function tempHash($tempHash): self
        {
            $tempHash = (string )$tempHash;
            if (!empty($tempHash))
                $this->tempHash = substr($tempHash, 0, 1) === "{" ? $tempHash : "{{$tempHash}}";
            return $this;
        }

        /**
         * @param int $db
         * @return bool
         * @throws InternalError
         */
        final public function select(int $db): bool
        {
            $success = true;
            foreach ($this->redis as $redis)
                if ($redis instanceof RedisBase && !$redis->select($db))
                    $success = false;
            $this->db = $db;
            return $success;
        }

        /**
         * getMultiple alias keys
         * @param string $key
         * @return array
         */
        final public function getMultiple(string $key = ''): array
        {
            return $this->keys($key);
        }


        /**
         * Sharding Redis Keys Performance
         * @param string $key
         * @return array
         */
        final public function getKeys(string $key = ''): array
        {
            return $this->keys($key);
        }

        /**
         * Sharding Redis Keys Performance
         * @param string $key
         * @return array|bool
         */
        final public function keys(string $key = ''): array|bool
        {
            if ($this->hasHash($key)) {
                return $this->__call('keys', [$key]);
            } else
                return false;
        }

        /**
         * Check is have hash in key
         * @param string $key
         * @return boolean
         */
        private function hasHash(string $key): bool
        {
            $matches = [];
            if (empty($key))
                return false;
            preg_match($this->hashRegex, $key, $matches);
            return !empty($matches) && !empty($matches['key']);
        }

        /**
         * @param string $name
         * @param array $args
         * @return mixed
         */
        public function __call(string $name, array $args): mixed
        {
            $this->app->profiler->timerStart("redis");
            if (in_array($name, $this->restrictFnc, true))
                return false;
            $key = '';
            if (!empty($args))
                $key = $args[0];
            $index = $this->getIndex($key);
            if ($index !== false) {
                $redis = &$this->redis[$index];
                $ret = !empty($redis) ? call_user_func_array([$redis, $name], $args) : false;
            } else
                $ret = false;

            $rptArgs = '';
            foreach ($args as $arg)
                $rptArgs .= (" " . (is_string($arg) || is_numeric($arg) ? $arg : gettype($arg)));
            $this->app->profiler->saveQuery(sprintf("%s %s", $name, $rptArgs), "redis");
            return $ret;
        }

        /**
         * Get Shading Index & Load index of redis not loaded
         * @param string $key
         * @return int|bool
         */
        private function getIndex(string $key = ''): int|bool
        {
            if (!is_string($key))
                $key = '';
            $hash = $this->getHash($key . $this->postHash . $this->tempHash);
            $this->tempHash = '';
            $index = empty($hash) || empty($this->size) ? 0 : (int)bcmod(sprintf("%u", crc32
            ($hash)), (string)$this->size);
            if (!isset($this->redis[$index])) {
                if (isset($this->instance[$index]))
                    $this->redis[$index] = new RedisBase($this->app, $this->instance[$index], $this->authPass, $this->
                    db, $this->timeout, $this->rwTimeout, $this->allowReconnect, $this->persistent);
                else
                    return false;
            }
            return $index;
        }

        /**
         * Get Key Hash
         * @param string $key
         * @return string Hash
         */
        private function getHash(string $key): string
        {
            $matches = [];
            if (empty($key))
                return '';
            preg_match($this->hashRegex, $key, $matches);
            return !empty($matches) && !empty($matches['key']) ? $matches['key'] : $key;
        }

        /**
         * Restate Shading Redis.
         */
        final public function restate(): void
        {
            foreach ($this->redis as $index => $redis)
                unset($this->redis[$index]);
            usleep(100000);
        }

        /**
         * getMultiple alias keys
         * @param string $keys
         * @return int
         */
        final public function del(string $keys): int
        {
            if (is_array($keys)) {
                $deleteKeys = [];
                foreach ($keys as $key) {
                    $index = $this->getIndex($key);
                    if (!isset($deleteKeys[$index]))
                        $deleteKeys[$index] = [];
                    $deleteKeys[$index][] = $key;
                }
                $success = 0;
                foreach ($deleteKeys as $deleteKey)
                    $success += (int)$this->__call('del', $deleteKey);
                return $success;
            } else
                return (int)$this->__call('del', [$keys]);
        }
    }
}