<?php

namespace Npf\Library {

    use BadMethodCallException;
    use Exception as ExceptionAlias;
    use GeoIp2\Database\Reader;
    use GeoIp2\Exception\AddressNotFoundException;
    use InvalidArgumentException;
    use MaxMind\Db\Reader\InvalidDatabaseException;
    use Npf\Core\App;
    use Npf\Core\Common;
    use Npf\Exception\InternalError;

    /**
     * Class Aes
     * @package Library\Crypt
     */
    class GeoIp
    {
        /**
         * @var App
         */
        private $app;
        /**
         * @var string
         */
        private $continent = null;
        /**
         * @var string
         */
        private $continentCode = null;
        /**
         * @var string
         */
        private $countryIsoCode = null;
        /**
         * @var string
         */
        private $country = null;
        /**
         * @var string
         */
        private $city = null;
        /**
         * @var string
         */
        private $ip = null;
        /**
         * @var string
         */
        private $dbFiles;

        /**
         * Aes constructor.
         * @param App $app
         * @throws InternalError
         */
        public function __construct(App &$app)
        {
            $this->app = &$app;
            $this->dbFiles = $this->app->config('Misc')->get('geoIpDB');
            $this->setIp(Common::getClientIp());
            $this->app->response->add('geoError', Common::getServerIp());
        }

        /**
         * @param $ip
         * @return bool
         */
        public function setIp($ip)
        {
            $this->continent = null;
            $this->continentCode = null;
            $this->country = null;
            $this->countryIsoCode = null;
            $this->city = null;
            try {
                if (file_exists($this->dbFiles)) {
                    $geoIp = new Reader($this->dbFiles);
                    $data = $geoIp->city($ip);
                    $this->ip = $ip;
                    $this->continent = $data->continent->name;
                    $this->continentCode = $data->continent->code;
                    $this->country = $data->country->name;
                    $this->countryIsoCode = $data->country->isoCode;
                    $this->city = $data->city->name;
                }
            } catch (BadMethodCallException $ex) {
                try {
                    if (!empty($geoIp) && $geoIp instanceof Reader) {
                        $data = $geoIp->country($this->ip);
                        $this->continent = $data->continent->name;
                        $this->continentCode = $data->continent->code;
                        $this->country = $data->country->name;
                        $this->countryIsoCode = $data->country->isoCode;
                    }
                } catch (ExceptionAlias $ex) {
                    return false;
                }
            } catch (AddressNotFoundException $ex) {
                $this->ip = null;
                return false;
            } catch (InvalidArgumentException $ex) {
                return false;
            } catch (InvalidDatabaseException $ex) {
                $this->dbFiles = null;
                return false;
            }
            return true;
        }

        /**
         * @return string
         */
        public final function getContinent()
        {
            return $this->continent;
        }

        /**
         * @return string
         */
        public final function getContinentCode()
        {
            return $this->continentCode;
        }

        /**
         * @return string
         */
        public final function getCountry()
        {
            return $this->country;
        }

        /**
         * @return string
         */
        public final function getCountryCode()
        {
            return $this->countryIsoCode;
        }

        /**
         * @return string
         */
        public final function getCity()
        {
            return $this->city;
        }
    }
}