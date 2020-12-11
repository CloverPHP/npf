<?php
declare(strict_types=1);

namespace Npf\Library {

    use BadMethodCallException;
    use Exception ;
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
         * @var string|null
         */
        private string|null $continent;
        /**
         * @var string|null
         */
        private string|null $continentCode = null;
        /**
         * @var string|null
         */
        private string|null $countryIsoCode = null;
        /**
         * @var string|null
         */
        private string|null $country = null;
        /**
         * @var string|null
         */
        private string|null $city = null;
        /**
         * @var string|null
         */
        private string|null $ip = null;
        /**
         * @var string|null
         */
        private string|null $dbFiles;

        /**
         * Aes constructor.
         * @param App $app
         * @throws InternalError
         */
        public function __construct(private App $app)
        {
            $this->dbFiles = $this->app->config('Misc')->get('geoIpDB');
            $this->setIp(Common::getClientIp());
            $this->app->response->add('geoError', Common::getServerIp());
        }

        /**
         * @param string $ip
         * @return self
         */
        public function setIp(string $ip): self
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
            } catch (BadMethodCallException) {
                try {
                    if (!empty($geoIp) && $geoIp instanceof Reader) {
                        $data = $geoIp->country($this->ip);
                        $this->continent = $data->continent->name;
                        $this->continentCode = $data->continent->code;
                        $this->country = $data->country->name;
                        $this->countryIsoCode = $data->country->isoCode;
                    }
                } catch (Exception) {
                    return $this;
                }
            } catch (AddressNotFoundException) {
                $this->ip = null;
                return $this;
            } catch (InvalidArgumentException) {
                return $this;
            } catch (InvalidDatabaseException) {
                $this->dbFiles = null;
                return $this;
            }
            return $this;
        }

        /**
         * @return string|null
         */
        public final function getContinent(): ?string
        {
            return $this->continent;
        }

        /**
         * @return string|null
         */
        public final function getContinentCode(): ?string
        {
            return $this->continentCode;
        }

        /**
         * @return string|null
         */
        public final function getCountry(): ?string
        {
            return $this->country;
        }

        /**
         * @return string|null
         */
        public final function getCountryCode(): ?string
        {
            return $this->countryIsoCode;
        }

        /**
         * @return string|null
         */
        public final function getCity(): ?string
        {
            return $this->city;
        }
    }
}