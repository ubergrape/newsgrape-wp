<?php

class NGCP_API {
	private $errors = array();
	
	function __construct($username=null, $api_key=null, $api_url='http://www.newsgrape.com/api/0.1/') {
		$this->api_url = $api_url;
		
		if (NGCP_DEV) {
			 $this->api_url = 'http://staging.newsgrape.com/api/0.1/';
		} 
		
		/* Client info for newsgrape's statistics */
		$this->client = 'Wordpress/'.get_bloginfo('version').' NGWPSync/1.0';
		
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
		
	function decode_json_response($response, $function_name, $array_key1=null, $array_key2=null) {
		if (is_wp_error($response)) {
			$this->error($function_name, __('Request failed: ').$response->get_error_message());
			return False;
		}
		
		$response_decoded = json_decode($response['body'],true);
		
		if (204 == $response['response']['code']) {
			return $response_decoded;
		}
		
		if ($response_decoded == null) {
			if($this->is_unauthorized($response)) {
				$this->error($function_name,__('You are not authorized.<br/>Possible reasons:<ul><li>- Your API key has been invalidated. Reconnect with Newsgrape</li><li>- This article has been synced initially with another Newsgrape account</li></ul>'));
			} elseif ($this->is_bad_request($response)) {
				$this->error($function_name,__('The server rejected your request<br/>Possible reasons:<ul><li>The article hast been deleted on newsgrape but not on Wordpress</li><li>- Your Newsgrape Plugin is out of date - update it!</li><li>- The Newsgrape server has problems</li></ul>'));
			} else {
				$this->error($function_name,__('The Newsgrape server sent an unexpected answer.<br/>This looks like your hoster is using a proxy server which blocks requests to newsgrape.com. Please contact your hoster.<br/><br/><a href="#" onclick="jQuery(\'#setting-error-ngcp pre\').show()">Show first 2000 characters of response</a>', 'ngcp').'<pre style="display:none">'.esc_html(substr($response['body'],0,2000)).'</pre>');
			}
			return False;
		}
		
		if ( (null == $array_key1 || !array_key_exists($array_key1, $response_decoded))
			&& (null == $array_key2 || !array_key_exists($array_key1, $response_decoded))
			&& ($array_key1 != $array_key2) 	){
				
			if ($this->is_unauthorized($response) && array_key_exists('message',$response_decoded)) {
				$this->error($function_name, __($response_decoded['message'], 'ngcp'));
				return False;
			} else {
				$this->error(__FUNCTION__,__('The Newsgrape server sent an unexpected answer. Is your Newsgrape Sync plugin up to date? <a href="update-core.php">Update Plugins</a> ', 'ngcp'));
				ngcp_debug("Array keys ($array_key1 $array_key2 message) not found in JSON response : " . substr($response['body'],0,500));
				return False;
			}
		}
		
		return $response_decoded;
	}

	function fetch_new_key($username, $password) {
		$this->report(__FUNCTION__);
		
		$url = $this->api_url . "key/";
		$args = array(
			'body' => array( 'username' => $username, 'password' => $password )
		);
		
		$response = wp_remote_post($url,$args);
		
		$response_decoded = $this->decode_json_response($response,__FUNCTION__,'key');

		// The response can be empty but should not be False
		if (False === $response_decoded) {
			return False;
		}
		
		$key = $response_decoded['key'];
		
		$this->report(__FUNCTION__,'fetched key: '.$key);
		
		return $key;
	}
	
	function change_site_settings($canonical_link=1) {
		$this->report(__FUNCTION__);
		
		$url = $this->api_url . "sites/";
		$args = array(
			'headers' => $this->get_headers(),
			'body' => array( 'canonical_link' => $canonical_link )
		);
		
		$response = wp_remote_post($url,$args);
		
		$response_decoded = $this->decode_json_response($response,__FUNCTION__);
				
		$this->report(__FUNCTION__,'settings changed');
		
		return ("canonical_link setting updated" == $response_decoded);
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
		
		$response_decoded = $this->decode_json_response($response,__FUNCTION__,'id','display_url');

		if (False === $response_decoded) {
			return False;
		}
		
		update_post_meta($post->wp_id, 'ngcp_id', $response_decoded['id']);
		update_post_meta($post->wp_id, 'ngcp_display_url', $response_decoded['display_url']);
		update_post_meta($post->wp_id, 'ngcp_synced', time());
		delete_post_meta($post->wp_id, 'ngcp_deleted');
		
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

		$response_decoded = $this->decode_json_response($response,__FUNCTION__);

		if (False === $response_decoded) {
			return False;
		}
		
		update_post_meta($post->wp_id, 'ngcp_synced', time());
		delete_post_meta($post->wp_id, 'ngcp_deleted');
		
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
		
		$response_decoded = $this->decode_json_response($response,__FUNCTION__);

		if (False === $response_decoded) {
			return False;
		}
		
		if (404 == $response['response']['code']) {
			$this->error(__FUNCTION__,'Article not found on Newsgrape, this means it has been deleted before.');
		} elseif (204 != $response['response']['code']) {
			$this->error(__FUNCTION__,'Article could not be deleted.');
			return False;
		}
		
		update_post_meta($post->wp_id, 'ngcp_deleted', True);
		
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
	
	function get_comment_count() {
		// THIS IS A DUMMY. not implemented on ng api yet
		//$response = $this->get_get('comment_count/');
		return array(12 => 1,// id => comment_count
					 13 => 892,
					 16 => 132,
					 18 => 0);
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
		
		$response_decoded = $this->decode_json_response($response,__FUNCTION__);

		if (!$response_decoded) {
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
		
		return $headers;
	}
	
	private function is_unauthorized($response) {
		return $response['response']['code'] == '401' ;
	}
	
	private function is_bad_request($response) {
		return $response['response']['code'] == '400' ;
	}
	
	private function report($function_name, $message="start") {
		ngcp_debug("NGCP API ($function_name): $message");
	}
	
	function get_error_header($function_name) {
		$error_headers = array(
			'fetch_new_key' => __('Could not login to Newsgrape. ', 'ngcp'),
			'create' => __('Could not create an article. ', 'ngcp'),
			'update' => __('Could not update article. ', 'ngcp'),
			'delete' => __('Could not delete article. ', 'ngcp'),
		);
		
		if (array_key_exists($function_name, $error_headers)) {
			return $error_headers[$function_name];
		}
		
		return '';
	}

	private function error($function_name, $message="", $id=null) {
		global $ngcp_error;
		$ngcp_error = $message;
		
		if($id) {
			$this->errors[$id] = $this->get_error_header($function_name) . __($message, 'ngcp');
		} else {
			$this->errors[$function_name] = $this->get_error_header($function_name) . __($message, 'ngcp');
		}
		$this->handle_errors();
		ngcp_debug("NGCP API ($function_name) ERROR: $message ($id)");
	}

	function has_errors() {
		return (0 != sizeof($this->errors));
	}
	
	function handle_errors() {
		if($this->has_errors()) {
			update_option('ngcp_error_notice', $this->errors);
		}
	}
}
