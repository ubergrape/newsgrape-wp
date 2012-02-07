<?php

class NGCP_API {
	private $errors = array();
	
	function __construct($user=null, $api_key=null, $api_url='http://www.newsgrape.com/api/0.1/') {
		$this->api_url = $api_url;
		
		if (NGCP_DEBUG) {
			 $this->api_url = 'http://staging.newsgrape.com/api/0.1/';
		}
		
		/* Client info for newsgrape's statistics */
		$this->client = 'Wordpress/'.get_bloginfo('version').' NGWPCrossposter/1.0'; //TODO discuss name
		
		/* Unique Blog ID. Should be the same after domain change or plugin uninstall:
		 * Doesn't matter if collissions can be found, so we use md5*/
		$this->external_id = 'WP'.md5(AUTH_KEY);
		
		if (null==$user) {
			$options = ngcp_get_options();
			$this->user = $options['user'];
			$this->api_key = $options['api_key'];
		} else {
			$this->user = $user;
			$this->api_key = $api_key;
		}
	}
	
	function fetch_new_key($username, $password) {
		$url = $this->api_url . "key/";
		$args = array(
			'body' => array( 'username' => $username, 'password' => $password )
		);
		
		$response = wp_remote_post($url,$args);
		
		if (is_wp_error($response)) {
			$this->error(__FUNCTION__,__('API key request failed: '.$result->get_error_message()),'key_fetch_fail');
			return False;
		}
		
		$response_decoded = json_decode($response['body'],true);
		if ($response_decoded == NULL || !array_key_exists("key",$response_decoded)) {
			$this->error(__FUNCTION__,__($username.":".$password.'Something went wrong while decoding json answer: '.substr($response['body'],0,300)),'key_fetch_fail');
			return False;
		}
		
		$key = $response_decoded['key'];
		
		$this->report(__FUNCTION__,'fetched key: '.$key);
		
		return $key;
	}
	
	function create($post) {
		$this->report(__FUNCTION__,$post);
			
		$url = $this->api_url.'articles/';
		
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
		if ($response_decoded == NULL || !array_key_exists("id", $response_decoded) || !array_key_exists("display_url",$response_decoded)) {
			if ($response_decoded != NULL && array_key_exists("message", $response_decoded)) {
				$this->error(__FUNCTION__,'Fetch Key failed: '.$response_decoded['message']);
			} else {
				$this->error(__FUNCTION__,'Something went wrong while decoding json answer: '.substr($response['body'],0,300));
			}
			return False;
		}
		
		update_post_meta($post->wp_id, 'ngcp_id', $response_decoded['id']);
		update_post_meta($post->wp_id, 'ngcp_display_url', $response_decoded['display_url']);
		update_post_meta($post->wp_id, 'ngcp_sync', time());
		
		$this->report(__FUNCTION__,'done');
		
		return True;
	}
	
	function get($post) {
		$url = $this->api_url.'articles/';
		
		$args = array(
			'headers' => $this->get_headers(),
			'body' => $post_urlencoded
		);
		
		$response = wp_remote_post($url,$args);
	}
	
	function update($post) {
		$this->report(__FUNCTION__,$post);
		
		$ngcp_id = get_post_meta($post->wp_id, 'ngcp_id', true);
		$url = $this->api_url.'articles/'.$ngcp_id.'/';
		
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
		
		return True;
	}
	
	function delete($post) {
		$this->report(__FUNCTION__,$post);
		
		$this->report(__FUNCTION__,'done');
	}	

	private function get_headers() {
		$headers = array(
			'X-NEWSGRAPE-USER' => $this->user,
			'X-NEWSGRAPE-KEY' => $this->api_key,
			'X-CLIENT' => $this->client,
			'X-EXTERNAL-ID' => $this->external_id,
			'X-BASE-URL' => home_url()
		);
		
		if(NGCP_DEBUG) {
			$headers['Authorization'] = 'Basic ' . base64_encode("stefan" . ':' . "wordpress"); //TODO remove
		}
		
		return $headers;
	}
	
	private function report($function_name, $message="start") {
		if(NGCP_DEBUG) {
			error_log("NGCP API ($function_name): $message");
		}
	}

	private function error($function_name, $message="", $id=null) {
		if($id) {
			$this->errors[$id] = __($message, 'ngcp');
		} else {
			$this->errors[$function_name] = __($message, 'ngcp');
		}
		if(NGCP_DEBUG) {
			error_log("NGCP API ($function_name) ERROR: $message ($id)");
		}
	}

	function has_errors() {
		return (0 != sizeof($this->error));
	}
	
	function handle_errors() {
		if($this->has_errors()) {
			update_option('ngcp_error_notice', $this->errors);
		}
	}
}
