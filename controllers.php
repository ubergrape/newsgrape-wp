<?php

class NGCP_Core_Controller {
	static function post($post_ID) {
		if (!NGCP_Core_Controller::check_nonce() || !NGCP_Core_Controller::has_api_key()) {
			return $post_ID;
		}
		
		$post = new NGCP_Post($post_ID);
		
		if (!$post->should_be_synced()) {
			ngcp_debug("controller: post -> STOP (should not be synced)");
			return $post_ID;
		}
		
		if ($post->should_be_deleted_because_category_changed()) {
			ngcp_debug("controller: post -> delete (should be deleted, category)");
			return NGCP_Core_Controller::delete($post_ID);
		}
		
		if ($post->was_synced()) {
			ngcp_debug("controller: post -> edit (was synced before)");
			return NGCP_Core_Controller::edit($post_ID);
		}
		
		$api = new NGCP_API();
		$api->create($post);
		
		return $post_ID;
	}
	
	static function edit($post_ID) {
		if (!NGCP_Core_Controller::check_nonce() || !NGCP_Core_Controller::has_api_key()) {
			return $post_ID;
		}

		$post = new NGCP_Post($post_ID);
		
		if (!$post->was_synced()) {
			ngcp_debug("controller: edit -> post (was never synced before)");
			return NGCP_Core_Controller::post($post_ID);
		}
		
		if ($post->should_be_deleted_because_private()) {
			ngcp_debug("controller: edit -> delete (should be deleted, private)");
			return NGCP_Core_Controller::delete($post_ID);
		}
		
		$api = new NGCP_API();
		$api->update($post);
		
		return $post_ID;
	}
	
	static function delete($post_ID) {
		if (!NGCP_Core_Controller::check_nonce() || !NGCP_Core_Controller::has_api_key()) {
			return $post_ID;
		}

		$post = new NGCP_Post($post_ID);
		
		if ($post->was_never_synced()) {
			ngcp_debug("controller: delete -> STOP (was never synced before)");
			return $post_ID;
		}
		
		$api = new NGCP_API();
		$api->delete($post);
		
		return $post_ID;
	}
	
	static function save($post_ID) {		
		if(get_post_status($post_ID)=='auto-draft') {
			return $post_ID;
		}
		

		$meta_keys = array(
			'ngcp_language',
			'ngcp_license',
			'ngcp_comments',
			'ngcp_type',
			'ngcp_sync',
			'ngcp_category',
			'ngcp_description',
		);
		
		foreach ($meta_keys as $meta_key) {
			$meta_value = 0;
			if (isset($_POST[$meta_key])) {
				$meta_value = $_POST[$meta_key];
				if ('on' == $meta_value) { $meta_value = 1; }
				if ('off' == $meta_value) { $meta_value = 0; }
			}
			update_post_meta($post_ID, $meta_key, $meta_value);
		}
		
		ngcp_debug("controller: saved post ".$post_ID);
		
		return $post_ID;
	}
	
	static function has_api_key() {
		$options = ngcp_get_options();
		
		if ("" == $options['api_key']) {
			update_option('ngcp_error_notice', array("no_api_key" => "No API key set."));
			ngcp_debug("controller: NO API KEY");
			return False;
		}
		
		return True;
	}
	
	static function check_nonce() {
		/*if (!isset($_POST['ngcp_nonce']) || False==wp_verify_nonce($_POST['ngcp_nonce'], "ngcp_metabox")) {
			update_option('ngcp_error_notice', array("wrong_nonce" => "Wrong NONCE"));
			ngcp_debug("controller: STOP (wrong nonce)");
			return False;
		}*/
		return True;
	}
}
		
	
