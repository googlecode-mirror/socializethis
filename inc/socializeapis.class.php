<?php
// TweetMeme Class made by Mike Rogers
if(!class_exists('SocializeAPIs')){
class SocializeAPIs {
	public $headerInfo, $results;
	
	private function apiCall($url){
		if(function_exists('curl_init')){
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
			
			if ($this->headerInfo['http_code'] == 200){
				return $this->results;
			}
			return FALSE;	
		}
		return FALSE;
	}
	
	public function TweetMeme($url){
		$data = unserialize($this->apiCall('http://api.tweetmeme.com/url_info.php?url='.urlencode($url)));
		return array('tm_link' => $data['story']['tm_link'], 'url_count' => (int) $data['story']['url_count']); // Return what I care about.
	}
	public function Reddit($url){
		$data = json_decode($this->apiCall('http://www.reddit.com/api/info.json?url='.urlencode($url)));
		if($data->data->modhash != ''){
			$data = $data->data->children[0]->data;
			return array('permalink'=> (string) $data->permalink, 'score' => (int) $data->score, 'num_comments' => (int) $data->num_comments);
		}else{
			return array('permalink'=> '', 'score' => 0, 'num_comments' => 0);
		}
	}
}
}
?>