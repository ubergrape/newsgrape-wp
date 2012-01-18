<?php

class NGCP_API {
	private $api_path = 'http://www.newsgrape.com/api/0.1/';
	private $dev = true; //TODO remove
	private $client = '';
	private $key = 'b2b269db0dedf4b89fcf8aafc9ea8a9820cf5a0d'; //TODO empty
	private $user = 'wp_test'; //TODO empty
	
	function __construct() {
		$this->client = 'Wordpress/'.get_bloginfo('version').' NGWPCrossposter/1.0'; //TODO discuss name
		if ($this->dev) {
			 $this->api_path = 'http://staging.newsgrape.com/api/0.1/';
		}
	}
	
	function get_key() {
		$this->report(__FUNCTION__);
		
		if ($this->key != NULL) {
			$this->report(__FUNCTION__, "key from db: " . $this->key);
			return $this->key;
		}
		
		// if no key, fetch
		$url = $this->api_path . "key/";
		$args = array(
			'body' => array( 'username' => 'wp_test', 'password' => 'password' )
		);
		
		$response = wp_remote_post($url,$args);
		
		if (is_wp_error($response)) {
			$this->error(__FUNCTION__,'Something went wrong with the API key request');
			return False;
		}
		
		$response_decoded = json_decode($response['body'],true);
		if ($response_decoded == NULL || !in_array("key",$response_decoded)) {
			$this->error(__FUNCTION__,'Something went wrong while decoding json');
			return False;
		}
		
		$this->key = $response_decoded['key'];
		
		$this->report(__FUNCTION__,'fetched key: '.$this->key);
		
		return $this->key;
	}
	
	function create($post) {
		$this->report(__FUNCTION__,$post);
			
		$url = $this->api_path.'articles/';
		
		$args = array(
			'headers' => $this->get_headers(),
			'body' => $post->urlencoded()
		);
		
		$response = wp_remote_post($url,$args);
		$this->report(__FUNCTION__,"POST ".$url." [".$args["body"]."]");
		
		if (is_wp_error($response)) {
			$this->error(__FUNCTION__,'Something went wrong with the article creation: '.$response->get_error_message());
			return False;
		}
		
		$response_decoded = json_decode($response['body'],true);
		if ($response_decoded == NULL || !array_key_exists("id",$response_decoded) || !array_key_exists("display_url",$response_decoded)) {
			$this->error(__FUNCTION__,'Something went wrong while decoding json answer: '.$response['body']);
			return False;
		}
		
		update_post_meta($post->wp_id, 'ngcp_id', $response_decoded['id']);
		update_post_meta($post->wp_id, 'ngcp_display_url', $response_decoded['display_url']);
		update_post_meta($post->wp_id, 'ngcp_sync', time());
		
		$this->report(__FUNCTION__,'done');
	}
	
	function get($post) {
		$url = $this->api_path.'articles/';
		
		$args = array(
			'headers' => $this->get_headers(),
			'body' => $post_urlencoded
		);
		
		$response = wp_remote_post($url,$args);
	}
	
	function update($post) {
		$this->report(__FUNCTION__,$post);
		
		$ngcp_id = get_post_meta($post->wp_id, 'ngcp_id', true);
		$url = $this->api_path.'articles/'.$ngcp_id.'/';
		
		$args = array(
			'method' => 'PUT',
			'headers' => $this->get_headers(),
			'body' => $post->urlencoded()
		);
		
		$response = wp_remote_post($url,$args);
		
		if (is_wp_error($response)) {
			$this->error(__FUNCTION__,'Something went wrong with the article creation: '.$response->get_error_message());
			return False;
		}
		
		$response_decoded = json_decode($response['body'],true);
		if ($response_decoded == NULL || !array_key_exists("id",$response_decoded) || !array_key_exists("display_url",$response_decoded)) {
			$this->error(__FUNCTION__,'Something went wrong while decoding json: '.$response['body']);
			return False;
		}
		
		update_post_meta($post->wp_id, 'ngcp_sync', time());
		
		$this->report(__FUNCTION__,'done');
	}
	
	function delete($post) {
		$this->report(__FUNCTION__,$post);
		
		$this->report(__FUNCTION__,'done');
	}	

	private function get_headers() {
		$headers = array(
			'X-NEWSGRAPE-USER' => $this->user,
			'X-NEWSGRAPE-KEY' => $this->get_key(),
			'X-CLIENT' => $this->client,
			'X-BASE-URL' => home_url()
		);
		
		if($this->dev) {
			$headers['Authorization'] = 'Basic ' . base64_encode("stefan" . ':' . "wordpress"); //TODO remove
		}
		
		return $headers;
	}
	
	private function report($function_name, $message="start") {
		if($this->dev) {
			error_log("NGCP API ($function_name): $message");
		}
	}

	private function error($function_name, $message="") {
		if($this->dev) {
			error_log("NGCP API ($function_name) ERROR: $message");
		}
	}

}
