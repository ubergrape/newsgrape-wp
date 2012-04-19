<?php
/*
Plugin Name: Newsgrape Sync
Version: 0.1
Description: The Plugin automatically syncs wordpress articles to your newsgrape account. Editing or deleting a post will be replicated as well.
Author: Newsgrape.com, Stefan Kröner
Author URI: http://www.kanen.at/
*/

define('NGCP_DEBUG', false);
define('NGCP_DEBUG_FILE', true);
define('NGCP_DEV', false);

/* Set this to the plugin dir name if you have symlink problems */
//define('NGCP_PLUGIN_DIR','newsgrape-sync');

$ngcp_dir = dirname(__FILE__);

@require_once "$ngcp_dir/api.php";
@require_once "$ngcp_dir/controllers.php";
@require_once "$ngcp_dir/models.php";
@require_once "$ngcp_dir/metabox.php";
@require_once "$ngcp_dir/ngcp-options.php";
@require_once "$ngcp_dir/ngcp-options-fast-edit.php";

/* Set default options */
function ngcp_set_defaults() {
	$options = ngcp_get_options();
	add_option( 'ngcp', $options, '', 'no' );
}
register_activation_hook(__FILE__, 'ngcp_set_defaults');

/* Register settings */
function register_ngcp_settings() {
	register_setting( 'ngcp', 'ngcp', 'ngcp_validate_options');
	register_setting( 'ngcp_fe', 'ngcp_fe', 'ngcp_validate_fe_options');
}

/* When uninstalled, remove option */
function ngcp_remove_options() {
	delete_option('ngcp');
	delete_option('ngcp_error_notice');
}
register_uninstall_hook( __FILE__, 'ngcp_remove_options' );

/* Metabox Style */
function ngcp_css() { ?>
	<style type="text/css">
		.ngcp-section:first-child {
			border-top-width: 0;
		}
		.ngcp-section {
			border-top-color: white;
			border-bottom-color: #DFDFDF;
		}
		.ngcp-section-last {
			border-bottom-width: 0;
		}
		#newsgrape .inside {
			margin: 0;
			padding: 0;
		}
		#ngcp-promotional-info {
			display: none;
		}
		.ngcp-info a {
			word-break: break-word;
		}
		.ngcp-info .on-newsgrape {
			display: block;
		}
		.ngcp-info synced{
			background: #F8FFF7;
		}
		div.ngcp-radio-column ul li { list-style: none; padding: 0; text-indent: 0; margin-left: 0; }
		div#post-body-content div.ngcp-radio-column, div#post-body-content p.ngcp-userpics { float: left; width: 22%; margin-right: 2%; }
		div#side-info-column div.ngcp-radio-column ul { margin: 1em; }
		#ngcp_license {
			max-width: 100%;
		}
		
		#newsgrape_description {
			border: none;
			background: none;
			margin-top: 16px;
		}
		#newsgrape_description .inside {
			padding: 0;
		}
		
		#newsgrape_description h3, #newsgrape_description .handlediv {
			display: none;
		}
	
		#ngcp_description {
			border-color: #CCC;
			background-color: white;
			padding: 3px 8px;
			font-size: 1.7em;
			line-height: 100%;
			width: 100%;
			outline: none;
		}
		
		#ngcp_description-prompt-text {
			color: #BBB;
			position: absolute;
			font-size: 1.7em;
			padding: 8px 10px;
			cursor: text;
			vertical-align: middle;
		}

	</style>
<?php 
}

/* Admin options page style */
function ngcp_settings_css() { ?>
	<style type="text/css">
		table.editform th { text-align: left; }
		dl { margin-right: 2%; margin-top: 1em; color: #666; }
		dt { font-weight: bold; }
		#ngcp dd { font-style: italic; }
		ul#category-children {
			list-style: none;
			height: 15em;
			width: 38em;
			overflow-y: scroll;
			border: 1px solid #dfdfdf;
			padding: 0 1em;
			background: #fff;
			border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; 
		}
		#category-children li {
			clear: both;
		}
		ul.children { margin-left: 1.5em; }
		tr#scary-buttons { display: none; }
		#delete_all { font-weight: bold; color: #c00; }
		#ngcp-logout { margin-bottom: 30px; }
		#ngcp-advanced-options { /*display: none;*/ }
		#category-children select { float: right; }
		#ngcp-fast-edit {
			width: 450px;
			background: #fafafa;
			padding: 18px;
			margin-bottom: 20px;
			margin-top: 20px;
		}
		
		#ngcp-fast-edit-button {
			float: right;
		}
		.ngcp-all-articles {
			margin-top: 20px;
		}
		.ngcp-all-articles td{
			padding: 6px;
		}
		.ngcp-all-articles .post-edit-link {
			border-right: 1px solid #8f8f8f;
			padding-right: 12px;
		}
		.ngcp-all-articles .ngcp-the-date {
			padding-left: 8px;
			padding-right: 5px;
			color: #8f8f8f;
			font-size: 10px;
		}
		.ngcp-all-articles .ngcp-the-title {
			padding-right: 8px;
			font-weight: bold;
			display: block;
		}
		.ngcp-all-articles .ngcp-has-no-type {
			background: #f8f8f8;
		}
		.ngcp-all-articles th.type {
			min-width: 326px;
		}
		.ngcp-sync-state {
			color: #8f8f8f;
			font-size: 10px;
		}
		.ngcp-all-articles tr.ngcp-synced {
			background: #E7F7D3;
		}
		.ngcp-sync-state.ngcp-synced {
			color: green;
		}
		.ngcp-edit-all {
			color: #fff;
			background: #8f8f8f;
		}
		.ngcp-hidden {
			visibility: hidden;
		}
		#ngcp-cat-select {
			margin-bottom: 10px;
		}
		.options h3 {
			padding-top: 20px;
		}
		#ngcp_header_img {
			border-radius: 15px;
			max-width: 100%;
			height: auto;
		}
		#setting-error-ngcp pre {
			font-weight: normal;
			font-size: 10px;
		}
		#ngcp-user-table {
			width: 585px;
			margin-bottom: 10px;
			margin-top: 10px;
		}
		#ngcp-user-table td {
			height: 18px;
		}
	</style>
<?php
}

/* Adds optional description/intro text to the post content */
function ngcp_add_description_to_content($content) {
	global $id, $post;
	
	$description = get_post_meta($id, 'ngcp_description', true);

	if('' != $description) {
		return '<p class="ng_intro"><strong>'.$description.'</strong></p>'.$content;
	}
	return $content;
}

/* Determines if the Newsgrape plugin should replace the default
 * WordPress comment system.
 * The Newsgrape comment system can be (de)activated globally on the
 * options page or individually in the metabox
 */
function ngcp_can_replace_comments() {
	global $id, $post;
	
	$options = ngcp_get_options();
	
	if(is_feed()){
		ngcp_report(__FUNCTION__,"is feed");
		return false;
	}
	
    if('draft' == $post->post_status){
		ngcp_report(__FUNCTION__,"draft");
		return false;
	}
	
	$allow_comments_global = $options['comments'];
	$allow_comments_for_this_post = get_post_meta($id, 'ngcp_comments', true);
	
	if(0 == $allow_comments_global){
		ngcp_report(__FUNCTION__,"global");
		return false;
	}
	
	if(0 == $allow_comments_for_this_post){
		ngcp_report(__FUNCTION__,"post");
		return false;
	}
	
	return true;
}

function ngcp_report($function_name, $message) {
	if(NGCP_DEBUG) {
		error_log("NGCP ($function_name): $message");
	}
}

function ngcp_error_notice() {
	$errors = get_option('ngcp_error_notice');
	if (!empty($errors)) { 
    	add_action('admin_notices', 'ngcp_print_notices');
	}
}

function ngcp_print_notices() {
	$errors = get_option('ngcp_error_notice');
	$class = 'updated';
	if (!empty($errors) && isset($_GET['action']) && $_GET['action'] == 'edit') { // show this only after we've posted something
		foreach ($errors as $code => $error) {
			$code = trim( (string)$code);
			switch ($code) {
				case 'no_api_key' :
					$msg .= sprintf(__('Could not sync. Please got to the <a href="%s">Newsgrape options screen</a> and enter enter your Newsgrape username and password.', 'ngcp'), 'options-general.php?page=ngcp-options.php');
					$class = 'error';
					break;
				case 'create' : 
					$msg .= sprintf(__('Could not sync. (Error: %s)', 'ngcp'), 'options-general.php?page=ngcpoptions.php', $error );
					$class = 'error';
					break;
				case 'update' : 
					$msg .= sprintf(__('Could not sync the updated entry. (Error: %s)', 'ngcp'), $error );
					$class = 'error';
					break;
				default: 
					$msg .= sprintf(__('Error (%s): %s', 'ngcp'), $code, $error );
					$class = 'error';
					break;
			}
		}
	}
	if ($class == 'updated') // still good?
		$msg = sprintf(__("Synced to Newsgrape.", 'ngcp')); 
	echo "<div class='$class'><p><strong>Newsgrape:</strong> $msg</p></div>";
	update_option('ngcp_error_notice', ''); // turn off the message
}

function ngcp_print_login_notice() {
	echo "\n<div class='error'><p>";
	_e('<strong>Newsgrape:</strong> Enter your Newsgrape username and password to start syncing to Newsgrape: <a href="admin.php?page=newsgrape">go to Newsgrape Settings</a>', 'ngcp');
	echo "</p></div>";
}

/* Prints out a <link rel='canonical'> pointing to the corresponing
 * Newsgrape article
 */
function ngcp_rel_canonical() {
	global $posts;
	
	if ( is_single() || is_page() ) {
		$id = $posts[0]->ID;
		$options = ngcp_get_options();
		$ngcp_display_url = get_post_meta($id, 'ngcp_display_url',true);
		
		if (NGCP_DEBUG) {
			echo "\n<!-- Newsgrape Sync Debug Information";
			echo "\nGlobal Sync: ".$options['sync'];
			echo "\nArticle Sync: ".get_post_meta($id, 'ngcp_sync',true)==1;
			echo "\nNewsgrape URL: ".$ngcp_display_url;
			echo "-->";
		}
		if( 1==$options['sync'] &&
		    0!==get_post_meta($id, 'ngcp_sync',true) &&
		    ""!=$ngcp_display_url &&
		    NULL!=$ngcp_display_url ){
				echo "\n<link rel='canonical' href='$ngcp_display_url'><!-- Newsgrape Sync-->\n";
				return;
		}
	}
	
	rel_canonical();
}

/* Newsgrape comment sysem */
function ngcp_comments($file) {
	/*if ( !( is_singular() && ( have_comments() || 'open' == $post->comment_status ) ) ) {
        return;
    }*/

    if ( !ngcp_can_replace_comments() ) {
        return $file;
    }
    
	$file = dirname( __FILE__ ) . '/comments.php';
    return $file;
}

/* Log Newsgrape debug messages if NGCP_DEBUG is set.
 * Writes to debug.log in plugin directory if NGCP_DEBUG_FILE is set
 */
function ngcp_debug($message) {
	if(NGCP_DEBUG) {
		if(NGCP_DEBUG_FILE) {
			$path = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'debug.log';
			$fp = fopen($path ,"a");
			if(false==$fp) die("Debug file $path not writable!");
			fwrite($fp, $message."\n");
			fclose($fp);
		} else {
			error_log($message);
		}
	}
}

/* Generates a random string, containing upper- and lowercase
 * characters, numbers, "." and "-".
 * This is used to generate the unique blog id
 */
function ngcp_random($len) { 
    if (@is_readable('/dev/urandom')) { 
        $f=fopen('/dev/urandom', 'r'); 
        $urandom=fread($f, $len); 
        fclose($f); 
    } 

    $return=''; 
    for ($i=0;$i<$len;++$i) { 
        if (!isset($urandom)) { 
            if ($i%2==0) mt_srand(time()%2147 * 1000000 + (double)microtime() * 1000000); 
            $rand=48+mt_rand()%64; 
        } else $rand=48+ord($urandom[$i])%64; 

        if ($rand>57) 
            $rand+=7; 
        if ($rand>90) 
            $rand+=6; 

        if ($rand==123) $rand=45; 
        if ($rand==124) $rand=46; 
        $return.=chr($rand); 
    } 
    return $return; 
}

/* Returns the url for the plugin directory (with trailing slash)
 * Workaround for symlinked plugin directories
 * see:
 * http://core.trac.wordpress.org/ticket/16953
 * https://bugs.php.net/bug.php?id=46260
 */
function ngcp_plugin_dir_url() {
	if (defined('NGCP_PLUGIN_DIR')) {
		return plugins_url(NGCP_PLUGIN_DIR) . '/';
	}
	return plugins_url(basename(dirname(__FILE__))) . '/';
}

function ngcp_log_http($data = '', $log_type = '', $extra = '') {
	$data_readable = print_r($data,true);
	if (strlen($data_readable)>3000){
		$data_readable = substr($data_readable,0,3000)." ... (truncated after 3000 characters)\n";
	}
	$message = 'HTTP ('.current_filter().'): '.$data_readable;
	ngcp_debug($message);
	return $data;
}

/* Finds out if current user is connected.
 * returns 'multi' if multiuser is activated and current user is connected
 * returns 'single' if multiuser is deactiveated and user is connected
 * returns False if newsgrape is not connected
 */
function ngcp_is_current_user_connected() {
	$options = ngcp_get_options();
	if('multi' == $options['multiuser']) {
		$user_meta = ngcp_user_meta();
		if ($user_meta && array_key_exists('api_key',$user_meta)) {
			return 'multi';
		}
	} elseif (isset($options['api_key']) && '' != $options['api_key']) {
		return 'single';
	}
	
	return False;
}

function ngcp_user_meta() {
	require_once(ABSPATH . WPINC . '/pluggable.php');
	global $current_user;
	get_currentuserinfo();
	return get_user_meta($current_user->ID, 'ngcp', True);
}

$class = 'NGCP_Core_Controller';

add_action('admin_menu', 'ngcp_add_menu'); // Add menu to admin
add_action('add_meta_boxes', 'ngcp_add_meta_box'); //Add meta box
add_action('admin_head-post-new.php', 'ngcp_css');
add_action('admin_head-post.php', 'ngcp_css');
add_action('admin_enqueue_scripts', 'ngcp_metabox_js');
add_action('publish_post', array($class,'post'));
add_action('publish_future_post', array($class,'post'));
add_action('draft_to_private', array($class,'post'));
add_action('new_to_private', array($class,'post'));
add_action('pending_to_private', array($class,'post'));
add_action('private_to_public', array($class,'edit'));
add_action('private_to_password', array($class,'edit'));
add_action('untrashed_post', array($class,'edit'));
add_action('edit_post', array($class,'edit'));
add_action('delete_post', array($class,'delete'));
add_action('save_post', array($class,'save'));
add_action('admin_head-post.php', 'ngcp_error_notice');
add_action('admin_head-post-new.php', 'ngcp_error_notice');
add_filter('comments_template', 'ngcp_comments');
remove_action('wp_head', 'rel_canonical');
add_action('wp_head', 'ngcp_rel_canonical');
add_filter('the_content', 'ngcp_add_description_to_content', 30);



// Inform user that he needs to enter his newsgrape credentials
if(!ngcp_is_current_user_connected()) {
	add_action('admin_notices', 'ngcp_print_login_notice');
}

// enable http logging
if(NGCP_DEBUG) {
	add_filter('pre_http_request', 'ngcp_log_http', 10, 3);
	add_filter('http_request_args', 'ngcp_log_http', 10, 2 );
	add_action('http_api_debug', 'ngcp_log_http', 10, 3);
}

// Make Plugin Multilingual
load_plugin_textdomain('ngcp', false, basename($ngcp_dir.'/lang'));

?>
