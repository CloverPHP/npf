<?php

namespace Npf\Core {

    use Npf\Exception\InternalError;
    use Npf\Library\Aes;
    use Npf\Library\Daemon;
    use Npf\Library\Gd;
    use Npf\Library\GeoIp;
    use Npf\Library\Monitor;
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
         * @var App
         */
        private $app;

        /**
         * @var array
         */
        private $component;

        /**
         * Session constructor.
         * @param App $app
         */
        final public function __construct(App $app)
        {
            $this->app = &$app;
        }

        /**
         * Session constructor.
         * @param $name
         * @return mixed
         * @throws InternalError
         */
        final public function create($name)
        {
            $className = "Npf\\Library\\" . ucfirst($name);
            if (!class_exists($className))
                throw new InternalError('Library Not found', $className);
            return new $className($this->app);
        }

        /**
         * Session constructor.
         * @param $name
         * @return mixed
         * @throws InternalError
         */
        final public function __get($name)
        {
            if (!isset($this->component[$name]))
                $this->component[$name] = $this->create($name);
            return $this->component[$name];
        }
    }
}