<?php
/*
Plugin Name: Newsgrape Sync
Version: 1.6
Description: The Plugin automatically syncs wordpress articles to your newsgrape account. Editing or deleting a post will be replicated as well.
Author: Newsgrape.com, Stefan Kröner
Author URI: http://www.newsgrape.com/
*/

/* Again, the version. used in api requests in the user agent string */
define('NGCP_VERSION','1.6');

/* NGCP_DEBUG true enables:
 * - Various Debug messages in logfile
 * - Raw dump of options and blog id on options page
 * - Buttons to delete options and blog id on options page
 * - Display multiuser API keys on options page
 * - Raw dump of article meta in metabox
 * - mark articles as test articles
 */
define('NGCP_DEBUG', true);

/* NGCP_DEBUG_FILE enables logging to "debug.log" in plugin folder
 * if this is set to false, debug messages go to the webservers error log
 */
define('NGCP_DEBUG_FILE', true);

/* NGCP_DEV enables the development server for API and comments system*/
define('NGCP_DEV', false);
define('NGCP_DEV_SERVER', 'http://staging.newsgrape.com');

/* Set this to the plugin dir name if you have symlink problems */
define('NGCP_PLUGIN_DIR','newsgrape-sync');



/* General Settings. No need to edit this */
define('NGCP_MAXLENGTH_DESCRIPTION', 300);
define('NGCP_MAXLENGTH_TITLE', 100);
define('NGCP_MAXWIDTH_IMAGE', 432);
define('NGCP_MAXHEIGHT_IMAGE', 800);



$ngcp_dir = dirname(__FILE__);
@require_once "$ngcp_dir/metabox.php";
@require_once "$ngcp_dir/ngcp-options.php";

// Newsgrape.com has been discontinued

add_action('admin_notices', 'ngcp_print_eol_notice');

function ngcp_print_eol_notice() {
    echo "<div class='updated' style='background-image: url(" . ngcp_plugin_dir_url() . "img/hiRobot.png); background-repeat: no-repeat; background-position-x: 10px; padding-left: 60px;'><p><strong>Newsgrape.com has been shut down.</strong> You cannot sync or unsync posts anymore.</p><p>For more information, visit <a href='http://www.newsgrape.com/'>Newsgrape.com</a>. You can download all your old articles on Newsgrape.com if you are logged in.</p><p>Please go to <a href='plugins.php'>Plugins</a>, deactivate and delete <i>Newsgrape Sync</i> to remove this message.<br />Before your remove <i>Newsgrape Sync</i> make sure that you have copied all your article intros to your content.<br/>Seperate article intros were a Newsgrape feature and they will be deleted when you delete the plugin.</p></div>";
}


/* Deactivation */
function ngcp_deactivation() {
	wp_clear_scheduled_hook('sync_newsgrape_comment_count');
}
register_deactivation_hook(__FILE__, 'ngcp_deactivation');

/* When uninstalled, remove option */
function ngcp_remove_options() {
	delete_option('ngcp');
	delete_option('ngcp_error_notice');
	delete_option('ngcp_lastcommentsync');
	delete_option('trending_notice_ignore');
}
register_uninstall_hook( __FILE__, 'ngcp_remove_options' );

/* Admin options page style */
function ngcp_settings_css() { ?>
	<style type="text/css">
		<?php include('css/settings.css');?>
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


function ngcp_get_the_excerpt($excerpt){
	global $id, $post;

	$description = get_post_meta($id, 'ngcp_description', true);
    ngcp_debug('descritipon '.$description);
	if('' != $description) {
        $excerpt_more = apply_filters('excerpt_more', ' ' . '[...]');
		return $description.$excerpt_more;
	}
	return $excerpt;

}

add_filter('the_content', 'ngcp_add_description_to_content', 30);
add_filter('get_the_excerpt', 'ngcp_get_the_excerpt');
add_action('add_meta_boxes', 'ngcp_add_meta_box'); //Add meta box
add_action('admin_enqueue_scripts', 'ngcp_metabox_js');

// Make Plugin Multilingual
load_plugin_textdomain('ngcp', false, basename($ngcp_dir.'/lang'));

?>
