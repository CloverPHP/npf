<?php
declare(strict_types=1);

namespace Npf\Library {

    use Npf\Core\App;

    /**
     * Class Aes
     * @package Library\Crypt
     */
    class Aes
    {
        private array $supportMode = [
            'AES-256-ECB',
            'AES-256-CBC',
            'AES-256-CFB',
            'AES-256-OFB',
            'AES-256-CTR',
        ];
        private string $cipherMode = 'AES-256-CTR';
        private string $secret_key = '';
        private string $iv = '';

        /**
         * Aes constructor.
         * @param App $app
         */
        public function __construct(private App $app)
        {
        }

        /**
         * @param $key
         * @param string $iv
         * @return self
         */
        public function setSecret(string $key, string $iv = ''): self
        {
            $this->secret_key = sha1(sha1($key));
            $this->setIV($iv);
            return $this;
        }

        /**
         * @param string $iv
         * @return self
         */
        public function setIV(string $iv): self
        {
            if (!empty($iv) && is_string($iv)) {
                if ((int)strlen($iv) !== (int)$this->ivLen())
                    $this->iv = substr(sha1($iv), -1 * $this->ivLen());
                else
                    $this->iv = $iv;
            } else
                $this->iv = $this->genIV();
            return $this;
        }

        /**
         * @return int
         */
        private function ivLen(): int
        {
            return openssl_cipher_iv_length($this->cipherMode);
        }

        /**
         * @return string
         */
        private function genIV(): string
        {
            return openssl_random_pseudo_bytes($this->ivLen());
        }

        /**
         * @param array $data
         * @return string
         */
        public function encryptData(array $data): string
        {
            $raw = json_encode($data);
            return $this->encrypt($raw);
        }

        /**
         * @param $content
         * @return string
         */
        public function encrypt($content): string
        {
            $iv = !empty($this->iv) ? $this->iv : $this->genIV();
            $cryptTxt = openssl_encrypt($content, $this->cipherMode, $this->secret_key, OPENSSL_RAW_DATA, $iv);
            return base64_encode($cryptTxt);
        }

        /**
         * @param string $str
         * @return mixed
         */
        public function decryptData(string $str = ''): mixed
        {
            $raw = $this->decrypt($str);
            $data = json_decode($raw, true);
            return $data ? $data : $raw;
        }

        /**
         * @param $cryptTxt
         * @return string
         */
        public function decrypt($cryptTxt): string
        {
            $iv = !empty($this->iv) ? $this->iv : $this->genIV();
            $cryptTxt = base64_decode($cryptTxt);
            return openssl_decrypt($cryptTxt, $this->cipherMode, $this->secret_key, OPENSSL_RAW_DATA, $iv);
        }

        /**
         * @param string|array|null $cipherMode
         * @return array|string
         */
        public function cipherMode(string|array $cipherMode = null): array|string
        {
            if (!empty($cipherMode) && in_array($cipherMode, $this->supportMode, true))
                $this->cipherMode = $cipherMode;
            return $this->cipherMode;
        }
    }
}