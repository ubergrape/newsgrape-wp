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
	public $is_promotional = False;
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
		$this->is_promotional = get_post_meta($wp_post_id, 'ngcp_promotional', true) || false;
		
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
			'is_promotional'	=> $this->is_promotional,
		);
		
		// Check for post image
		if($image = $this->find_a_post_image()){
			$data['image'] = base64_encode_image($image); //TODO thumbnail size?
		}
		
		// If creative add genre
		if(1==$this->is_creative){
			$data['genre'] = get_post_meta($this->wp_id, 'ngcp_category', true);
		}
		
		return http_build_query($data);
	}
	
	function find_a_post_image() {
		$image = false;
		
		// Check for post image
		if(null!=get_post_thumbnail_id($this->wp_id)){
			$image = get_attached_file(get_post_thumbnail_id($this->wp_id));
			ngcp_debug('post image found');
		}
		
		// use first image in post if only one image
		if(!$image) {
			$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $this->wp_post->post_content, $matches, PREG_SET_ORDER);
			if(1==count($matches) && 2==count($matches[0])) { // only one image in post
				$image_url = $matches[0][1];
				
				ngcp_debug("image tag found in post: $image_url");
				
				// find the image in the gallery
				// hint: there can be images in the gallery which are not in in the post content
				//       also the post can contain images which are not
				$args = array(
					'post_type' => 'attachment',
					'post_mime_type' => 'image',
					'numberposts' => null,
					'post_status' => null,
					'post_parent' => $this->wp_id
				);
				$attachments = get_posts($args);
				if (0 < count($attachments)) {
					foreach($attachments as $attachment) {
						if(wp_get_attachment_url($attachment->ID) == $image_url) {
							ngcp_debug('image found in post gallery');
							$image = get_attached_file($attachment->ID);
						}
					}
				}
				
				if(!$image) {
					ngcp_debug('image not found in gallery, ignoring');
				}
			}
		}
        
        if(!$image) {
			ngcp_debug('no usable image found in post');
		}
        
		return $image;
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
