<?php

declare(strict_types=1);

trait DysonLocalLib
{
    public static $IS_NODATA = IS_EBASE + 1;
    public static $IS_UNAUTHORIZED = IS_EBASE + 2;
    public static $IS_SERVERERROR = IS_EBASE + 3;
    public static $IS_HTTPERROR = IS_EBASE + 4;
    public static $IS_INVALIDDATA = IS_EBASE + 5;
    public static $IS_NOPRODUCT = IS_EBASE + 6;
    public static $IS_PRODUCTMISSІNG = IS_EBASE + 7;
    public static $IS_INVALIDPREREQUISITES = IS_EBASE + 8;
    public static $IS_NOLOGIN = IS_EBASE + 9;
    public static $IS_NOVERIFY = IS_EBASE + 10;

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private static $api_host = 'appapi.cp.dyson.com';
    private static $user_agent = 'DysonLink/32531 CFNetwork/1240.0.4 Darwin/20.5.0';
    private static $cainfo = __DIR__ . '/certs/DigiCert-chain.crt';

    private function GetFormStatus()
    {
        $formStatus = [];
        $formStatus[] = ['code' => IS_CREATING, 'icon' => 'inactive', 'caption' => 'Instance getting created'];
        $formStatus[] = ['code' => IS_ACTIVE, 'icon' => 'active', 'caption' => 'Instance is active'];
        $formStatus[] = ['code' => IS_DELETING, 'icon' => 'inactive', 'caption' => 'Instance is deleted'];
        $formStatus[] = ['code' => IS_INACTIVE, 'icon' => 'inactive', 'caption' => 'Instance is inactive'];
        $formStatus[] = ['code' => IS_NOTCREATED, 'icon' => 'inactive', 'caption' => 'Instance is not created'];

        $formStatus[] = ['code' => self::$IS_NODATA, 'icon' => 'error', 'caption' => 'Instance is inactive (no data)'];
        $formStatus[] = ['code' => self::$IS_UNAUTHORIZED, 'icon' => 'error', 'caption' => 'Instance is inactive (unauthorized)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];
        $formStatus[] = ['code' => self::$IS_NOPRODUCT, 'icon' => 'error', 'caption' => 'Instance is inactive (no product)'];
        $formStatus[] = ['code' => self::$IS_PRODUCTMISSІNG, 'icon' => 'error', 'caption' => 'Instance is inactive (product missing)'];
        $formStatus[] = ['code' => self::$IS_INVALIDPREREQUISITES, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid preconditions)'];
        $formStatus[] = ['code' => self::$IS_NOLOGIN, 'icon' => 'error', 'caption' => 'Instance is inactive (not logged in)'];
        $formStatus[] = ['code' => self::$IS_NOVERIFY, 'icon' => 'error', 'caption' => 'Instance is inactive (login not verified)'];

        return $formStatus;
    }

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_NODATA:
            case self::$IS_UNAUTHORIZED:
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    private function GetStatusText()
    {
        $txt = false;
        $status = $this->GetStatus();
        $formStatus = $this->GetFormStatus();
        foreach ($formStatus as $item) {
            if ($item['code'] == $status) {
                $txt = $item['caption'];
                break;
            }
        }

        return $txt;
    }

    private function getDeviceList()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, 'status=' . $this->GetStatusText(), 0);
            return false;
        }

        $msg = '';
        $auth = $this->doLogin_2fa_1(false, $msg);
        if ($auth == false) {
            $this->SendDebug(__FUNCTION__, 'doLogin_2fa_1() failed', 0);
            return false;
        }
        $jdata = json_decode($auth, true);
        $token = $this->GetArrayElem($jdata, 'token', '');
        if ($token == '') {
            $this->SendDebug(__FUNCTION__, 'doLogin_2fa_1() returned no token', 0);
            return false;
        }

        $url = 'https://' . self::$api_host . '/v2/provisioningservice/manifest';

        $headers = [
            'User-Agent: ' . self::$user_agent,
            'Accept: */*',
            'Authorization: Bearer ' . $token,
        ];

        $this->SendDebug(__FUNCTION__, 'http-get: url=' . $url, 0);
        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERPWD, $auth);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $statuscode = 0;
        $err = '';
        $data = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200 && $httpcode != 201) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }

        if ($statuscode) {
            $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
            $this->SetStatus($statuscode);
            return false;
        }

        return $jdata;
    }

    private function getDevice($serial)
    {
        $devices = $this->getDeviceList();
        if ($devices != '') {
            foreach ($devices as $device) {
                if ($device['Serial'] == $serial) {
                    return $device;
                }
            }
        }
        return false;
    }

    private function decryptPassword($encrypted_password)
    {
        $pwHash = false;

        $key = pack(
                'c*',
                0x01, 0x02, 0x03, 0x04, 0x05, 0x06, 0x07, 0x08,
                0x09, 0x0a, 0x0b, 0x0c, 0x0d, 0x0e, 0x0f, 0x10,
                0x11, 0x12, 0x13, 0x14, 0x15, 0x16, 0x17, 0x18,
                0x19, 0x1a, 0x1b, 0x1c, 0x1d, 0x1e, 0x1f, 0x20
            );
        $iv = pack(
                'c*',
                0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00,
                0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00
            );

        $pw = base64_decode($encrypted_password);
        $data = openssl_decrypt($pw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, $iv);
        if ($data != false) {
            $data = utf8_decode($data);

            // unpad
            $pad = ord(substr($data, strlen($data) - 1, 1));
            $data = substr($data, 0, -$pad);

            $jdata = json_decode($data, true, 512, JSON_UNESCAPED_SLASHES);
            if (isset($jdata['apPasswordHash'])) {
                $pwHash = $jdata['apPasswordHash'];
            }
        }
        return $pwHash;
    }

    private function do_HttpRequest($func, $params, $header, $postdata, $mode, &$cdata, &$cerrno, &$cerror, &$httpcode)
    {
        $url = 'https://' . self::$api_host . $func;

        if ($params != '') {
            $n = 0;
            foreach ($params as $param => $value) {
                $url .= ($n++ ? '&' : '?') . $param . '=' . rawurlencode($value);
            }
        }

        $time_start = microtime(true);

        $this->SendDebug(__FUNCTION__, 'http-' . $mode, 0);
        $this->SendDebug(__FUNCTION__, '... url=' . $url, 0);
        $this->SendDebug(__FUNCTION__, '... header=' . print_r($header, true), 0);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postdata));
                $this->SendDebug(__FUNCTION__, '... postdata=' . print_r($postdata, true), 0);
                break;
            default:
                break;
        }
        curl_setopt($ch, CURLOPT_CAINFO, self::$cainfo);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, ' => cdata=' . $cdata, 0);
    }

    private function checkLogin()
    {
        $auth = $this->ReadAttributeString('Auth');
        $this->SendDebug(__FUNCTION__, 'read Attribute("Auth")=' . $auth, 0);
        if ($auth != '') {
            $jdata = json_decode($auth, true);
            $token = $this->GetArrayElem($jdata, 'token', '');
            if ($token != false) {
                return true;
            }
        }
        return false;
    }

    private function doLogin_2fa_1($force, &$msg)
    {
        if ($force == false) {
            $auth = $this->ReadAttributeString('Auth');
            $this->SendDebug(__FUNCTION__, 'read Attribute("Auth")=' . $auth, 0);
            if ($auth != '') {
                $jdata = json_decode($auth, true);
                $token = $this->GetArrayElem($jdata, 'token', '');
                if ($token != false) {
                    $tstamp = $this->GetArrayElem($jdata, 'tstamp', 0);
                    $this->SendDebug(__FUNCTION__, 'old token=' . $token . ' from ' . date('d.m.Y H:i:s', $tstamp), 0);
                    return $auth;
                }
                $lastLogin = $this->GetArrayElem($jdata, 'lastLogin', 0);
                if ($lastLogin > 0) {
                    $now = time();
                    $dif = $now - $lastLogin;
                    $this->SendDebug(__FUNCTION__, 'lastLogin=' . date('H:i:s', $lastLogin) . ', now=' . date('H:i:s', $now) . ', dif=' . $dif, 0);
                    if ($lastLogin + 300 > time()) {
                        $this->SendDebug(__FUNCTION__, 'try not to login, last attempt was ' . date('H:i:s', $lastLogin) . ' (< 5m ago)', 0);
                        return false;
                    }
                }
            }
        }

        $authData = [
            'lastLogin' => time(),
        ];

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');
        $country = $this->ReadPropertyString('country');
        $country = strtoupper($country);

        $func = '/v3/userregistration/email/userstatus';

        $params = [
            'country' => $country
        ];

        $headers = [
            'User-Agent: ' . self::$user_agent,
            'Accept: */*',
            'Content-Type: application/json',
        ];

        $postdata = [
            'Email'    => $user
        ];

        $this->do_HttpRequest($func, $params, $headers, $postdata, 'POST', $cdata, $cerrno, $cerror, $httpcode);

        $statuscode = 0;
        $err = '';
        $msg = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode == 429) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (too many requests)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
            if ($cdata != '') {
                $jdata = json_decode($cdata, true);
                if (isset($jdata['Message'])) {
                    $msg = $jdata['Message'];
                }
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                if (!isset($jdata['accountStatus'])) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                } else {
                    if ($jdata['accountStatus'] != 'ACTIVE') {
                        $statuscode = self::$IS_INVALIDDATA;
                        $err = 'accountStatus=' . $jdata['accountStatus'];
                    }
                    // result.data.authenticationMethod === 'EMAIL_PWD_2FA'
                }
            }
        }

        $auth = json_encode($authData);
        $this->SendDebug(__FUNCTION__, 'write Attribute("Auth")=' . $auth, 0);
        $this->WriteAttributeString('Auth', $auth);

        if ($statuscode != 0) {
            // $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err . ', msg=' . $msg, 0);
            $this->SetStatus($statuscode);
            if ($msg == '') {
                $msg = 'Login failed';
            }
            return false;
        }

        IPS_Sleep(250);

        $func = '/v3/userregistration/email/auth';

        $country2locale = [
            'EN' => 'en-GB',
            'DE' => 'de-DE',
            'AU' => 'de-AT',
            'CH' => 'de-CH',
            'NL' => 'nl-NL',
            'FR' => 'fr-FR',
        ];

        $params = [
            'country' => $country,
            'culture' => isset($country2locale[$country]) ? $country2locale[$country] : 'en-US'
        ];

        $postdata = [
            'Email'    => $user,
            'Password' => $password
        ];

        $this->do_HttpRequest($func, $params, $headers, $postdata, 'POST', $cdata, $cerrno, $cerror, $httpcode);

        $statuscode = 0;
        $err = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
            if ($cdata != '') {
                $jdata = json_decode($cdata, true);
                if (isset($jdata['Message'])) {
                    $msg = $jdata['Message'];
                }
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                if (!isset($jdata['challengeId'])) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                } else {
                    $authData['challengeId'] = $jdata['challengeId'];
                    $msg = 'Check mailbox for mail with code';
                }
            }
        }

        $auth = json_encode($authData);
        $this->SendDebug(__FUNCTION__, 'write Attribute("Auth")=' . $auth, 0);
        $this->WriteAttributeString('Auth', $auth);

        if ($statuscode != 0) {
            // $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err . ', msg=' . $msg, 0);
            $this->SetStatus($statuscode);
            return false;
        }

        $this->SetStatus(self::$IS_NOVERIFY);
        return $auth;
    }

    private function doLogin_2fa_2($otpCode, &$msg)
    {
        if ($otpCode == '') {
            $this->SendDebug(__FUNCTION__, 'missing otpCode', 0);
            return false;
        }

        $challengeId = '';
        $auth = $this->ReadAttributeString('Auth');
        $this->SendDebug(__FUNCTION__, 'read Attribute("Auth")=' . $auth, 0);
        if ($auth != '') {
            $jdata = json_decode($auth, true);
            $challengeId = $this->GetArrayElem($jdata, 'challengeId', 0);
            $lastLogin = $this->GetArrayElem($jdata, 'lastLogin', 0);
            // lastLogin zu alt -> $challengeId=''
        }
        if ($challengeId == '') {
            $this->SendDebug(__FUNCTION__, 'missing challengeId', 0);
            return false;
        }

        $user = $this->ReadPropertyString('user');
        $password = $this->ReadPropertyString('password');

        $func = '/v3/userregistration/email/verify';

        $params = '';

        $headers = [
            'User-Agent: ' . self::$user_agent,
            'Accept: */*',
            'Content-Type: application/json',
        ];

        $postdata = [
            'Email'       => $user,
            'Password'    => $password,
            'challengeId' => $challengeId,
            'otpCode'     => $otpCode,
        ];

        $this->do_HttpRequest($func, $params, $headers, $postdata, 'POST', $cdata, $cerrno, $cerror, $httpcode);

        $authData = false;

        $statuscode = 0;
        $err = '';
        $msg = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 401) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (unauthorized)';
            } elseif ($httpcode == 403) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode == 429) {
                $statuscode = self::$IS_UNAUTHORIZED;
                $err = 'got http-code ' . $httpcode . ' (too many requests)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = 'got http-code ' . $httpcode;
            }
            if ($cdata != '') {
                $jdata = json_decode($cdata, true);
                if (isset($jdata['Message'])) {
                    $msg = $jdata['Message'];
                }
            }
        } elseif ($cdata == '') {
            $statuscode = self::$IS_INVALIDDATA;
            $err = 'no data';
        } else {
            $jdata = json_decode($cdata, true);
            if ($jdata == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            } else {
                if (!isset($jdata['token'])) {
                    $statuscode = self::$IS_INVALIDDATA;
                    $err = 'malformed response';
                } else {
                    $token = $jdata['token'];
                    $this->SendDebug(__FUNCTION__, 'new token=' . $token, 0);
                    $authData = [
                        'token'  => $token,
                        'tstamp' => time()
                    ];
                    $msg = 'Login successful';
                }
            }
        }

        if ($authData != false) {
            $auth = json_encode($authData);
            $this->SendDebug(__FUNCTION__, 'write Attribute("Auth")=' . $auth, 0);
            $this->WriteAttributeString('Auth', $auth);
        }

        if ($statuscode != 0) {
            // $this->LogMessage('url=' . $url . ' => statuscode=' . $statuscode . ', err=' . $err, KL_WARNING);
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err . ', msg=' . $msg, 0);
            $this->SetStatus($statuscode);
            if ($msg == '') {
                $msg = 'Login failed';
            }
            return false;
        }

        $this->SetStatus(IS_ACTIVE);
        return $auth;
    }
}
