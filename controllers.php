<?php

class NGCP_Core_Controller {
	static function post($post_ID) {
		$post = new NGCP_Post($post_ID);
		
		if (!$post->should_be_crossposted()) {
			return $post_ID;
		}
		
		if ($post->should_be_deleted_because_category_changed()) {
			return NGCP_Core_Controller::delete($post_ID);
		}
		
		if ($post->was_crossposted()) {
			return NGCP_Core_Controller::edit($postID);
		}
		
		$api = new NGCP_API();
		$api->create($post);
		
		return $post_ID;
	}
	
	static function edit($post_ID) {
		$post = new NGCP_Post($post_ID);
		
		if (!$post->was_crossposted()) {
			return $post_ID;
		}
		
		if ($post->should_be_deleted_because_private()) {
			return NGCP_Core_Controller::delete($post_ID);
		}
		
		$api = new NGCP_API();
		$api->update($post);
	}
	
	static function delete($post_ID) {
		$post = new NGCP_Post($post_ID);
		
		if ($post->was_never_crossposted()) {
			return $post_ID;
		}
		
		$api = new NGCP_API();
		$api->delete($post);
		
		return $post_ID;
	}
}
		
	
