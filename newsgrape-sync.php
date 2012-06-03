<?php
/*
Plugin Name: Newsgrape Sync
Version: 1.0
Description: The Plugin automatically syncs wordpress articles to your newsgrape account. Editing or deleting a post will be replicated as well.
Author: Newsgrape.com, Stefan Kröner
Author URI: http://www.kanen.at/
*/

/* NGCP_DEBUG true enables:
 * - Various Debug messages in logfile
 * - Raw dump of options and blog id on options page
 * - Buttons to delete options and blog id on options page
 * - Display multiuser API keys on options page
 * - Raw dump of article meta in metabox
 */
define('NGCP_DEBUG', false);

/* NGCP_DEBUG_FILE enables logging to "debug.log" in plugin folder
 * if this is set to false, debug messages go to the webservers error log
 */
define('NGCP_DEBUG_FILE', true);

/* NGCP_DEV enables the staging server for API and comments system*/
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
@require_once "$ngcp_dir/ngcp-help.php";

/* Set default options */
function ngcp_set_defaults() {
	$options = ngcp_get_options();
	add_option( 'ngcp', $options, '', 'no' );
	wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'sync_newsgrape_comment_count');
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
		#post-body-content #newsgrape {
		}
		#post-body-content #newsgrape .inside {
			height: 100%;
		}
		#post-body-content #newsgrape .inside .misc-pub-section {
			min-height: 77px;
			height: 100%;
		}
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
		#newsgrape {
			background: #fcfcfa ;
		}
		#newsgrape h3 {
			background: url("<?php echo ngcp_plugin_dir_url(); ?>menu_icon_inverted.png") 5px 6px no-repeat #efefef;
			font-family: Arial, Helvetica, sans-serif;
			color: #444;
			text-shadow: none;
			font-weight: bold;
			padding-left: 22px;
		}
		#newsgrape h3 em {
			color: #e53d2e;
			font-style: normal;
		}
		#newsgrape .ngcp-info {
			background: #fff;
			color: #a6a6a1;
			border: none;
		}
		#newsgrape .ngcp-info.synced {
			background: #c5e3b6;
			color: #444;
			border-top: 1px solid #fff;
		}
		#newsgrape #ng-type-select label {
			width: 62px;
			float: left;
		}
		#newsgrape #ng-type-select select {
			width: 192px;
		}
		#newsgrape .inside {
			margin: 0;
			padding: 0;
		}
		#newsgrape #ng-sync-option {
			background: #e0e0d7;
		}
		#newsgrape #ng-sync-option input {
			margin-right: 5px;
		}
		#ng-more-sync-options #ngcp_more_inner {
			margin-top: 15px;
		}
		#ngcp-license {
			border-top: 1px solid #ddd;
		}
		#ngcp-promotional-info {
			display: none;
		}
		.ngcp-info p {
			margin-top: 3px;
			margin-bottom: 3px;
		}
		.ngcp-info a {
			word-break: break-word;
		}
		.ngcp-info .on-newsgrape {
			display: block;
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
		#wpwrap {
			background: url("<?php echo ngcp_plugin_dir_url(); ?>furley_bg.png");
		}
		#adminmenuback {
			z-index: 1;
		}
		#adminmenuwrap {
			z-index:2;
		}
		#wpbody-content > .wrap .error {
			display: none;
		}
		#wpbody-content > .wrap {
			background-color: #fff;
			padding: 15px;
			margin: 40px 35px 20px 20px;
			border-radius: 12px;-moz-border-radius: 12px;-webkit-border-radius: 12px;
			border: 2px solid #e0e0d7;
			color: #444;
		}
		#wpbody-content > .wrap input[type=text],#wpbody-content > .wrap select,#wpbody-content > .wrap textarea {
		    margin-bottom: 4px;
		    padding: 4px;
		    color: #444;
		    font-size: 15px;
		    border: 1px solid #ebebe7;
		    border-radius: 4px;-moz-border-radius: 4px;-webkit-border-radius: 4px;
		    background-color: #fcfcfa;
		    outline: 0;
		}
		#wpbody-content > .wrap input[readonly="readyonly"] {
			background-color: #fcfcfa;
			color: #a6a6a1;
		}
		#wpbody-content > .wrap .button-primary, #wpbody-content > .wrap .button-secondary, #wpbody-content > .wrap .button-quiet {
		    position: relative;
		    display: inline-block;
		    font-size: 14px;
		    color: #fcfcfa !important;
		    margin: 0;
		    padding: 5px 8px 5px;
		    border: 0 !important;
		    border-radius: 4px;-moz-border-radius: 4px;-webkit-border-radius: 4px;
		    text-align: left;
		    font-weight: bold;
		    cursor:pointer;
			text-shadow: none !important;
			background-image: none;
		}
		#wpbody-content > .wrap .button-primary:hover, #wpbody-content > .wrap .button-secondary:hover, #wpbody-content > .wrap .button-quiet:hover {
			color: 
			white !important;
			background-image: -webkit-gradient(linear, 50% 0%, 50% 100%, color-stop(0%, 
			#444), color-stop(100%, 
			#333)) !important;
			background-image: -webkit-linear-gradient(
			#444,
			#333) !important;
			background-image: -moz-linear-gradient(
			#444,
			#333) !important;
			background-image: -o-linear-gradient(
			#444,
			#333) !important;
			background-image: -ms-linear-gradient(
			#444,
			#333) !important;
			background-image: linear-gradient(
			#444,
			#333) !important;
			background-color: 
			#444 !important;
		}
		#wpbody-content > .wrap .button-secondary {
			background-image: -webkit-gradient(linear, 50% 0%, 50% 100%, color-stop(0%, 
			#3E97C5), color-stop(100%, 
			#3689B3)) !important;
			background-image: -webkit-linear-gradient(
			#3E97C5,
			#3689B3) !important;
			background-image: -moz-linear-gradient(
			#3E97C5,
			#3689B3) !important;
			background-image: -o-linear-gradient(
			#3E97C5,
			#3689B3) !important;
			background-image: -ms-linear-gradient(
			#3E97C5,
			#3689B3) !important;
			background-image: linear-gradient(
			#3E97C5,
			#3689B3) !important;
			background-color: 
			#3689B3 !important;		
		}
		#wpbody-content > .wrap .button-primary {
			background-image: -webkit-gradient(linear, 50% 0%, 50% 100%, color-stop(0%, 
			#E85245), color-stop(100%, 
			#E53D2E)) !important;
			background-image: -webkit-linear-gradient(
			#E85245,
			#E53D2E) !important;
			background-image: -moz-linear-gradient(
			#E85245,
			#E53D2E) !important;
			background-image: -o-linear-gradient(
			#E85245,
			#E53D2E) !important;
			background-image: -ms-linear-gradient(
			#E85245,
			#E53D2E) !important;
			background-image: linear-gradient(
			#E85245,
			#E53D2E) !important;
			background-color: 
			#E53D2E !important;
		}
		#wpbody-content > .wrap .button-quiet {
			background-image: -webkit-gradient(linear, 50% 0%, 50% 100%, color-stop(0%, 
			#BFBFBB), color-stop(100%, 
			#B2B2AE)) !important;
			background-image: -webkit-linear-gradient(
			#BFBFBB,
			#B2B2AE) !important;
			background-image: -moz-linear-gradient(
			#BFBFBB,
			#B2B2AE) !important;
			background-image: -o-linear-gradient(
			#BFBFBB,
			#B2B2AE) !important;
			background-image: -ms-linear-gradient(
			#BFBFBB,
			#B2B2AE) !important;
			background-image: linear-gradient(
			#BFBFBB,
			#B2B2AE) !important;
			background-color: 
			#B2B2AE !important;		
		}
		input[type=submit].ng-button-link {
			background: none !important;
			border: none !important;
			cursor: pointer;
		}
		#wpbody-content > .wrap a, .ng-button-link {
			color: #3689b3 !important;
		}
		#wpbody-content > .wrap a:hover, input[type=submit].ng-button-link:hover {
			color: #444 !important;
		}
		.ng-connect-box {
			background-color: #ebebe7;
			border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; 
			margin: 12px 0px;
		}
		.ng-small {
			font-size: 10px !important;
		}
		.ng-success-bg {
			background-color: #c5e3b6 !important;
		}
		.toplevel_page_newsgrape {
			background: #e53d2e !important;
			text-shadow: 0 !important;
			border-color: #e53d2e !important;
			text-shadow: none !important;
		}
		#toplevel_page_newsgrape .wp-menu-arrow, #toplevel_page_newsgrape .wp-menu-arrow div  {
			background: #e53d2e !important;
		}
		.wp-menu-arrow {
		}
		table.editform th { text-align: left; }
		dl { margin-right: 2%; margin-top: 1em; color: #666; }
		dt { font-weight: bold; }
		#ngcp dd { font-style: italic; }
		ul#category-children {
			list-style: none;
			height: auto;
			width: 38em;
			max-width: 100%;
			overflow-y: auto;
			border: 1px solid #e0e0d7;
			padding: 1em;
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
		.ng-edit-box {
			background: #fafafa;
			padding: 18px;
			margin: 0 0 12px 0;
			border: 1px solid #e0e0d7;
			border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; 
		}
		#ngcp-fast-edit {
		}
		
		#ngcp-fast-edit-button {
			float: right;
		}
		.ng-connected-button {
			max-width: 620px;
			margin-bottom: 15px;
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
					$msg .= sprintf(__('Could not sync. Error: %s', 'ngcp'), 'options-general.php?page=ngcpoptions.php', $error );
					$class = 'error';
					break;
				case 'update' :
					$msg .= sprintf(__('Could not sync the updated entry. Error: %s', 'ngcp'), $error );
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
	if(!ngcp_is_current_user_connected() && current_user_can('manage_options')) {
		echo "\n<div class='error'><p>";
		_e('<strong>Newsgrape:</strong> Enter your Newsgrape username and password to start syncing to Newsgrape: <a href="admin.php?page=newsgrape">go to Newsgrape Settings</a>', 'ngcp');
		echo "</p></div>";
	}
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
			echo "\nCanonical: ".$options['canonical'];
			echo "\nArticle Sync: ".get_post_meta($id, 'ngcp_sync',true)==1;
			echo "\nNewsgrape URL: ".$ngcp_display_url;
			echo "\n-->\n";
		}
		if( 1==$options['sync'] &&
			1==$options['canonical'] &&
		    0!==get_post_meta($id, 'ngcp_sync',true) &&
		    ""!=$ngcp_display_url &&
		    NULL!=$ngcp_display_url){
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
 * returns True if user is connected
 * returns False if newsgrape is not connected
 */
function ngcp_is_current_user_connected() {
	$options = ngcp_get_options();
	
	if (isset($options['api_key']) && '' != $options['api_key']) {
		return True;
	}
	
	return False;
}

/* Schedule event to update comment count for all articles
 */
function ngcp_sync_comment_count() {
	$api = new NGCP_API();
	$comment_count = $api->get_comment_count();
	
	foreach ($comment_count as $id => $count) {
		update_post_meta($id, 'ngcp_comment_count', $count);
	}
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
add_action('sync_newsgrape_comment_count', 'ngcp_sync_comment_count');

function my_activation() {
	wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'my_hourly_event');
}



// Inform user that he needs to enter his newsgrape credentials
add_action('admin_notices', 'ngcp_print_login_notice');

// enable http logging
if(NGCP_DEBUG) {
	add_filter('pre_http_request', 'ngcp_log_http', 10, 3);
	add_filter('http_request_args', 'ngcp_log_http', 10, 2 );
	add_action('http_api_debug', 'ngcp_log_http', 10, 3);
}

// make sure that there is a blog id. we need it for the comment system.
if(!get_option('ngcp_blog_id')) {
	ngcp_debug("generate blog id");
	update_option('ngcp_blog_id',ngcp_random(24));
}

// Make Plugin Multilingual
load_plugin_textdomain('ngcp', false, basename($ngcp_dir.'/lang'));

?>
