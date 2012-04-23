<?php

function base64_encode_image ($filename=string,$filetype=string) {
    if ($filename) {
        $imgbinary = fread(fopen($filename, "r"), filesize($filename));
        return base64_encode($imgbinary);
    }
}

class NGCP_Post {
	public $wp_post;
	public $wp_id = 0;
	public $id = 0;
	public $wp_type;
	public $slug = "";
	public $url = "";
	public $status ="";
	public $title = "";
	public $title_plain = "";
	public $content = "";
	public $description = "";
	public $language = "en";
	public $tags = array();
	public $is_creative = False;
	public $options = array();
	
	function __construct($wp_post_id = NULL) {
		$this->options = ngcp_get_options();
		if (NULL != $wp_post_id) {
			$this->import_wp_object($wp_post_id);
		}
	}
	
	function __toString() {
		return $this->title." (".$this->url.")";
	}
			
	function import_wp_object($wp_post_id) {
		$wp_post = &get_post($wp_post_id);
		
		$the_content = $wp_post->post_content;
		$the_content = apply_filters('the_content', $the_content);
		$the_content = str_replace(']]>', ']]&gt;', $the_content);
		
		$this->wp_post		= $wp_post;
		$this->wp_id		= $wp_post_id;
		$this->id			= get_post_meta($wp_post_id, 'ngcp_id', true);
		$this->wp_type		= $wp_post->post_type;
		$this->slug			= $wp_post->post_name;
		$this->url			= get_permalink($wp_post_id);
		$this->post_status 	= $wp_post->post_status;
		$this->title		= get_the_title($wp_post_id);
		$this->title_plain	= strip_tags(@$this->title);
		$this->content		= $the_content;
		$this->description	= get_post_meta($wp_post_id, 'ngcp_description', true);
		$this->language		= get_post_meta($wp_post_id, 'ngcp_language', true);
		//$this->tags			= $this->import_tags($wp_post_id);
		$this->is_creative	= ('creative' == get_post_meta($wp_post_id, 'ngcp_type', true));
		
		if('' == $this->language || 0 == $this->language) {
			$this->language = $this->options['language'];
		}
				
		if('' == $this->description) {
			$this->description = $wp_post->post_excerpt;
		}
	}
	
	function import_tags($wp_post_id) {
		$tags = array();
		
		$options = ngcp_get_options();
		
		$tag = $this->options['tag'];
		
		if (1 == $tag || 3 == $tag) {
			$post_categories = wp_get_post_categories($wp_post_id);
			foreach($post_categories as $c){
				$cat = get_category($c);
				$tags[] = $cat->name;
			}
		}
		if (2 == $tag || 3 == $tag) {
			$post_tags = wp_get_post_categories($wp_post_id);
			foreach($post_tags as $t){
				$tag = get_category($t);
				$tags[] = $tags->name;
			}
		}
		
		return $tags;
	}
	
	function urlencoded() {
		// example:
		// title=this+is+a+title&pub_status=3&description=this+is+a+description&language=en&text=this+is+the+body+text
		
		$data = array (
			'title'				=> $this->title,
			'pub_status'		=> 3, // 0=Unpublished, 3=Published
			'description'		=> $this->description,
			'language'			=> $this->language,
			'text'				=> $this->content,
			//'tags'				=> json_encode($this->tags),
			'external_post_id'	=> $this->wp_id, // has to be unique in combination with the X-EXTERNAL-ID header
			'external_post_url'	=> get_permalink($this->wp_id),
			'is_creative'		=> $this->is_creative,
		);
		
		// Check for post image
		if(null!=get_post_thumbnail_id($this->wp_id)){
			$data['image'] = base64_encode_image(get_attached_file(get_post_thumbnail_id($this->wp_id))); //TODO thumbnail size?
		}
		
		// If creative add genre
		if(1==$this->is_creative){
			$data['genre'] = get_post_meta($this->wp_id, 'ngcp_category', true);
		}
		
		return http_build_query($data);
	}
	
	function should_be_synced() {
		// If the post was manually set to not be synced,
		// or nothing was set and the default is not to sync,
		// or it's private and the default is not to sync private posts, give up now
		
		if (
			0 == $this->options['sync'] ||
			0 == get_post_meta($this->wp_id, 'ngcp_sync', true) ||
			//('private' == $this->post_status && $this->options['privacy_private'] == 'ngcp_no') ||
			('publish' != $this->post_status)
		) {
			return False;
		}
		
		return True;
	}
	
	function should_be_deleted_because_private(){
		// If ...
		// - It's changed to private, and we've chosen not to sync private entries
		// - It now isn't published or private (trash, pending, draft, etc.)
		// - It was synced but now it's set to not sync
		
		if (
			('private' == $this->post_status && $this->options['privacy_private'] == 'ngcp_no') || 
			('publish' != $this->post_status && 'private' != $this->post_status) || 
			0 == get_post_meta($this->wp_id, 'ngcp_sync', true)
		) {
			return True;
		}
		
		return False;
	}
	
	function should_be_deleted_because_category_changed() {
		// If the post shows up in the forbidden category list and it has been
		// synced before (so the forbidden category list must have changed),
		// delete the post. Otherwise, just give up now

		$postcats = wp_get_post_categories($this->wp_id);
		foreach($postcats as $cat) {
			if(in_array($cat, $this->options['skip_cats'])) {
				return True;
			}
		}

		return False;
	}
	
	function was_synced() {
		return !$this->was_never_synced();
		
	}
	
	function was_never_synced() {
	    return ("" == $this->id || null == $this->id || 0 == $this->id);
	}
}

?>
