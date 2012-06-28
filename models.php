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
	public $status = "";
	public $pub_date = 0;
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
		$this->pub_date 	= get_post_time('U', True, $wp_post);
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
		
		/* description hirarchy:
		 * 1) ngcp_description (-> normal content)
		 * 2) manual excerpt (-> normal content)
		 * 3) teaser (-> content without teaser)
		 */
		if('' == trim($this->description)) {
			$content = $this->content;
			
			if ( '' != $wp_post->post_excerpt) {
				ngcp_debug('post has manual excerpt');
				$this->description = $wp_post->post_excerpt;
			} else if ( preg_match('/<!--more(.*?)?-->/', $content, $matches) ) {
				ngcp_debug('post has teaser (more tag)');
				$content = explode($matches[0], $content, 2);
				$this->description = $content[0];
				$this->content = $content[1];
	        }
		} else {
			ngcp_debug('post has newsgrape description');
		}
		
		// Trim Title and description
		$this->description = substr($this->description, 0, NGCP_MAXLENGTH_DESCRIPTION);
		$this->title = substr($this->title, 0, NGCP_MAXLENGTH_TITLE);
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
			'pub_date'			=> $this->pub_date,
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
			$resized_image = image_resize($image, NGCP_MAXWIDTH_IMAGE, NGCP_MAXHEIGHT_IMAGE, false, 'newsgrape');
			if (is_wp_error($resized_image)) {
				$resized_image = $image;
				// TODO: inform user about missing GD library etc.
			}
			$data['image'] = base64_encode_image($resized_image);
			$data['text'] = $this->content; // find_a_post_image can modify content
		}
		
		return http_build_query($data);
	}
	
	
	/* finds a post image:
	 * 1) post-thumbnail
	 * 2) first image in post
	 *    if the post starts with an image, the image is removed from the content
	 * 
	 * attention: modifies $this->content
	 */
	function find_a_post_image() {
		$image = false;
		
		// Check for post image
		if(current_theme_supports('post-thumbnails') && null!=get_post_thumbnail_id($this->wp_id)){
			$image = get_attached_file(get_post_thumbnail_id($this->wp_id));
			ngcp_debug('post image found');
		}
		
		// if we have no post image check for images inside content
		if(!$image) {
			
			// strip html except img
			$stripped_content = trim(str_replace('&nbsp;','',strip_tags($this->wp_post->post_content,'<img>')));
			
			// find image urls
			$output = preg_match_all('/<img.+src=[\'"]([^\'"]+)[\'"].*>/i', $stripped_content, $matches, PREG_SET_ORDER);
			
			// do we have at least one image in post?
			if(count($matches) > 0 && 2==count($matches[0])) { 
				
				// first image
				$image_url = $matches[0][1];
				$image_tag = $matches[0][0];
				
				ngcp_debug("image tag found in post: $image_url");
				
				// wordpress resizes images and gives them names like image-150x150.jpg
				$image_ori_url = preg_replace('/\-[0-9]+x[0-9]+/', '', $image_url);
				ngcp_debug("original image: $image_ori_url");
				
				$image = $this->get_image_path_from_url($image_ori_url);
				
				if($image) {
					// does the post start with the image? then remove image
					if(0==strpos($stripped_content, $image_tag)) {
						ngcp_debug('post starts with image, removing image from content');
						$this->content = str_replace($image_tag, '', $this->content);
					}					
				} else {
					ngcp_debug('image not found mediathek, ignoring');
				}
			}
		}
        
        if(!$image) {
			ngcp_debug('no usable image found in post');
		}
        
		return $image;
	}
	
	function get_image_path_from_url($image_ori_url) {
		// find the image in the mediathek
		// hint: there can be images in the gallery which are not in in the post content
		//       also the post can contain images which are not
	
		$args = array(
			'post_type' => 'attachment',
			'post_mime_type' => 'image',
			'numberposts' => null,
			'post_status' => null,
		);
		$attachments = get_posts($args);
		if (0 < count($attachments)) {
			foreach($attachments as $attachment) {
				$att_url = wp_get_attachment_url($attachment->ID);
				if (($att_url == $image_url) || ($att_url == $image_ori_url)) {
					ngcp_debug('image found in mediathek');
					return get_attached_file($attachment->ID);
				}
			}
		}
		
		return null;
	}
	
	function should_be_synced() {
		// If the post was manually set to not be synced,
		// or nothing was set and the default is not to sync,
		// or it's private and the default is not to sync private posts, give up now
		// also publish posts with a publish date in the future. newsgrape will handle this
		
		if (
			0 == $this->options['sync'] ||
			0 == get_post_meta($this->wp_id, 'ngcp_sync', true) ||
			//('private' == $this->post_status && $this->options['privacy_private'] == 'ngcp_no') ||
			('publish' != $this->post_status && 'future' != $this->post_status)
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
