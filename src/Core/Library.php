<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\InternalError;
    use Npf\Library\Aes;
    use Npf\Library\Gd;
    use Npf\Library\GeoIp;
    use Npf\Library\Rpc;
    use Npf\Library\S3;
    use Npf\Library\TwoFactorAuth;
    use Npf\Library\UserAgent;
    use Npf\Library\XPathDom;

    /**
     * Class Library
     * @package Npf\Core
     * @property Rpc $rpc
     * @property XPathDom $XPathDom
     * @property Gd $gd
     * @property Aes $aes
     * @property UserAgent $UserAgent
     * @property S3 $S3
     * @property GeoIp $GeoIp
     * @property TwoFactorAuth $TwoFactorAuth
     */
    class Library
    {
        /**
         * @var array
         */
        private array $component = [];

        /**
         * Session constructor.
         * @param App $app
         */
        final public function __construct(private App $app)
        {
        }

        /**
         * Session constructor.
         * @param string $name
         * @return mixed
         * @throws InternalError
         */
        final public function create(string $name): mixed
        {
            $className = "Npf\\Library\\" . ucfirst($name);
            if (!class_exists($className))
                throw new InternalError('Library Not found', $className);
            return new $className($this->app);
        }

        /**
         * Session constructor.
         * @param string $name
         * @return mixed
         * @throws InternalError
         */
        final public function __get(string $name): mixed
        {
            if (!isset($this->component[$name]))
                $this->component[$name] = $this->create($name);
            return $this->component[$name];
        }
    }
}