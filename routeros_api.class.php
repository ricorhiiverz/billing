<?php
/**
 * RouterOS API client implementation.
 * @author Ben Menking <ben@infotechsc.com>
 * @copyright 2015-2019 Ben Menking
 * @license https://github.com/BenMenking/routeros-api/blob/master/LICENSE MIT
 */

class RouterosAPI
{
    public $debug = false; // Show debug information
    public $connected = false; // Connection status
    public $port = 8728; // RouterOS API port
    public $timeout = 3; // Connection timeout
    public $attempts = 5; // Connection attempts
    public $delay = 3; // Delay between connection attempts

    private $socket; // Socket resource
    private $error_no; // Error number
    private $error_str; // Error string

    /**
     * Connect to RouterOS
     *
     * @param string $ip       Hostname (IP or domain) of the RouterOS server
     * @param string $username Login username
     * @param string $password Login password
     *
     * @return boolean                Connection status
     */
    public function connect($ip, $username, $password)
    {
        for ($ATTEMPT = 1; $ATTEMPT <= $this->attempts; ++$ATTEMPT) {
            $this->connected = false;
            $this->debug('Connection attempt #' . $ATTEMPT . ' to ' . $ip . '...');
            if ($this->socket = @fsockopen($ip, $this->port, $this->error_no, $this->error_str, $this->timeout)) {
                socket_set_timeout($this->socket, $this->timeout);
                if ($this->login($username, $password)) {
                    $this->debug('Connected successfully to ' . $ip . '!');
                    $this->connected = true;
                    break;
                }
            }
            sleep($this->delay);
        }
        return $this->connected;
    }

    /**
     * Disconnect from RouterOS
     *
     * @return void
     */
    public function disconnect()
    {
        fclose($this->socket);
        $this->connected = false;
        $this->debug('Disconnected');
    }

    /**
     * Parse response from RouterOS
     *
     * @param array $response Response data
     *
     * @return array              Parsed response data
     */
    public function parseResponse($response)
    {
        if (is_array($response)) {
            $PARSED      = array();
            $CURRENT     = null;
            $singlevalue = null;
            foreach ($response as $x) {
                if (in_array($x, array('!fatal', '!re', '!trap'))) {
                    if ($x == '!re') {
                        $CURRENT =& $PARSED[];
                    } else {
                        $CURRENT =& $PARSED[$x][];
                    }
                } elseif ($x != '!done') {
                    $MATCHES = explode('=', $x, 2);
                    if ($MATCHES[0] == 'ret') {
                        $singlevalue = $MATCHES[1];
                    }
                    $CURRENT[$MATCHES[0]] = $MATCHES[1];
                }
            }

            if (empty($PARSED) && !is_null($singlevalue)) {
                $PARSED = $singlevalue;
            }

            return $PARSED;
        }
        return array();
    }

    /**
     * Read data from RouterOS
     *
     * @return array              RouterOS response data
     */
    public function read()
    {
        $RESPONSE = array();
        while (true) {
            $BYTE   = ord(fread($this->socket, 1));
            $LENGTH = 0;
            if ($BYTE & 128) {
                if (($BYTE & 192) == 128) {
                    $LENGTH = (($BYTE & 63) << 8) + ord(fread($this->socket, 1));
                } else {
                    if (($BYTE & 224) == 192) {
                        $LENGTH = (($BYTE & 31) << 8) + ord(fread($this->socket, 1));
                        $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                    } else {
                        if (($BYTE & 240) == 224) {
                            $LENGTH = (($BYTE & 15) << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        } else {
                            $LENGTH = ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                            $LENGTH = ($LENGTH << 8) + ord(fread($this->socket, 1));
                        }
                    }
                }
            } else {
                $LENGTH = $BYTE;
            }
            $_ = '';
            if ($LENGTH > 0) {
                $_      = '';
                $retlen = 0;
                while ($retlen < $LENGTH) {
                    $toread = $LENGTH - $retlen;
                    $_ .= fread($this->socket, $toread);
                    $retlen = strlen($_);
                }
                $RESPONSE[] = $_;
                $this->debug('Received: ' . $_);
            }
            if ($BYTE == 0) {
                return $RESPONSE;
            }
        }
    }

    /**
     * Write (send) data to RouterOS
     *
     * @param string  $command  A string with the command to send
     * @param boolean $param2   If we are sending a second parameter
     *
     * @return void
     */
    public function write($command, $param2 = true)
    {
        if ($command) {
            $data = explode("\n", $command);
            foreach ($data as $com) {
                $com = trim($com);
                fwrite($this->socket, $this->encodeLength(strlen($com)) . $com);
                $this->debug('Sent: ' . $com);
            }
            if (gettype($param2) == 'integer') {
                fwrite($this->socket, $this->encodeLength(strlen('.tag=' . $param2)) . '.tag=' . $param2 . chr(0));
                $this->debug('Sent: .tag=' . $param2);
            } elseif (gettype($param2) == 'boolean') {
                fwrite($this->socket, ($param2 ? chr(0) : ''));
            }
        }
    }

    /**
     * Write (send) data to RouterOS
     *
     * @param string  $com      A string with the command to send
     * @param array   $arr      An array with arguments or queries
     *
     * @return array                  RouterOS response data
     */
    public function comm($com, $arr = array())
    {
        $count = count($arr);
        $this->write($com, !$arr);
        $i = 0;
        if (is_array($arr)) {
            foreach ($arr as $key => $value) {
                switch (substr($key, 0, 1)) {
                    case '?':
                        $this->write('?' . substr($key, 1) . '=' . $value, true);
                        break;
                    case '~':
                        $this->write('~' . substr($key, 1) . '=' . $value, true);
                        break;
                    default:
                        $this->write('=' . $key . '=' . $value, ($i + 1) == $count);
                        break;
                }
                $i++;
            }
        }
        return $this->read();
    }

    /**
     * Standard login method
     *
     * @param string $username Login username
     * @param string $password Login password
     *
     * @return boolean                Login status
     */
    private function login($username, $password)
    {
        $RESPONSE = $this->comm('/login', array('name' => $username, 'password' => $password));
        if (isset($RESPONSE[0]) && $RESPONSE[0] == '!done') {
            return true;
        }
        return false;
    }

    /**
     * Encode length of the string
     *
     * @param integer $length Length of the string
     *
     * @return string               Encoded length
     */
    private function encodeLength($length)
    {
        if ($length < 0x80) {
            return chr($length);
        }
        if ($length < 0x4000) {
            return chr(($length >> 8) | 0x80) . chr($length & 0xFF);
        }
        if ($length < 0x200000) {
            return chr(($length >> 16) | 0xC0) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
        if ($length < 0x10000000) {
            return chr(($length >> 24) | 0xE0) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
        }
        return chr(0xF0) . chr(($length >> 24) & 0xFF) . chr(($length >> 16) & 0xFF) . chr(($length >> 8) & 0xFF) . chr($length & 0xFF);
    }

    /**
     * Print text for debug purposes
     *
     * @param string $text Text to print
     *
     * @return void
     */
    private function debug($text)
    {
        if ($this->debug) {
            echo $text . "\n";
        }
    }
}
