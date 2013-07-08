<?php

function ngcp_get_options() {
	// set defaults
	$defaults = array(
			'username'			=> '',
			'api_key'			=> '',
			'sync'				=> 1,
			'published_old'		=> 0,
			'privacy'			=> 'public',
			'privacy_private'	=> 'ngcp_no',
			'comments'			=> 1,
			'excerpt'			=> 1,
			'canonical'			=> 1,
			'tag'				=> '2',
			'more'				=> 'link',
			'skip_cats'			=> array(),
			'type'				=> array(),
			'header_loc'		=> 0,		// 0 means top, 1 means bottom
			'languages'			=> array(),
			'language'			=> substr(get_bloginfo('language'),0,2),
			'licenses'			=> array(),
			'categories'		=> array(),
			'ng_category'		=> array(),
			'license'			=> '1',
			'sync_pages'		=> 0,
	);

	$options = get_option('ngcp');
	if (!is_array($options)) $options = array();

	// still need to get the defaults for the new settings, so we'll merge again
	return array_merge( $defaults, $options );
}

?>
