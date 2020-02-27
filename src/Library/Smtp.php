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
    private $connected = [];
    private $logs = [];
    private $defaultFrom = 'admin@localhost.com';
    private $from = '';
    private $reply = '';
    private $to = [];
    private $bcc = [];
    private $cc = [];
    private $header = [];
    private $subject = '';
    private $content = [];
    private $messageId = "";
    private $attachment = [];
    private $priority = 3;
    private $eol = "\n";
    private $error = '';

    /**
     * Smtp constructor.
     * @param string $server
     * @param int $port
     * @param int $timeout
     */
    function __construct($server = '', $port = 25, $timeout = 15)
    {
        global $_SERVER;
        $this->messageId = sha1('' . microtime(true));
        if (!empty($server) && !empty($port))
            $this->connect($server, $port, $timeout);
    }

    /**
     * @param string $server
     * @param int $port
     * @param int $timeout
     * @return bool
     */
    public function connect($server = 'localhost', $port = 25, $timeout = 5)
    {
        if ($this->connected)
            $this->__destruct();
        $ErrNo = 0;
        $this->socket = @fsockopen($server, $port, $ErrNo, $this->error, $timeout);
        stream_set_timeout($this->socket, 0, 300000);
        $this->execute("CONNECT");
        if (empty($this->socket)) {
            $this->connected = (boolean)false;
            return false;
        } else {
            $this->connected = (boolean)true;

            //Say hello to server for confirm connection successful.
            @fwrite($this->socket, "EHLO {$server}" . $this->eol);
            $this->execute("EHLO");
            return true;
        }
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * @return bool
     */
    public function disconnect()
    {
        if ($this->connected) {
            @fwrite($this->socket, "QUIT" . $this->eol);
            $this->execute("QUIT");
            usleep(300000);
            @fclose($this->socket);
            $this->connected = (boolean)false;
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $call
     */
    private function execute($call)
    {
        if ($this->connected) {
            $logs = [
                'call' => $call,
                'logs' => []
            ];
            $done = false;
            while (!$done) {
                $response = fgets($this->socket, 4095);
                flush();
                socket_get_status($this->socket);
                if (strpos($response, "\0") || $response === false)
                    $done = true;
                $response = trim($response);
                $code = 0;
                $data = '';
                if (!empty($response)) {
                    $code = (int)substr($response, 0, 3);
                    $data = substr($response, 4);
                }
                if ($code > 0) {
                    $logs['logs'][] = [
                        'code' => $code,
                        'msg' => $data
                    ];
                }
            }
            $this->logs[] = $logs;
        }
    }

    /**
     * @param $Wait
     * @return bool
     */
    public function waitResponse($Wait)
    {
        if ($this->connected) {
            return stream_set_blocking($this->socket, ($Wait === (boolean)true) ? 1 : 0);
        } else
            return false;
    }

    /**
     * @param $user
     * @param $password
     */
    public function login($user, $password)
    {
        if ($this->connected === true) {
            @fwrite($this->socket, "AUTH LOGIN" . $this->eol);
            $this->execute("AUTH LOGIN");

            //send the username
            @fwrite($this->socket, base64_encode($user) . $this->eol);
            $this->execute("AUTH USER");

            //send the password
            @fwrite($this->socket, base64_encode($password) . $this->eol);
            $this->execute("AUTH PASS");
        }
    }

    /**
     * @param string $messageId
     */
    public function setMessageID($messageId = '')
    {
        $this->messageId = sha1("" . (!empty($messageId) ? $messageId : $this->
            messageId));
    }

    /**
     * @param $email
     * @param null $name
     */
    public function setReply($email, $name = null)
    {
        if ($this->connected === true) {
            if ($this->emailCheck($email) && empty($this->reply))
                $this->reply = (!empty($name) ? "\"{$name}\" <{$email}>" :
                    $email);
        }
    }

    /**
     * @param $email
     * @return bool
     */
    public function emailCheck($email)
    {
        if (@preg_match("/^([a-zA-Z0-9])+([a-zA-Z0-9._-])*@([a-zA-Z0-9_-])+([a-zA-Z0-9._-]+)+$/",
            $email))
            return true;
        else
            return false;
    }

    /**
     * @param $email
     * @param null $Name
     * @return bool
     */
    public function addTo($email, $Name = null)
    {
        if ($this->connected == true && $this->emailCheck($email)) {
            @fwrite($this->socket, "RCPT TO:<{$email}>" . $this->eol);
            $this->execute("RCPT TO");
            $this->to[] = (!empty($Name) ? "\"{$Name}\" <{$email}>" :
                $email);
            return true;
        } else
            return false;
    }

    /**
     * @param $email
     * @param null $name
     * @return bool
     */
    public function addCc($email, $name = null)
    {
        if ($this->connected === true && $this->emailCheck($email)) {
            @fwrite($this->socket, "RCPT TO:<{$email}>" . $this->eol);
            $this->execute("RCPT TO");
            $this->cc[] = (!empty($name) ? "\"{$name}\" <{$email}>" :
                $email);
            return true;
        } else
            return false;
    }

    /**
     * @param $email
     * @param null $name
     * @return bool
     */
    public function addBcc($email, $name = null)
    {
        if ($this->connected === true && $this->emailCheck($email)) {
            @fwrite($this->socket, "RCPT TO:<{$email}>" . $this->eol);
            $this->execute("RCPT TO");
            $this->bcc[] = (!empty($name) ? "\"{$name}\" <{$email}>" :
                $email);
            return true;
        } else
            return false;
    }

    /**
     * @param $subject
     */
    public function setSubject($subject)
    {
        if ($this->connected === (boolean)true)
            $this->subject = $subject;
    }

    /**
     * @param $content
     */
    public function setBodyText($content)
    {
        if ($this->connected === (boolean)true)
            $this->content['text'] = $content;
    }

    /**
     * @param $content
     */
    public function setBodyHtml($content)
    {
        if ($this->connected === (boolean)true)
            $this->content['html'] = $content;
    }

    /**
     * @param $fileName
     * @param string $displayName
     */
    public function addAttachment($fileName, $displayName = '')
    {
        if ($this->connected === (boolean)true) {
            $fileInfo = new finfo(FILEINFO_MIME_TYPE);
            if (file_exists($fileName)) {
                $displayName = (!empty($displayName) ? $displayName : $fileName);
                $this->attachment[] = [
                    'name' => basename($displayName),
                    'mime' => $fileInfo->file($fileName, FILEINFO_MIME_TYPE),
                    'content' => chunk_split(base64_encode(file_get_contents($fileName)), 76, $this->
                    eol)];
            } elseif (!empty($fileName) && !empty($displayName)) {
                $this->attachment[] = [
                    'name' => basename($displayName),
                    'mime' => $fileInfo->buffer($fileName, FILEINFO_MIME_TYPE),
                    'content' => chunk_split(base64_encode($fileName), 76, $this->eol)];
            } else
                $this->logs[] = "file no found : {$fileName}";
        } else {
            $this->logs[] = "SMTP no connected";
        }
    }

    /**
     * @return bool
     */
    public function sendMail()
    {
        if ($this->connected === (boolean)true) {

            //Check got any receiver
            if (count($this->to) === 0 && count($this->cc) === 0 && count($this->
                bcc) === 0)
                return false;

            //Check got any sender
            if (empty($this->from))
                $this->setFrom($this->defaultFrom, 'Root');

            //Tell Server Next message is the email contain.
            @fwrite($this->socket, "DATA" . $this->eol);
            $this->execute("DATA");

            //Setup Mail Boundary
            $Mix_Boundary = @sha1(md5($this->subject . "-MIXED-" . microtime(true)));
            $Text_Boundary = @sha1(md5($this->subject . "-TEXT-" . microtime(true)));

            //Setup Intial Email Header
            $this->addHeader("MIME-Version", "1.0");
            $this->addHeader("From", $this->from);
            if (!empty($this->reply)) {
                $this->addHeader("Reply-to", $this->reply);
                $this->addHeader("Return-Path", $this->reply);
            }
            if (count($this->to) > 0)
                $this->addHeader("To", implode(", ", $this->to));
            if (count($this->cc) > 0)
                $this->addHeader("Cc", implode(", ", $this->cc));
            if (count($this->bcc) > 0)
                $this->addHeader("Bcc", implode(", ", $this->bcc));
            $this->addHeader("Date", date("r"));
            $this->addHeader("Subject", "=?UTF-8?B?" . base64_encode($this->subject) .
                "?=");
            if ($this->priority != 3) {
                $this->addHeader("X-Priority", $this->priority);
                if ($this->priority >= 4) {
                    $this->addHeader("Priority", "Low");
                    $this->addHeader("Importance", "low");
                } elseif ($this->priority >= 3) {
                    $this->addHeader("Priority", "Medium");
                    $this->addHeader("Importance", "normal");
                } else {
                    $this->addHeader("Priority", "Urgent");
                    $this->addHeader("Importance", "high");
                }
            }

            $this->addHeader("Message-ID", $this->messageId);

            //Ensure text and html body both have content
            if (empty($this->content['text']) && !empty($this->content['html'])) {
                $this->content['text'] = strip_tags($this->content['html']);
            } elseif (!empty($this->content['text']) && empty($this->content['html'])) {
                $this->content['html'] = "<pre>{$this->content['text']}</pre>";
            }

            //Start Body Message
            if (count($this->attachment) > 0) {
                $this->addHeader("Content-Type", "multipart/mixed; boundary=\"{$Mix_Boundary}\"");
                $this->content['body'] .= "--{$Mix_Boundary}{$this->eol}";
                $this->content['body'] .= "Content-Type: multipart/alternative; boundary=\"{$Text_Boundary}\"{$this->eol}{$this->eol}";
            } else {
                $this->addHeader("Content-Type", "multipart/alternative; boundary=\"{$Text_Boundary}\"");
            }

            //Start the body content
            if (!empty($this->content['text'])) {
                $this->content['body'] .= "--{$Text_Boundary}{$this->eol}Content-Type: text/plain; charset=UTF-8;{$this->eol}";
                $this->content['body'] .= "Content-Transfer-Encoding: quoted-printable;{$this->eol}{$this->eol}";
                $this->content['body'] .= $this->qpEncode($this->content['text'], true) .
                    $this->eol;
                $this->content['body'] .= "Content-Transfer-Encoding: base64;{$this->eol}{$this->eol}";
                $this->content['body'] .= chunk_split(base64_encode($this->content['text']), 76, $this->eol) . $this->eol;
            }
            if (!empty($this->content['html'])) {
                $this->content['body'] .= "--{$Text_Boundary}{$this->eol}Content-Type: text/html; charset=UTF-8;{$this->eol}";
                $this->content['body'] .= "Content-Transfer-Encoding: quoted-printable;{$this->eol}{$this->eol}";
                $this->content['body'] .= $this->qpEncode($this->content['html'], true) .
                    $this->eol;
                $this->content['body'] .= "Content-Transfer-Encoding: base64;{$this->eol}{$this->eol}";
                $this->content['body'] .= chunk_split(base64_encode($this->content['html']), 76, $this->eol) . $this->eol;
            }

            $this->content['body'] .= "--{$Text_Boundary}--{$this->eol}";

            //Start the body attachment
            if (count($this->attachment) > 0) {
                $Count = (int)0;
                foreach ($this->attachment as $attachment) {
                    $Count++;
                    $this->content['body'] .= "--{$Mix_Boundary}{$this->eol}";
                    $this->content['body'] .= "Content-Type: {$attachment['mime']}; name=\"{$attachment['name']}\"{$this->eol}";
                    $this->content['body'] .= "Content-Disposition: attachment; filename=\"{$attachment['name']}\"{$this->eol}";
                    $this->content['body'] .= "Content-Transfer-Encoding: base64{$this->eol}{$this->eol}";
                    $this->content['body'] .= "{$attachment['content']}{$this->eol}{$this->eol}";
                }
            }
            if (count($this->attachment) > 0)
                $this->content['body'] .= "--{$Mix_Boundary}--{$this->eol}";

            //Prepare Email Content
            $this->content['data'] = '';

            //Prepare Mail Header
            foreach ($this->header as $header)
                $this->content['data'] .= "{$header}";
            $this->content['data'] .= "{$this->eol}{$this->content['body']}.{$this->eol}";

            //Send Email Contain to Server
            stream_set_timeout($this->socket, 5);
            @fwrite($this->socket, $this->content['data']);
            stream_set_timeout($this->socket, 0, 300000);
            $this->execute("SEND");
            return true;
        } else
            return false;
    }

    /**
     * @param $email
     * @param null $name
     * @return bool
     */
    public function setFrom($email, $name = null)
    {
        if ($this->connected === true && $this->emailCheck($email) && empty($this->from)) {
            @fwrite($this->socket, "MAIL FROM:<{$email}>" . $this->eol);
            $this->execute("MAIL FROM");
            $this->from = (!empty($name) ? "\"{$name}\" <{$email}>" :
                $email);
            return true;
        } else {
            return false;
        }
    }

    /**
     * @param $name
     * @param $content
     */
    public function addHeader($name, $content)
    {
        $this->header[] = "{$name}: {$content}{$this->eol}";
    }

    /**
     * @param $sText
     * @param bool $bEmulate_imap_8bit
     * @return string
     */
    public function qpEncode($sText, $bEmulate_imap_8bit = true)
    {
        $aLines = explode(chr(13) . chr(10), $sText);
        for ($i = 0; $i < count($aLines); $i++) {
            $sLine = &$aLines[$i];
            if (strlen($sLine) === 0)
                continue;
            $sRegExp = '/[^\x09\x20\x21-\x3C\x3E-\x7E]/e';
            if ($bEmulate_imap_8bit)
                $sRegExp = '/[^\x20\x21-\x3C\x3E-\x7E]/e';
            $sReplmt = 'sprintf( "=%02X", ord ( "$0" ) ) ;';
            $sLine = preg_replace($sRegExp, $sReplmt, $sLine);
            $iLength = strlen($sLine);
            $iLastChar = ord($sLine{$iLength - 1});
            if (!($bEmulate_imap_8bit && ($i == count($aLines) - 1)))
                if (($iLastChar == 0x09) || ($iLastChar == 0x20)) {
                    $sLine{$iLength - 1} = '=';
                    $sLine .= ($iLastChar == 0x09) ? '09' : '20';
                }
            if ($bEmulate_imap_8bit) {
                $sLine = str_replace(' =0D', '=20=0D', $sLine);
            }
            preg_match_all('/.{1,73}([^=]{0,2})?/', $sLine, $aMatch);
            $sLine = implode('=' . chr(13) . chr(10), $aMatch[0]); // add soft crlf's
        }
        return implode(chr(13) . chr(10), $aLines);
    }

    /**
     * @return array
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return array
     */
    public function getLogs()
    {
        return $this->logs;
    }
}