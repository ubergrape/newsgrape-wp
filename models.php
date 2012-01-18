<?php

function base64_encode_image ($filename=string,$filetype=string) {
    if ($filename) {
        $imgbinary = fread(fopen($filename, "r"), filesize($filename));
        return 'data:image/' . $filetype . ';base64,' . base64_encode($imgbinary);
    }
}

class NGCP_Post {
	private $wp_post;
	private $wp_id;
	private $id = 0;
	private $type;
	private $slug = "";
	private $url = "";
	private $status ="";
	private $title = "";
	private $title_plain = "";
	private $content = "";
	private $description = "";
	private $language = "en";
	private $tags = array();
	
	function __construct($wp_post_id = NULL) {
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
		$this->wp_id		= (int) $wp_post_id;
		$this->id			= get_post_meta($this->wp_id, 'ngcp_id', true);
		$this->type			= $wp_post->post_type;
		$this->slug			= $wp_post->post_name;
		$this->url			= get_permalink($this->wp_id);
		$this->status		= $wp_post->post_status;
		$this->title		= get_the_title($this->wp_id);
		$this->title_plain	= strip_tags(@$this->title);
		$this->content		= $the_content;
		$this->description	= $wp_post->post_excerpt;
		$this->language		= 'de'; //get_bloginfo('language').substr(0,2); //TODO let user select in sidebar
		$this->tags			= $this->import_tags($wp_post_id);
	}
	
	function import_tags($wp_post_id) {
		$tags = array();
		
		$options = ngcp_get_options();
		
		$tag = $options['tag'];
		
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
			'title'			=> $this->title,
			'pub_status'	=> 3, // 0=Unpublished, 3=Published
			'description'	=> $this->description,
			'language'		=> $this->language,
			'text'			=> $this->content,
			'image_blob'	=> base64_encode_image(get_the_post_thumbnail($this->wp_id)), //TODO thumbnail size?
			'tags'			=> json_encode($this->tags)
		);
		
		return http_build_query($data);
	}
	
	function should_be_crossposted() {
		return True; //TODO testing
		// If the post was manually set to not be crossposted,
		// or nothing was set and the default is not to crosspost,
		// or it's private and the default is not to crosspost private posts, give up now
		$options = ngcp_get_options();
		
		if (
			0 == $options['crosspost'] ||
			get_post_meta($this->wp_id, 'ngcp_no', true) ||
			('private' == $this->post_status && $options['privacy_private'] == 'ngcp_no')
		) {
			return False;
		}
		
		return True;
	}
	
	function should_be_deleted_because_private(){
		return False; //TODO testing
		// If ...
		// - It's changed to private, and we've chosen not to crosspost private entries
		// - It now isn't published or private (trash, pending, draft, etc.)
		// - It was crossposted but now it's set to not crosspost
		$options = ngcp_get_options();
		
		if (
			('private' == $this->post_status && $options['privacy_private'] == 'ngcp_no') || 
			('publish' != $this->post_status && 'private' != $this->post_status) || 
			1 == get_post_meta($this->wp_id, 'ngcp_no', true)
		) {
			return False;
		}
		
		return True;
	}
	
	function should_be_deleted_because_category_changed() {
		return False; //TODO testing
		// If the post shows up in the forbidden category list and it has been
		// crossposted before (so the forbidden category list must have changed),
		// delete the post. Otherwise, just give up now
		$should_be_deleted = True;
		
		$options = ngcp_get_options();

		$postcats = wp_get_post_categories($this->wp_id);
		foreach($postcats as $cat) {
			if(in_array($cat, $options['categories'])) {
				$should_be_deleted = False;
				break; // decision made and cannot be altered, fly on
			}
		}

		return $should_be_deleted;
	}
	
	function was_crossposted() {
		return (0 != $this->id);
	}
}

?>
