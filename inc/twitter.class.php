<?php

## The below file was taken from: http://nullinfo.wordpress.com/oauth-twitter/
include ('oauth.func.php'); // Intend to eventually recode this file.
## End taken stuff :)
// Twitter Class made by Mike Rogers based on oauth-twitter by joechung.
if (!class_exists('Twitter')) {

    class Twitter {

        private $consumer_key, $consumer_secret;
        public $headerInfo, $oauth_token_secret, $oauth_token, $results, $screen_name;

        public function __construct($consumer_key, $consumer_secret, $oauth_token, $oauth_token_secret) {
            $this->consumer_key = $consumer_key;
            $this->consumer_secret = $consumer_secret;
            $this->oauth_token = $oauth_token;
            $this->oauth_token_secret = $oauth_token_secret;
        }

        private function apiCall($url, $params=NULL, $oauth_token_secret=NULL, $post='POST') {
            $params['oauth_version'] = '1.0';
            $params['oauth_nonce'] = mt_rand();
            $params['oauth_timestamp'] = time();
            $params['oauth_consumer_key'] = $this->consumer_key;
            $params['oauth_signature_method'] = 'HMAC-SHA1';

            if ($oauth_token_secret === TRUE) {
                $params['oauth_signature'] = oauth_compute_hmac_sig($post, $url, $params, $this->consumer_secret, null);
            } else {
                $params['oauth_token'] = $this->oauth_token;
                $params['oauth_signature'] = oauth_compute_hmac_sig($post, $url, $params, $this->consumer_secret, $this->oauth_token_secret);
            }

            if (function_exists('curl_init')) {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_PORT, 80);
                if ($post == 'POST') {
                    curl_setopt($ch, CURLOPT_POST, TRUE);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, oauth_http_build_query($params));
                } else {
                    curl_setopt($ch, CURLOPT_POST, 0);
                    $url = $url . '?' . oauth_http_build_query($params);
                }
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

        public function requestToken($oauth_callback='oob') {
            $params['oauth_callback'] = $oauth_callback;
            $tokens = ($this->apiCall('http://api.twitter.com/oauth/request_token', $params, TRUE));
            if ($tokens != FALSE) {
                $tokens = oauth_parse_str($tokens);
                $this->oauth_token = $tokens['oauth_token'];
                $this->oauth_token_secret = $tokens['oauth_token_secret'];
                return $tokens;
            }
            return FALSE;
        }

        public function accessToken($oauth_verifier='') {
            $params['oauth_verifier'] = $oauth_verifier;
            $tokens = ($this->apiCall('http://api.twitter.com/oauth/access_token', $params));
            if ($tokens != FALSE) {
                $tokens = oauth_parse_str($tokens); // Store these.
                $this->oauth_token = $tokens['oauth_token'];
                $this->oauth_token_secret = $tokens['oauth_token_secret'];
                $this->screen_name = $tokens['screen_name'];
                return $tokens;
            }
            return FALSE;
        }

        public function updateStatus($status, $params='') {
            $params['status'] = htmlentities($status);
            return $this->apiCall('http://api.twitter.com/1/statuses/update.json', $params);
        }

        public function mentions($params='') {
            return $this->apiCall('http://api.twitter.com/1/statuses/mentions.json', $params, NULL, 'GET');
        }

        public function sendDM($text, $screen_name, $params='') {
            $params['text'] = $text;
            $params['screen_name'] = $screen_name;
            return $this->apiCall('http://api.twitter.com/1/direct_messages/new.json', $params);
        }

        public function addUserToList($list_id, $user, $id) {
            $params['id'] = htmlentities($id);
            return $this->apiCall('http://api.twitter.com/1/' . $user . '/' . $list_id . '/members.json', $params);
        }

        public function DeleteUserFromList($list_id, $user, $id) {
            $params['id'] = htmlentities($id);
            $params['_method'] = htmlentities('DELETE');
            return $this->apiCall('http://api.twitter.com/1/' . $user . '/' . $list_id . '/members.json', $params);
        }

        public function getList($list_id, $user, $since_id='0') {
            $params['since_id'] = $since_id;
            $params['per_page'] = '200';
            return $this->apiCall('http://api.twitter.com/1/' . $user . '/lists/' . $list_id . '/statuses.xml', $params, NULL, 'GET');
        }

        public function getTimeline($screen_name, $count=100) {
            $params['screen_name'] = $screen_name;
            $params['count'] = $count;
            return $this->apiCall('http://api.twitter.com/1/statuses/user_timeline.json', $params, NULL, 'GET');
        }

    }

}
?>