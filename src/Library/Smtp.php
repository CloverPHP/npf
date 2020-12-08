<?php

namespace Npf\Library;

use finfo;

/**
 * Class Smtp
 * @package Npf\Library
 */
class Smtp
{
    private $socket;
    private $logs = [];
    private $lastResponse = '';
    private $eol = "\n";
    private $error = [];
    private $serverElm = [];
    private $mail = [
        'server' => [],
        'messageId' => '',
        'auth' => [],
        'contact' => [
            'from' => [
                'email' => 'root@localhost.com',
                'name' => 'root',
            ],
            'reply' => [],
            'to' => [],
            'cc' => [],
            'bcc' => [],
        ],
        'attachment' => [
            'inline' => [],
            'attachment' => [],
        ],
        'subject' => '',
        'header' => [],
        'content' => [
            'text' => '',
            'html' => '',
            'body' => '',
        ],
    ];

    /**
     * Smtp constructor.
     * @param string $server
     * @param int $port
     * @param string $secure
     * @param int $timeout
     */
    public function __construct($server = '', $port = 25, $secure = '', $timeout = 30)
    {
        //Note the server info.
        $port = (int)$port;
        $this->mail['server'] = [
            'host' => $server,
            'secure' => $secure,
            'server' => $this->getServerName(),
            'port' => $port > 0 && $port < 65535 ? $port : 25,
            'timeout' => $timeout
        ];
    }

    /**
     * Validate Host name
     * @param $host
     * @return bool
     */
    private function isValidHost($host)
    {
        if (empty($host) || !is_string($host) || strlen($host) > 256 || !preg_match('/^([a-zA-Z\d.-]*|\[[a-fA-F\d:]+])$/', $host))
            return false;
        if (strlen($host) > 2 && substr($host, 0, 1) === '[' && substr($host, -1, 1) === ']')
            return filter_var(substr($host, 1, -1), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;
        if (is_numeric(str_replace('.', '', $host)))
            return filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
        if (filter_var('http://' . $host, FILTER_VALIDATE_URL) !== false)
            return true;
        return false;
    }

    /**
     * @param $content
     * @return mixed
     */
    private function safeText($content)
    {
        return str_replace(["\r\n", "\r", "\n"], "", $content);
    }

    /**
     * Get the server hostname.
     * Returns 'localhost.localdomain' if unknown.
     * @return string
     */
    private function getServerName()
    {
        $result = '';
        if (isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER))
            $result = $_SERVER['SERVER_NAME'];
        elseif (function_exists('gethostname') && gethostname() !== false)
            $result = gethostname();
        elseif (php_uname('n') !== false)
            $result = php_uname('n');
        if (!$this->isValidHost($result))
            $result = 'localhost.localdomain';
        return $result;
    }

    /**
     * Check is email or not
     * @param $email
     * @return bool
     */
    public function isEmail($email)
    {
        return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Set error messages and codes.
     *
     * @param string $message
     * @param string $detail
     * @param string $code
     * @param string $extend
     */
    private function setError($message, $detail = '', $code = '', $extend = '')
    {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'code' => $code,
            'extend' => $extend,
        ];
    }

    /**
     * Disconnect from server if connected
     * @return Smtp
     */
    private function disconnect()
    {
        if (is_resource($this->socket)) {
            $this->logs[] = "Disconnected";
            $this->setError('');
            usleep(300000);
            @fclose($this->socket);
            if (is_resource($this->socket))
                fclose($this->socket);
            $this->socket = null;
        }
        return $this;
    }

    private function getHost()
    {
        $prefix = '';
        if (!empty($this->mail['server']['secure']))
            $prefix = "{$this->mail['server']['secure']}://";
        $host = "{$prefix}{$this->mail['server']['host']}";
        return [
            'host' => $host,
            'port' => $this->mail['server']['port'],
        ];
    }

    /**
     * @return bool
     */
    private function connect()
    {
        $errorNo = 0;
        $errStr = '';
        if (is_resource($this->socket))
            $this->disconnect();
        $this->setError('');
        $server = $this->getHost();
        if (function_exists('stream_socket_client')) {
            $socket_context = stream_context_create();
            set_error_handler([$this, 'errorHandler']);
            $this->socket = stream_socket_client(
                "{$server['host']}:{$server['port']}",
                $errorNo,
                $errStr,
                $this->mail['server']['timeout'],
                STREAM_CLIENT_CONNECT,
                $socket_context
            );
            restore_error_handler();
        } else {
            set_error_handler([$this, 'errorHandler']);
            $this->socket = fsockopen(
                $server['host'],
                $server['port'],
                $errorNo,
                $errStr,
                $this->mail['server']['timeout']
            );
            restore_error_handler();
        }

        if (!is_resource($this->socket)) {
            $this->socket = null;
            $this->setError(
                'Failed to connect to smtp server',
                '',
                (string)$errorNo,
                $errStr
            );
            return false;
        }

        //Server didn't response probably
        if (!($content = $this->execute('CONNECT', [220, 221]))) {
            $this->disconnect();
            return false;
        }

        if (strpos(PHP_OS, 'WIN') !== 0) {
            $max = (int)ini_get('max_execution_time');
            if (0 !== $max && $this->mail['server']['timeout'] > $max && strpos(ini_get('disable_functions'), 'set_time_limit') === false)
                @set_time_limit($this->mail['server']['timeout']);
            stream_set_timeout($this->socket, $this->mail['server']['timeout'], 0);
        }
        return true;
    }

    /**
     * Reports an error number and string.
     *
     * @param int $errNo The error number returned by PHP
     * @param string $errStr The error message returned by PHP
     */
    private function errorHandler($errNo, $errStr)
    {
        $this->setError(
            'Connection failed.',
            $errStr,
            (string)$errNo
        );
    }

    /**
     * @return string
     */
    private function readLine()
    {
        if (!is_resource($this->socket))
            return '';

        $data = '';
        stream_set_timeout($this->socket, 300);
        $endTime = time() + 300;
        $selR = [$this->socket];
        $selW = $selE = null;
        while (is_resource($this->socket) && !feof($this->socket)) {
            set_error_handler([$this, 'errorHandler']);
            $n = stream_select($selR, $selW, $selE, 1);
            restore_error_handler();
            if ($n === false) {
                $message = $this->error['detail'];
                if (stripos($message, 'interrupted system call') !== false) {
                    $this->setError('');
                    continue;
                }
                break;
            }
            if (!$n)
                break;
            $str = @fgets($this->socket, 512);
            $data .= $str;
            $info = stream_get_meta_data($this->socket);
            if (!isset($str[3]) || $str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n" ||
                $info['timed_out'] || $endTime && time() > $endTime)
                break;
        }
        return trim($data);
    }

    /**
     * Add Log
     * @param $name
     * @param $expect
     * @return bool|array
     */
    private function execute($name, $expect)
    {
        if (is_resource($this->socket)) {
            $this->lastResponse = $this->readLine();
            $code = 0;
            $data = '';
            if (!empty($this->lastResponse)) {
                $code = (int)substr($this->lastResponse, 0, 3);
                $data = substr($this->lastResponse, 4);
            }
            $content = [
                'call' => $name,
                'code' => $code,
                'data' => $data,
            ];
            $this->logs[] = $content;
            if (!in_array($code, (array)$expect, true))
                return false;
            return $content;
        }
        return false;
    }

    /**
     * Send Content to server
     * @param $content
     * @param bool $log
     * @param $expect
     * @return bool
     */
    private function send($content, $expect, $log = true)
    {
        if ($this->connected()) {
            @fwrite($this->socket, "{$content}{$this->eol}");
            if ($expect !== false)
                return $this->execute($log === true ? $content : $log, $expect);
            return true;
        } else {
            $this->logs[] = 'Socket Disconnected';
            return false;
        }
    }

    /**
     * Check connection state.
     *
     * @return bool True if connected
     */
    private function connected()
    {
        if (is_resource($this->socket)) {
            $sock_status = stream_get_meta_data($this->socket);
            if ($sock_status['eof']) {
                $this->disconnect();
                return false;
            }
            return true; // everything looks good
        }
        return false;
    }

    private function sendHello($cmd, $host)
    {
        $response = $this->send("{$cmd} {$host}", 250);
        if ($response) {
            $this->serverElm = [];
            $lines = explode("\n", $response['data']);

            foreach ($lines as $index => $line) {
                //First 4 chars contain response code followed by - or space
                $line = trim(substr($line, 4));
                if (empty($line))
                    continue;
                $fields = explode(' ', $line);
                if (!empty($fields)) {
                    if (empty($index)) {
                        $name = $cmd;
                        $fields = $fields[0];
                    } else {
                        $name = array_shift($fields);
                        switch ($name) {
                            case 'SIZE':
                                $fields = ($fields ? $fields[0] : 0);
                                break;
                            case 'AUTH':
                                if (!is_array($fields))
                                    $fields = [];
                                break;
                            default:
                                $fields = true;
                        }
                    }
                    $this->serverElm[$name] = $fields;
                }
            }
        }
        return (boolean)$response;
    }

    /**
     * Say hello to smtp server
     */
    private function welcomeServer()
    {
        return $this->sendHello('EHLO', $this->mail['server']['server']) or $this->sendHello('HELO', $this->mail['server']['server']);
    }

    /**
     * Start TLS (encrypted) session if needed.
     * @return bool
     */
    public function startTLS()
    {
        if (isset($this->serverElm['STARTTLS'])) {
            if (!$this->send('STARTTLS', 220))
                return false;

            $method = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $method |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
                $method |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
            }
            set_error_handler([$this, 'errorHandler']);
            $result = stream_socket_enable_crypto(
                $this->socket,
                true,
                $method
            );
            restore_error_handler();
            return (bool)$result;
        } else
            return -1;
    }

    /**
     * Auth User
     */
    private function authUser()
    {
        if (!$this->serverElm)
            return false;

        $authType = 'LOGIN';
        if (isset($this->serverElm['EHLO'])) {
            if (!isset($this->serverElm['AUTH']))
                return false;

            foreach (['CRAM-MD5', 'LOGIN', 'PLAIN'] as $method) {
                if (in_array($method, $this->serverElm['AUTH'], true)) {
                    $authType = $method;
                    break;
                }
            }
            if (empty($authType))
                return false;
        }
        switch ($authType) {
            case 'PLAIN':
                // Start authentication
                if (!$this->send('AUTH PLAIN', 334, 'AUTH'))
                    return false;
                if (!$this->send(base64_encode("\0{$this->mail['auth']['user']}\0{$this->mail['auth']['password']}"), 235, 'USER_PASS'))
                    return false;
                break;
            case 'LOGIN':
                // Start authentication
                if (!$this->send('AUTH LOGIN', 334, 'AUTH'))
                    return false;
                if (!$this->send(base64_encode($this->mail['auth']['user']), 334, 'USER'))
                    return false;
                if (!$this->send(base64_encode($this->mail['auth']['password']), 235, 'PASS'))
                    return false;
                break;
            case 'CRAM-MD5':
                // Start authentication
                if (!($content = $this->send('AUTH CRAM-MD5', 334)))
                    return false;
                $challenge = base64_decode(substr($content['data'], 4));
                $response = "{$this->mail['auth']['user']} " . hash_hmac('md5', $challenge, $this->mail['auth']['password']);
                if (!$this->send(base64_encode($response), 235, 'MD5'))
                    return false;
                break;
            default:
                return false;
        }
        return true;
    }

    /**
     * @param $data
     * @return bool
     */
    private function sendContent($data)
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $data));
        $field = substr($lines[0], 0, strpos($lines[0], ':'));
        $in_headers = false;
        if (!empty($field) && strpos($field, ' ') === false)
            $in_headers = true;

        foreach ($lines as $line) {
            $lines_out = [];
            if ($in_headers && $line === '')
                $in_headers = false;
            while (isset($line[998])) {
                $pos = strrpos(substr($line, 0, 998), ' ');
                if (!$pos) {
                    $pos = 998 - 1;
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos);
                } else {
                    $lines_out[] = substr($line, 0, $pos);
                    $line = substr($line, $pos + 1);
                }
                if ($in_headers)
                    $line = "\t" . $line;
            }
            $lines_out[] = $line;
            foreach ($lines_out as $line_out)
                $this->send($line_out, false);
        }
        return $this->send("\r\n.\r\n", 250, 'DATA END');
    }

    private function prepareMail()
    {
        //Setup Mail Boundary
        $mixBoundary = @sha1(md5($this->mail['subject'] . "-MIXED-" . microtime(true)));
        $relatedBoundary = @sha1(md5($this->mail['subject'] . "-RELATED-" . microtime(true)));
        $alternativeBoundary = @sha1(md5($this->mail['subject'] . "-ALTERNATIVE-" . microtime(true)));

        //Add necessary email header
        $this->addHeader("Date", gmdate("r"));

        //Add email contact (form, reply, to, cc, bcc
        $this->addHeader("From", !empty($this->mail['contact']['from']['name']) ? "{$this->mail['contact']['from']['name']} <{$this->mail['contact']['from']['email']}>" : "<{$this->mail['contact']['from']['email']}>");
        if (!empty($this->mail['contact']['reply'])) {
            $email = !empty($this->mail['contact']['reply']['name']) ? "{$this->mail['contact']['reply']['name']} <{$this->mail['contact']['reply']['email']}>" : "<{$this->mail['contact']['reply']['email']}>";
            $this->addHeader("Reply-to", $email);
            $this->addHeader("Return-Path", $email);
        }
        if (count($this->mail['contact']['to']) > 0) {
            $this->addHeader("To", $this->getEmailList($this->mail['contact']['to']));
        }
        if (count($this->mail['contact']['cc']) > 0)
            $this->addHeader("Cc", $this->getEmailList($this->mail['contact']['cc']));
        if (count($this->mail['contact']['bcc']) > 0)
            $this->addHeader("Bcc", $this->getEmailList($this->mail['contact']['bcc']));

        //Add Email Subject & Message ID
        $this->addHeader("Subject", "=?UTF-8?B?" . base64_encode($this->mail['subject']) . "?=");
        $this->addHeader("Message-ID", '<' . sha1($this->mail['content']['text'] . $this->mail['content']['html'] . (string)microtime(true)) . '.' . (string)microtime(true) . '@' . explode('@', $this->mail['contact']['from']['email'], 2)[1] . '>');
        $this->addHeader("MIME-Version", "1.0");
        $this->mail['content']['body'] .=

        $this->mail['content']['body'] = '';
        foreach ($this->mail['header'] as $headerName => $headerContent)
            $this->mail['content']['body'] .= "{$headerName}: {$headerContent}{$this->eol}";

        //Multipart/Mixed Boundary
        if (count($this->mail['attachment']['attachment']) > 0) {
            $this->mail['content']['body'] .= "Content-Type: multipart/mixed; boundary=\"{$mixBoundary}\"{$this->eol}";
            $this->mail['content']['body'] .= "{$this->eol}--{$mixBoundary}{$this->eol}";
        }

        //Multipart/Related Boundary
        if (count($this->mail['attachment']['inline']) > 0) {
            $this->mail['content']['body'] .= "Content-Type: multipart/related; boundary=\"{$relatedBoundary}\"{$this->eol}";
            $this->mail['content']['body'] .= "{$this->eol}--{$relatedBoundary}{$this->eol}";
        }

        //Multipart/Alternative Boundary
        if (!empty($this->mail['content']['text']) && !empty($this->mail['content']['html'])) {
            $this->mail['content']['body'] .= "Content-Type: multipart/alternative; boundary=\"{$alternativeBoundary}\"{$this->eol}";
            $this->mail['content']['body'] .= "{$this->eol}--{$alternativeBoundary}{$this->eol}";
        }

        //Add Text Content
        if (!empty($this->mail['content']['text'])) {
            $this->mail['content']['body'] .= "Content-Type: text/plain; charset=UTF-8{$this->eol}";
            $this->mail['content']['body'] .= "Content-Transfer-Encoding: base64{$this->eol}";
            $this->mail['content']['body'] .= chunk_split(base64_encode($this->mail['content']['text']), 76, $this->eol);
        }

        //Alternative Boundary Separate Line
        $this->mail['content']['body'] .= "{$this->eol}--{$alternativeBoundary}{$this->eol}";

        //Add Html Content
        if (!empty($this->mail['content']['html'])) {
            $this->mail['content']['body'] .= "Content-Type: text/html; charset=UTF-8{$this->eol}";
            $this->mail['content']['body'] .= "Content-Transfer-Encoding: base64{$this->eol}";
            $this->mail['content']['body'] .= chunk_split(base64_encode($this->mail['content']['html']), 76, $this->eol);
        }

        //End of Alternative Boundary
        if (!empty($this->mail['content']['text']) && !empty($this->mail['content']['html']))
            $this->mail['content']['body'] .= "{$this->eol}--{$alternativeBoundary}--{$this->eol}";

        //Add inline attachment
        if (count($this->mail['attachment']['inline']) > 0) {
            foreach ($this->mail['attachment']['inline'] as $attachment) {
                $this->mail['content']['body'] .= "{$this->eol}--{$relatedBoundary}{$this->eol}";
                $this->mail['content']['body'] .= "Content-Type: {$attachment['mime']};" . (!empty($attachment['name']) ? " name=\"{$attachment['name']}\"" : "") . "{$this->eol}";
                $this->mail['content']['body'] .= "Content-Disposition: inline;" . (!empty($attachment['name']) ? " filename=\"{$attachment['name']}\"" : "") . "{$this->eol}";
                $this->mail['content']['body'] .= "Content-Transfer-Encoding: base64{$this->eol}";
                if (!empty($attachment['id']))
                    $this->mail['content']['body'] .= "Content-ID: <{$attachment['id']}>{$this->eol}";
                if (!empty($attachment['desc']))
                    $this->mail['content']['body'] .= "Content-Description: {$this->safeText($attachment['desc'])}{$this->eol}";
                $this->mail['content']['body'] .= "{$this->eol}{$attachment['content']}{$this->eol}";
            }
            $this->mail['content']['body'] .= "{$this->eol}--{$relatedBoundary}--{$this->eol}";
        }
        //Add attachment
        if (count($this->mail['attachment']['attachment']) > 0) {
            foreach ($this->mail['attachment']['attachment'] as $attachment) {
                $this->mail['content']['body'] .= "{$this->eol}--{$mixBoundary}{$this->eol}";
                $this->mail['content']['body'] .= "Content-Type: {$attachment['mime']};" . (!empty($attachment['name']) ? " name=\"{$attachment['name']}\"" : "") . "{$this->eol}";
                $this->mail['content']['body'] .= "Content-Disposition: attachment;" . (!empty($attachment['name']) ? " filename=\"{$attachment['name']}\"" : "") . "{$this->eol}";
                $this->mail['content']['body'] .= "Content-Transfer-Encoding: base64{$this->eol}";
                if (!empty($attachment['id']))
                    $this->mail['content']['body'] .= "Content-ID: <{$attachment['id']}>{$this->eol}";
                if (!empty($attachment['desc']))
                    $this->mail['content']['body'] .= "Content-Description: {$this->safeText($attachment['desc'])}{$this->eol}";
                $this->mail['content']['body'] .= "{$this->eol}{$attachment['content']}{$this->eol}";
            }
            $this->mail['content']['body'] .= "{$this->eol}--{$mixBoundary}--{$this->eol}";
        }
    }

    /**
     * Send Mail
     * @return Smtp
     */
    public function sendMail()
    {
        //Check got any recipient
        if (empty($this->mail['contact']['to']) &&
            empty($this->mail['contact']['cc']) &&
            empty($this->mail['contact']['bcc']))
            return $this;

        /**
         * Prepare mail content
         */
        $this->prepareMail();

        /**
         * Connect and send mail to server
         */
        if (!$this->connect()) {
            $this->disconnect();
            return $this;
        }

        //Say hello to Server
        if (!$this->welcomeServer()) {
            $this->disconnect();
            return $this;
        } else {
            if ($this->startTLS() === true)
                if (!$this->welcomeServer()) { //Say hello again to Server
                    $this->disconnect();
                    return $this;
                }
        }

        //User Auth if need
        if (!$this->authUser()) {
            $this->disconnect();
            return $this;
        }

        //Tell Server From Mail
        if (!$this->send("MAIL FROM:<{$this->mail['contact']['from']['email']}>", 250)) {
            $this->disconnect();
            return $this;
        }

        //Tell Server Who shall receive
        $recipient = [];
        if (!empty($this->mail['contact']['to']))
            $recipient = array_merge($recipient, array_keys($this->mail['contact']['to']));
        if (!empty($this->mail['contact']['cc']))
            $recipient = array_merge($recipient, array_keys($this->mail['contact']['cc']));
        if (!empty($this->mail['contact']['bcc']))
            $recipient = array_merge($recipient, array_keys($this->mail['contact']['bcc']));
        $recipient = array_unique($recipient);
        foreach ($recipient as $email)
            if (!$this->send("RCPT TO:<{$email}>", [250, 251])) {
                $this->disconnect();
                return $this;
            }

        //Tell Server Next message is the email contain.
        if (!$this->send("DATA", 354)) {
            $this->disconnect();
            return $this;
        }

        //Send Email Contain to Server
        if (!$this->sendContent($this->mail['content']['body'])) {
            $this->disconnect();
            return $this;
        }

        //Tell Server we will quit
        $this->send('QUIT', 221);

        //Disconnect after send mail.
        $this->disconnect();
        return $this;
    }

    /**
     * @param $user
     * @param $password
     * @return Smtp
     */
    public function login($user, $password)
    {
        $this->mail['auth'] = [
            'user' => $user,
            'password' => $password
        ];
        return $this;
    }

    /**
     * @param $email
     * @param null $name
     * @return Smtp
     */
    public function setFrom($email, $name = null)
    {
        if ($this->isEmail($email)) {
            $this->mail['contact']['from'] = [
                'email' => $email,
            ];
            if (!empty($name) && is_string($name))
                $this->mail['from']['name'] = $name;
        }
        return $this;
    }

    /**
     * @param $email
     * @param null $name
     * @return Smtp
     */
    public function setReply($email, $name = null)
    {
        if ($this->isEmail($email)) {
            $this->mail['contact']['reply'] = [
                'email' => $email
            ];
            if (!empty($name) && is_string($name))
                $this->mail['contact']['reply']['name'] = $name;
        }
        return $this;
    }

    /**
     * @param $email
     * @param null $name
     * @return Smtp
     */
    public function addTo($email, $name = null)
    {
        if ($this->isEmail($email))
            $this->mail['contact']['to'][$email] = !empty($name) && is_string($name) ? $name : null;
        return $this;
    }

    /**
     * @param $email
     * @param null $name
     * @return Smtp
     */
    public function addCc($email, $name = null)
    {
        if ($this->isEmail($email))
            $this->mail['contact']['cc'][$email] = !empty($name) && is_string($name) ? $name : null;
        return $this;
    }

    /**
     * @param $email
     * @param null $name
     * @return Smtp
     */
    public function addBcc($email, $name = null)
    {
        if ($this->isEmail($email))
            $this->mail['contact']['bcc'][$email] = !empty($name) && is_string($name) ? $name : null;
        return $this;
    }

    /**
     * @param $subject
     * @return Smtp
     */
    public function setSubject($subject)
    {
        $this->mail['subject'] = $subject;
        return $this;
    }

    /**
     * @param $content
     * @return Smtp
     */
    public function setContentText($content)
    {
        $this->mail['content']['text'] = $content;
        return $this;
    }

    /**
     * @param $content
     * @return Smtp
     */
    public function setContentHtml($content)
    {
        $this->mail['content']['html'] = $content;
        return $this;
    }

    /**
     * @param $fileName
     * @param $id
     * @param string $displayName
     * @param string $desc
     * @return Smtp
     */
    public function addInlineImage($fileName, $displayName = '', $id = '', $desc = '')
    {
        $attachment = [];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        if (file_exists($fileName)) {
            $displayName = (!empty($displayName) ? $displayName : $fileName);
            $attachment = [
                'id' => $id,
                'desc' => $desc,
                'name' => basename($displayName),
                'mime' => $fileInfo->file($fileName, FILEINFO_MIME_TYPE),
                'content' => chunk_split(base64_encode(file_get_contents($fileName)), 76, $this->
                eol)
            ];
        } elseif (!empty($fileName)) {
            $attachment = [
                'id' => $id,
                'desc' => $desc,
                'mime' => $fileInfo->buffer($fileName, FILEINFO_MIME_TYPE),
                'content' => chunk_split(base64_encode($fileName), 76, $this->eol)
            ];
            if (!empty($displayName))
                $attachment['name'] = basename($displayName);
        }
        if (!empty($attachment))
            $this->mail['attachment']['inline'][] = $attachment;
        return $this;
    }

    /**
     * @param $fileName
     * @param string $displayName
     * @param string $id
     * @param string $desc
     * @return Smtp
     */
    public function addAttachment($fileName, $displayName = '', $id = '', $desc = '')
    {
        $attachment = [];
        $fileInfo = new finfo(FILEINFO_MIME_TYPE);
        if (file_exists($fileName)) {
            $displayName = (!empty($displayName) ? $displayName : $fileName);
            $attachment = [
                'id' => $id,
                'desc' => $desc,
                'name' => basename($displayName),
                'mime' => $fileInfo->file($fileName, FILEINFO_MIME_TYPE),
                'content' => chunk_split(base64_encode(file_get_contents($fileName)), 76, $this->
                eol)
            ];
        } elseif (!empty($fileName)) {
            $attachment = [
                'id' => $id,
                'desc' => $desc,
                'name' => basename($displayName),
                'mime' => $fileInfo->buffer($fileName, FILEINFO_MIME_TYPE),
                'content' => chunk_split(base64_encode($fileName), 76, $this->eol)
            ];
            if (!empty($displayName))
                $attachment['name'] = basename($displayName);
        }
        if (!empty($attachment))
            $this->mail['attachment']['attachment'][] = $attachment;
        return $this;
    }

    /**
     * @param $contact
     * @return Smtp
     */
    public function getEmailList($contact)
    {
        $result = [];
        foreach ($contact as $email => $name)
            if (!empty($name) && is_string($name))
                $result[] = "{$name} <{$email}>";
            else
                $result[] = $email;
        return implode(", ", $result);
    }

    /**
     * @param $name
     * @param $content
     * @return Smtp
     */
    public function addHeader($name, $content)
    {
        $this->mail['header'][$name] = $content;
        return $this;
    }

    /**
     * @return array
     */
    public function getContent()
    {
        return $this->mail['content'];
    }

    /**
     * @return array
     */
    public function getLogs()
    {
        return $this->logs;
    }
}