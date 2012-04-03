<?php

class NGCP_API {
	private $errors = array();
	
	function __construct($username=null, $api_key=null, $api_url='http://www.newsgrape.com/api/0.1/') {
		$this->api_url = $api_url;
		
		if (NGCP_DEV) {
			 $this->api_url = 'http://staging.newsgrape.com/api/0.1/';
		} 
		
		/* Client info for newsgrape's statistics */
		$this->client = 'Wordpress/'.get_bloginfo('version').' NGWPSync/1.0'; //TODO discuss name
		
		/* Unique Blog ID. Should be the same after domain change or plugin uninstall:
		 * This option will not be deleted when uninstalling the plugin*/
		$this->external_id = get_option('ngcp_blog_id');
		
		/* Blog Name */
		$this->external_name = get_bloginfo('name');
		
		if (null==$username) {
			$options = ngcp_get_options();
			$this->username = $options['username'];
			$this->api_key = $options['api_key'];
		} else {
			$this->username = $username;
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
			$this->error(__FUNCTION__,__('API key request failed: '.$response->get_error_message()),'key_fetch_fail');
			return False;
		}
		
		$response_decoded = json_decode($response['body'],true);
		if ($response_decoded == NULL || !array_key_exists("key",$response_decoded)) {
			$this->error(__FUNCTION__,__($username.":".$password.'Something went wrong while decoding json answer: '.substr($response['body'],0,300)),'key_fetch_fail');
			echo "<pre>"; print_r($response); echo "</pre>";
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
				$this->error(__FUNCTION__,'Article creation failed: '.$response_decoded['message']);
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
		
		$url = $this->api_url.'articles/'.$post->id.'/';
		
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
			$this->error(__FUNCTION__,'Something went wrong while decoding json answer: '.substr($response['body'],0,300));
			return False;
		}
		
		update_post_meta($post->wp_id, 'ngcp_sync', time());
		
		$this->report(__FUNCTION__,'done');
		
		return True;
	}
	
	function delete($post) {
		$this->report(__FUNCTION__,$post);
		
		$url = $this->api_url.'articles/'.$post->id.'/';
		
		$args = array(
			'method' => 'DELETE',
			'headers' => $this->get_headers(),
		);
		
		$response = wp_remote_post($url,$args);
		
		if (is_wp_error($response)) {
			$this->error(__FUNCTION__,'Something went wrong deleting the article: '.$response->get_error_message());
			return False;
		}
		
		if (204 != $response['response']['code']) {
			$this->error(__FUNCTION__,'Article could not be deleted.');
			return False;
		}
		
		$this->report(__FUNCTION__,'done');
		
		return True;
	}
	
	function get_languages() {
		$response = $this->get_get('languages/?format=json');
		$output = array();
		if ($response) {
			foreach ($response['objects'] as $lang) {
				$output[$lang['code']] = $lang['name'];
			}
		}
		$this->report(__FUNCTION__,var_export($output, true));
		return $output;
	}
	
	function get_licenses() {
		$response = $this->get_get('licenses/?format=json');
		$output = array();
		if($response) {
			foreach ($response['objects'] as $license) {
				$output[$license['id']] = $license['name'];
			}
		}
		$this->report(__FUNCTION__,var_export($output, true));
		return $output;
	}
	
	function get_creative_categories() {
		$response = $this->get_get('creative_categories/?format=json');
		$output = array();
		if ($response) {
			foreach ($response['objects'] as $cat) {
				$output[$cat['uuid']] = $cat['name'];
			}
		}
		$this->report(__FUNCTION__,var_export($output, true));
		return $output;
	}
	
	function get_get($url='languages/') {
		$this->report(__FUNCTION__,"Get $url");
		
		$url = $this->api_url.$url;
		
		$args = array(
			'headers' => $this->get_headers(),
		);
		
		$response = wp_remote_get($url,$args);
		
		if (is_wp_error($response)) {
			$this->error(__FUNCTION__,'Something went wrong fetching '.$url.': '.$response->get_error_message());
			return False;
		}
		
		$response_decoded = json_decode($response['body'],true);
		if ($response_decoded == NULL || empty($response_decoded)) {
			$this->error(__FUNCTION__,'Something went wrong while decoding json answer: '.substr($response['body'],0,300));
			echo "<pre>"; print_r($response); echo "</pre>";
			return False;
		} else if(array_key_exists("message",$response_decoded)) {
			$this->error(__FUNCTION__,'Something went wrong fetching '.$url.': '.$response_decoded['message']);
			return False;
		}
		
		$this->report(__FUNCTION__,'done');
		
		return $response_decoded;
	}

	private function get_headers() {
		$headers = array(
			'X-NEWSGRAPE-USER' => $this->username, 
			'X-NEWSGRAPE-KEY' => $this->api_key,
			'X-CLIENT' => $this->client,
			'X-EXTERNAL-ID' => $this->external_id,
			'X-BASE-URL' => home_url(),
			'X-EXTERNAL-NAME' => $this->external_name,
		);
		
		if(NGCP_DEBUG) {
			$headers['Authorization'] = 'Basic ' . base64_encode("stefan" . ':' . "wordpress"); //TODO remove
		}
		
		return $headers;
	}
	
	private function report($function_name, $message="start") {
		ngcp_debug("NGCP API ($function_name): $message");
	}

	private function error($function_name, $message="", $id=null) {
		if($id) {
			$this->errors[$id] = __($message, 'ngcp');
		} else {
			$this->errors[$function_name] = __($message, 'ngcp');
		}
		ngcp_debug("NGCP API ($function_name) ERROR: $message ($id)");
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
