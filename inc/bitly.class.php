<?php

// Bit.Ly Class made by Mike Rogers
if (!class_exists('BitLy')) {

    class BitLy {

        private $login, $apikey;
        public $headerInfo, $results;

        public function __construct($login, $apikey) {
            $this->login = $login;
            $this->apikey = $apikey;
        }

        private function apiCall($url) {
            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_VERBOSE, 1);
                curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_PORT, 80);
                curl_setopt($ch, CURLOPT_POST, NULL);
                curl_setopt($ch, CURLOPT_URL, $url);

                $this->results = curl_exec($ch);
                $this->headerInfo = curl_getinfo($ch);
                curl_close($ch);

                if ($this->headerInfo['http_code'] == 200) {
                    return $this->results;
                }
                return FALSE;
            }
            return FALSE;
        }

        public function verifyBitLy() {
            $this->apiCall('http://api.bit.ly/v3/validate?format=txt&x_login=notbilytapi&x_apiKey=not_apikey&login=' . $this->login . '&apikey=' . $this->apikey);

            if ($this->results == 0) {
                return TRUE;
            }
            return FALSE;
        }

        public function shorten($url) {
            return $this->apiCall('http://api.bit.ly/v3/shorten?format=txt&login=' . $this->login . '&apikey=' . $this->apikey . '&longUrl=' . urlencode($url));
        }

    }

}
?>