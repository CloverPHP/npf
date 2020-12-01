<?php

namespace Npf\Library {

    use Npf\Core\App;

    /**
     * Class Aes
     * @package Library\Crypt
     */
    class Aes
    {
        /**
         * @var App
         */
        private $app;
        private $supportMode = [
            'AES-256-ECB',
            'AES-256-CBC',
            'AES-256-CFB',
            'AES-256-OFB',
            'AES-256-CTR',
        ];
        private $cipherMode = 'AES-256-CTR';
        private $secret_key = '';
        private $iv = '';

        /**
         * Aes constructor.
         * @param App $app
         */
        public function __construct(App &$app)
        {
            $this->app = &$app;
        }

        /**
         * @param $key
         * @param string $iv
         */
        public function setSecret($key, $iv = '')
        {
            $this->secret_key = sha1(sha1($key));
            $this->setIV($iv);
        }

        /**
         * @param $iv
         */
        public function setIV($iv)
        {
            if (!empty($iv) && is_string($iv)) {
                if ((int)strlen($iv) !== (int)$this->ivLen())
                    $this->iv = substr(sha1($iv), -1 * $this->ivLen());
                else
                    $this->iv = $iv;
            } else
                $this->iv = $this->genIV();
        }

        /**
         * @return int
         */
        private function ivLen()
        {
            return openssl_cipher_iv_length($this->cipherMode);
        }

        /**
         * @return string
         */
        private function genIV()
        {
            return openssl_random_pseudo_bytes($this->ivLen());
        }

        /**
         * @param array $data
         * @return string
         */
        public function encryptData(array $data)
        {
            $raw = json_encode($data);
            return $this->encrypt($raw);
        }

        /**
         * @param $content
         * @return string
         */
        public function encrypt($content)
        {
            $iv = !empty($this->iv) ? $this->iv : $this->genIV();
            $cryptTxt = openssl_encrypt($content, $this->cipherMode, $this->secret_key, OPENSSL_RAW_DATA, $iv);
            return base64_encode($cryptTxt);
        }

        /**
         * @param string $str
         * @return mixed|string
         */
        public function decryptData($str = '')
        {
            $raw = $this->decrypt($str);
            $data = json_decode($raw, true);
            return $data ? $data : $raw;
        }

        /**
         * @param $cryptTxt
         * @return string
         */
        public function decrypt($cryptTxt)
        {
            $iv = !empty($this->iv) ? $this->iv : $this->genIV();
            $cryptTxt = base64_decode($cryptTxt);
            $content = openssl_decrypt($cryptTxt, $this->cipherMode, $this->secret_key, OPENSSL_RAW_DATA, $iv);
            return $content;
        }

        /**
         * @param null $cipherMode
         * @return string
         */
        public function cipherMode($cipherMode = null)
        {
            if (!empty($cipherMode) && in_array($cipherMode, $this->supportMode, true))
                $this->cipherMode = $cipherMode;
            return $this->cipherMode;
        }
    }
}