<?php
/*
Plugin Name: Newsgrape Sync
Version: 1.2.3
Description: The Plugin automatically syncs wordpress articles to your newsgrape account. Editing or deleting a post will be replicated as well.
Author: Newsgrape.com, Stefan Kröner
Author URI: http://www.newsgrape.com/
*/

/* Again, the version. used in api requests in the user agent string */
define('NGCP_VERSION','1.2.3');

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



/* General Settings. No need to edit this */
define('NGCP_MAXLENGTH_DESCRIPTION', 300);
define('NGCP_MAXLENGTH_TITLE', 100);
define('NGCP_MAXWIDTH_IMAGE', 432);
define('NGCP_MAXHEIGHT_IMAGE', 800);


$ngcp_dir = dirname(__FILE__);

@require_once "$ngcp_dir/api.php";
@require_once "$ngcp_dir/controllers.php";
@require_once "$ngcp_dir/models.php";
@require_once "$ngcp_dir/metabox.php";
@require_once "$ngcp_dir/ngcp-options.php";
@require_once "$ngcp_dir/ngcp-options-fast-edit.php";
@require_once "$ngcp_dir/ngcp-help.php";

/* Activation, set default options */
function ngcp_activation() {
	$options = ngcp_get_options();
	add_option( 'ngcp', $options, '', 'no' );
	wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'sync_newsgrape_comment_count');
}
register_activation_hook(__FILE__, 'ngcp_activation');

/* Deactivation */
function ngcp_deactivation() {
	wp_clear_scheduled_hook('sync_newsgrape_comment_count');
}
register_deactivation_hook(__FILE__, 'ngcp_deactivation');

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
	    <?php include('ngcp.css');?>
	</style>
<?php 
}

/* Admin options page style */
function ngcp_settings_css() { ?>
	<style type="text/css">
		<?php include('ngcp-settings.css');?>
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
				case 'update' :
				case 'delete' :
					$msg .= sprintf(__('%s', 'ngcp'), $error );
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
			if (is_writable($path)) {
				$fp = fopen($path ,"a");
				fwrite($fp, $message."\n");
				fclose($fp);
			} else {
				error_log("Debug logfile not writable!");
			}
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
	ngcp_debug("syncing comment count!");
	$api = new NGCP_API();
	$comment_count = $api->get_comment_count();
	
	foreach ($comment_count as $id => $count) {
		update_post_meta($id, 'ngcp_comment_count', $count);
	}
}

/* Change comment count
 */
function ngcp_get_comments_number($count) {
	global $post;
	
	if (ngcp_can_replace_comments()) {
		return (float) get_post_meta($post->ID, 'ngcp_comment_count', True);
	}
	return $count;
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
add_filter('get_comments_number', 'ngcp_get_comments_number');
add_action('sync_newsgrape_comment_count', 'ngcp_sync_comment_count');

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

// if no comment count has been synced yet maybe the schedular doesn't know about it?
if(!get_option('ngcp_lastcommentsync')) {
	wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'sync_newsgrape_comment_count');
}

// Make Plugin Multilingual
load_plugin_textdomain('ngcp', false, basename($ngcp_dir.'/lang'));

?>
