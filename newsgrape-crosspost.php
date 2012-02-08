<?php
/*
Plugin Name: Newsgrape Crossposter
Description: The Newsgrape Crosstposts automatically crossposts wordpress articles to your newsgrape account. Editing or deleting a post will be replicated as well.
Version: 1.0
Author: Stefan Kröner
Author URI: http://www.kanen.at/
*/

define('NGCP_DEBUG', true);

$ngcp_dir = dirname(__FILE__);

@require_once "$ngcp_dir/api.php";
@require_once "$ngcp_dir/controllers.php";
@require_once "$ngcp_dir/models.php";
@require_once "$ngcp_dir/ngcp-options.php";

// set default options 
function ngcp_set_defaults() {
	$options = ngcp_get_options();
	add_option( 'ngcp', $options, '', 'no' );
}
register_activation_hook(__FILE__, 'ngcp_set_defaults');

//register our settings
function register_ngcp_settings() {
	register_setting( 'ngcp', 'ngcp', 'ngcp_validate_options');
}

// when uninstalled, remove option
function ngcp_remove_options() {
	delete_option('ngcp');
	delete_option('ngcp_error_notice');
}
register_uninstall_hook( __FILE__, 'ngcp_remove_options' );


function ngcp_add_meta_box() {
    $label = __( 'Newsgrape', 'ngcp' );
    add_meta_box('newsgrape', $label, 'ngcp_inner_meta_box', null, 'side', 'high');
}

function ngcp_inner_meta_box($post) {
	global $post;
	$options = ngcp_get_options();
	
	$languages = $options['languages'];
	$licenses = $options['licenses'];
	
	$cats = wp_get_post_categories($post->ID);
	$post_meta = get_post_custom($post->ID);
	
	$ngcp_crosspost = (array_key_exists("ngcp_crosspost",$post_meta)) ? $post_meta['ngcp_crosspost'][0] : $options['crosspost'];
	$ngcp_promotional = (array_key_exists("ngcp_promotional",$post_meta)) ? $post_meta['ngcp_promotional'][0] : false;
	$ngcp_language = (array_key_exists("ngcp_language",$post_meta)) ? $post_meta['ngcp_language'][0] : $options['language'];
	$ngcp_license = (array_key_exists("ngcp_license",$post_meta)) ? $post_meta['ngcp_license'][0] : $options['license'];
	$ngcp_comments = (array_key_exists("ngcp_comments",$post_meta)) ? $post_meta['ngcp_comments'][0] : $options['comments'];
	$ngcp_type = (array_key_exists("ngcp_type",$post_meta)) ? $post_meta['ngcp_type'][0] : $options['type']["category-".$cats[0]];
	$ngcp_id = (array_key_exists("ngcp_id",$post_meta)) ? $post_meta['ngcp_id'][0] : false;
	$ngcp_display_url = (array_key_exists("ngcp_display_url",$post_meta)) ? $post_meta['ngcp_display_url'][0] : false;
?>



	<?php wp_nonce_field( 'ngcp_metabox', 'ngcp_nonce' ); ?>
	
    <div class="misc-pub-section ngcp-info">    	
		<?php if($ngcp_display_url): ?>
		    <p><a href="<?php echo $ngcp_display_url?>"><?php echo $ngcp_display_url?></a></p>
		    <?php if(NGCP_DEBUG) { echo "<p>NG ID: $ngcp_id</p>"; }?>
		<?php else: ?>
		    <em><?php _e('Not crossposted yet.', 'ngcp');?></em>
		<?php endif; ?>
	</div>
    
    
    <div class="misc-pub-section  misc-pub-section -last">
        <div class="ngcp-setting">
            <label><input type="checkbox" name="ngcp_crosspost" id="ngcp_crosspost" <?php checked($ngcp_crosspost, '1'); ?>/><?php _e('Crosspost', 'ngcp'); ?></label>
        </div>
        <div class="ngcp-setting">
            <h4><?php _e('Language', 'ngcp'); ?></h4>
            <select name="ngcp_language" id="ngcp_language">
                <?php foreach($languages as $short => $long): ?>
                    <option value="<?php echo $short?>" <?php selected($ngcp_language, $short); ?>>
                        <?php echo $long?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ngcp-setting">
            <h4><?php _e('license', 'ngcp'); ?></h4>
            <select name="ngcp_license" id="ngcp_license">
                <?php foreach($licenses as $short => $long): ?>
                    <option value="<?php echo $short?>" <?php selected($ngcp_license, $short); ?>>
                        <?php echo $long?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ngcp-setting">
            <h4><?php _e('Type', 'ngcp'); ?></h4>
            <label><input type="radio" name="ngcp_type" id="ngcp_type_opinion" value="opinion" <?php checked($ngcp_type, 'opinion'); ?>>  <?php _e('Opinion', 'ngcp'); ?></label><br />
            <label><input type="radio" name="ngcp_type" id="ngcp_type_creative" value="creative" <?php checked($ngcp_type, 'creative'); ?>>  <?php _e('Creative', 'ngcp'); ?></label><br />
        </div>
        
        <div class="ngcp-setting">
            <h4>More Settings</h4>
            <label><input type="checkbox" name="ngcp_comments" id="ngcp_comments" <?php checked($ngcp_comments, '1'); ?>>  <?php _e('Allow Comments', 'ngcp'); ?></label><br />
            <label><input type="checkbox" name="ngcp_promotional" id="ngcp_promotional" <?php checked($ngcp_promotional, '1'); ?>>  <?php _e('This is a Promotional Article', 'ngcp'); ?></label> <a href="#TB_inline?height=100&width=150&inlineId=ngcp-promotional-info&modal=true" class="thickbox">What is a promotional article?</a><div id="ngcp-promotional-info"><p><?php _e('Promotional articles have to be marked on Newsgrape, or users risk account suspendings.','ngcp'); ?><p><p style="text-align:center"><a href="#"onclick="tb_remove()" />close</a></p></div>
        </div>
    </div>

	<?php
	if(NGCP_DEBUG) {
		echo "<pre>";
		print_r( $post_meta );
		
		echo "$ngcp_crosspost\n";
		echo "$ngcp_language\n";
		echo "$ngcp_license\n";
		echo "$ngcp_type\n";
		echo "</pre>";
		
	}
	?>

	<?php
}

// ---- Style -----
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
		div.ngcp-radio-column ul li { list-style: none; padding: 0; text-indent: 0; margin-left: 0; }
		div#post-body-content div.ngcp-radio-column, div#post-body-content p.ngcp-userpics { float: left; width: 22%; margin-right: 2%; }
		div#side-info-column div.ngcp-radio-column ul { margin: 1em; }
		p.ngcp-cut-text { clear: both; }
		input#ngcp_cut_text { width: 90%; }
	</style>
<?php 
}

function ngcp_settings_css() { ?>
	<style type="text/css">
		table.editform th { text-align: left; }
		dl { margin-right: 2%; margin-top: 1em; color: #666; }
		dt { font-weight: bold; }
		#ngcp dd { font-style: italic; }
		ul#category-children { list-style: none; height: 15em; width: 20em; overflow-y: scroll; border: 1px solid #dfdfdf; padding: 0 1em; background: #fff; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; }
		ul.children { margin-left: 1.5em; }
		tr#scary-buttons { display: none; }
		#delete_all { font-weight: bold; color: #c00; }
		#ngcp-logout { margin-bottom: 30px; }
		#ngcp-advanced-options { display: none; }
		#category-children select { float: right; }
	</style>
<?php
}

function ngcp_can_replace() {
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
		return false;
	}
	
	if(0 == $allow_comments_for_this_post){
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
					$msg .= sprintf(__('Could not crosspost to Newsgrape. Please got to the <a href="%s">Newsgrape options screen</a> and enter enter your Newsgrape username and password.', 'ngcp'), 'options-general.php?page=ngcp-options.php');
					$class = 'error';
					break;
				case 'create' : 
					$msg .= sprintf(__('Could not crosspost to Newsgrape. (Error: %s)', 'ngcp'), 'options-general.php?page=ngcpoptions.php', $error );
					$class = 'error';
					break;
				case 'update' : 
					$msg .= sprintf(__('Could not crosspost the updated entry to (Error: %s)', 'ngcp'), $error );
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
		$msg = sprintf(__("Crossposted to Newsgrape.", 'ngcp')); 
	echo '<div class="'.$class.'"><p>'.$msg.'</p></div>';
	update_option('ngcp_error_notice', ''); // turn off the message
}

function ngcp_comments($file) {
	/*if ( !( is_singular() && ( have_comments() || 'open' == $post->comment_status ) ) ) {
        return;
    }*/

    if ( !ngcp_can_replace() ) {
        return $file;
    }
    
	$file = dirname( __FILE__ ) . '/comments.php';
    return $file;
}

$class = 'NGCP_Core_Controller';

add_action('admin_menu', 'ngcp_add_pages'); // Add settings menu to admin
add_action('add_meta_boxes', 'ngcp_add_meta_box'); //Add meta box
add_action('admin_head-post-new.php', 'ngcp_css');
add_action('admin_head-post.php', 'ngcp_css');
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


// Make Plugin Multilingual
load_plugin_textdomain('ngcp', false, basename($ngcp_dir.'/lang'));

?>
