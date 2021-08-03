<?php
declare(strict_types=1);

namespace Npf\Core {

    use Npf\Exception\InternalError;

    /**
     * Class AES
     * @package Library\Crypt
     */
    class Crypt
    {
        private string $cipher = 'AES-256-CTR';
        private string $secretKey = '';
        private string $iv = '';

        /**
         * AES constructor.
         * @param App $app
         * @throws InternalError
         */
        public function __construct(App $app)
        {
            $this->setKey($app->config('General')->get('secret'));
        }

        /**
         * @param string $key
         * @param string $iv
         */
        public function setKey(string $key, string $iv = '')
        {
            $this->secretKey = sha1(sha1($key));
            $this->setIv($iv);
        }

        /**
         * @param string $iv
         */
        public function setIv(string $iv)
        {
            if (!empty($iv)) {
                if (strlen($iv) !== $this->ivLen())
                    $this->iv = substr(sha1($iv), -1 * $this->ivLen());
                else
                    $this->iv = $iv;
            } else
                $this->iv = $this->genIV();
        }

        /**
         * @return int
         */
        private function ivLen(): int
        {
            return openssl_cipher_iv_length($this->cipher);
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
         * @param string $content
         * @param string|null $iv
         * @return string
         */
        public function encrypt(string $content, string|null $iv = null): string
        {
            if (empty($iv))
                $iv = !empty($this->iv) ? $this->iv : $this->genIV();
            $cryptText = openssl_encrypt($content, $this->cipher, $this->secretKey, OPENSSL_RAW_DATA, $iv);
            return base64_encode($cryptText);
        }

        /**
         * @param string $str
         * @return mixed
         */
        public function decryptData(string $str = ''): mixed
        {
            $raw = $this->decrypt($str);
            $data = json_decode($raw, true);
            return $data ?: $raw;
        }

        /**
         * @param string $cryptText
         * @param string|null $iv
         * @return string
         */
        public function decrypt(string $cryptText, string|null $iv = null): string
        {
            if (empty($iv))
                $iv = !empty($this->iv) ? $this->iv : $this->genIV();
            $cryptText = base64_decode($cryptText);
            return openssl_decrypt($cryptText, $this->cipher, $this->secretKey, OPENSSL_RAW_DATA, $iv);
        }
    }
}