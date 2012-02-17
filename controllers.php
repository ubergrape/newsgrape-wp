<?php

class NGCP_Core_Controller {
	static function post($post_ID) {
		if (!NGCP_Core_Controller::has_api_key()) {
			return $post_ID;
		}
		
		$post = new NGCP_Post($post_ID);
		
		if (!$post->should_be_crossposted()) {
			NGCP_Core_Controller::debug("post -> STOP (should not be crossposted)");
			return $post_ID;
		}
		
		if ($post->should_be_deleted_because_category_changed()) {
			NGCP_Core_Controller::debug("post -> delete (should be deleted)");
			return NGCP_Core_Controller::delete($post_ID);
		}
		
		if ($post->was_crossposted()) {
			NGCP_Core_Controller::debug("post -> edit (was crossposted before)");
			return NGCP_Core_Controller::edit($post_ID);
		}
		
		$api = new NGCP_API();
		$api->create($post);
		
		return $post_ID;
	}
	
	static function edit($post_ID) {
		if (!NGCP_Core_Controller::has_api_key()) {
			return $post_ID;
		}

		$post = new NGCP_Post($post_ID);
		
		if (!$post->was_crossposted()) {
			NGCP_Core_Controller::debug("edit -> post (was never crossposted before)");
			return NGCP_Core_Controller::post($post_ID);
		}
		
		if ($post->should_be_deleted_because_private()) {
			NGCP_Core_Controller::debug("edit -> delete (should be deleted)");
			return NGCP_Core_Controller::delete($post_ID);
		}
		
		$api = new NGCP_API();
		$api->update($post);
		
		return $post_ID;
	}
	
	static function delete($post_ID) {
		if (!NGCP_Core_Controller::has_api_key()) {
			return $post_ID;
		}

		$post = new NGCP_Post($post_ID);
		
		if ($post->was_never_crossposted()) {
			NGCP_Core_Controller::debug("delete -> STOP (was never crossposted before)");
			return $post_ID;
		}
		
		$api = new NGCP_API();
		$api->delete($post);
		
		return $post_ID;
	}
	
	static function save($post_ID) {		
		if (!isset($_POST['ngcp_nonce']) || !wp_verify_nonce($_POST['ngcp_nonce'], "ngcp_metabox")) {
			error_log("NGCP Controller save -> STOP; wrong nonce");
			return $post_ID;
		}
		
		$meta_keys = array(
			'ngcp_language',
			'ngcp_license',
			'ngcp_comments',
			'ngcp_type',
			'ngcp_crosspost',
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
		
		return $post_ID;
	}
	
	static function has_api_key() {
		$options = ngcp_get_options();
		
		if ("" == $options['api_key']) {
			update_option('ngcp_error_notice', array("no_api_key" => "No API key set."));
			return False;
		}
		
		return True;
	}
	
	static function debug($message) {
		if (NGCP_DEBUG) {
			error_log("NGCP Core Controller ".$message);
		}
	}
}
		
	
