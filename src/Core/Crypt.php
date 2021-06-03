<?php

namespace Npf\Core;

use Npf\Exception\InternalError;

/**
 * Class AES
 * @package Library\Crypt
 */
class Crypt
{
    /**
     * @var App
     */
    private $app;
    private $cipher = 'AES-256-CTR';
    private $secret_key = '';
    private $iv = '';

    /**
     * AES constructor.
     * @param App $app
     * @throws InternalError
     */
    public function __construct(App &$app)
    {
        $this->app = &$app;
        $this->setKey($app->config('General')->get('secret'));
    }

    /**
     * @param $key
     * @param string $iv
     */
    public function setKey($key, $iv = '')
    {
        $this->secret_key = sha1(sha1($key));
        $this->setIv($iv);
    }

    /**
     * @param $iv
     */
    public function setIv($iv)
    {
        if (!empty($iv) && is_string($iv)) {
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
    private function ivLen()
    {
        return openssl_cipher_iv_length($this->cipher);
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
     * @param null $iv
     * @return string
     */
    public function encrypt($content, $iv = null)
    {
        if (empty($iv))
            $iv = !empty($this->iv) ? $this->iv : $this->genIV();
        $cryptText = openssl_encrypt($content, $this->cipher, $this->secret_key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($cryptText);
    }

    /**
     * @param string $str
     * @return mixed|string
     */
    public function decryptData($str = '')
    {
        $raw = $this->decrypt($str);
        $data = json_decode($raw, true);
        return $data ?: $raw;
    }

    /**
     * @param $cryptText
     * @param null $iv
     * @return string
     */
    public function decrypt($cryptText, $iv = null)
    {
        if (empty($iv))
            $iv = !empty($this->iv) ? $this->iv : $this->genIV();
        $cryptText = base64_decode($cryptText);
        return openssl_decrypt($cryptText, $this->cipher, $this->secret_key, OPENSSL_RAW_DATA, $iv);
    }
}