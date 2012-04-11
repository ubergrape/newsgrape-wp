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
@require_once "$ngcp_dir/ngcp-options.php";
@require_once "$ngcp_dir/ngcp-options-fast-edit.php";

// set default options 
function ngcp_set_defaults() {
	$options = ngcp_get_options();
	add_option( 'ngcp', $options, '', 'no' );
}
register_activation_hook(__FILE__, 'ngcp_set_defaults');

//register our settings
function register_ngcp_settings() {
	register_setting( 'ngcp', 'ngcp', 'ngcp_validate_options');
	register_setting( 'ngcp_fe', 'ngcp_fe', 'ngcp_validate_fe_options');
}

// when uninstalled, remove option
function ngcp_remove_options() {
	delete_option('ngcp');
	delete_option('ngcp_error_notice');
}
register_uninstall_hook( __FILE__, 'ngcp_remove_options' );


function ngcp_add_meta_box() {
	/* only show meta box when connected to newsgrape */
	$options = ngcp_get_options();
	if (empty($options['api_key'])) {
		return;
	}
	
    $label = __( 'Newsgrape', 'ngcp' );
    add_meta_box('newsgrape', $label, 'ngcp_inner_meta_box', null, 'side', 'high');
    
    $label = __( 'Newsgrape Article Intro', 'ngcp' );
    add_meta_box('newsgrape_description', $label, 'ngcp_inner_meta_box_description', null, 'normal', 'high');
}

function ngcp_inner_meta_box_description($post) {
	global $post;
	$ngcp_description = get_post_meta($post->ID, 'ngcp_description', true);
?>
	<div id="newsgrape_description_inner">
	<label class="hide-if-no-js" style="<?php if ($ngcp_description!='') echo 'visibility:hidden'; ?>" id="ngcp_description-prompt-text" for="ngcp_description"><?php _e('Enter Newsgrape article intro here','ngcp'); ?></label>
	<input type="text" name="ngcp_description" size="30" tabindex="2" value="<?php echo $ngcp_description; ?>" id="ngcp_description" autocomplete="off">
	</div>

<?php
	
}

function ngcp_inner_meta_box($post) {
	global $post;
	$options = ngcp_get_options();
	
	$categories = $options['categories'];
	
	$languages = $options['languages'];
	$licenses = $options['licenses'];
	
	$cats = wp_get_post_categories($post->ID);
	$post_meta = get_post_custom($post->ID);
	
	$ngcp_promotional = (array_key_exists("ngcp_promotional",$post_meta)) ? $post_meta['ngcp_promotional'][0] : false;
	$ngcp_language = (array_key_exists("ngcp_language",$post_meta)) ? $post_meta['ngcp_language'][0] : $options['language'];
	$ngcp_license = (array_key_exists("ngcp_license",$post_meta)) ? $post_meta['ngcp_license'][0] : $options['license'];
	$ngcp_comments = (array_key_exists("ngcp_comments",$post_meta)) ? $post_meta['ngcp_comments'][0] : $options['comments'];
	$ngcp_id = (array_key_exists("ngcp_id",$post_meta)) ? $post_meta['ngcp_id'][0] : false;
	$ngcp_category = (array_key_exists("ngcp_category",$post_meta)) ? $post_meta['ngcp_category'][0] : false;
	$ngcp_display_url = (array_key_exists("ngcp_display_url",$post_meta)) ? $post_meta['ngcp_display_url'][0] : false;
	$is_synced = isset($post_meta['ngcp_id']) && $post_meta['ngcp_id'][0] != 0 && (!isset($post_meta['ngcp_deleted']) || False == $post_meta['ngcp_deleted']);
	$has_been_deleted = array_key_exists("ngcp_deleted",$post_meta) && True == $post_meta['ngcp_deleted'][0];
	
	if (!array_key_exists("ngcp_sync",$post_meta)) {
		if('published' != get_post_status($post->ID) && 'auto-draft' != get_post_status($post->ID) && !$ngcp_id) {
			$ngcp_sync = 0;
		} else {
			$ngcp_sync = $options['sync'];
		}
	} else {
			$ngcp_sync = $post_meta['ngcp_sync'][0];
	}
	
	if (!array_key_exists("ngcp_type",$post_meta)) {
		if (sizeof($cats) >= 1) {
			$ngcp_type = $options['type']["category-".$cats[0]];
		} else {
			$ngcp_type = 'opinion';
		}
	} else {
			$ngcp_type = $post_meta['ngcp_type'][0];
	}
	
?>

	<?php wp_nonce_field( 'ngcp_metabox', 'ngcp_nonce' ); ?>
	
    <div class="misc-pub-section ngcp-info <?php if($is_synced) { echo "synced"; } ?>">    	
		<?php if($is_synced): ?>
		    <p><strong class="on-newsgrape">On Newsgrape: </strong><a href="<?php echo $ngcp_display_url?>"><?php echo substr($ngcp_display_url,7); ?></a></p>
		    <?php if(NGCP_DEBUG) { echo "<p>NG ID: $ngcp_id</p>"; }?>
		<?php elseif($has_been_deleted): ?>
		    <em><?php _e('Not synced anymore. (has been deleted)', 'ngcp');?></em>
		<?php else: ?>
			<em><?php _e('Not synced yet.', 'ngcp');?></em>
		<?php endif; ?>
	</div>
	
	<div class="misc-pub-section">
		<div class="ngcp-setting">
            <label><input type="checkbox" name="ngcp_sync" id="ngcp_sync" <?php checked($ngcp_sync!=0); ?>/><?php _e('Sync with Newsgrape', 'ngcp'); ?></label>
		</div>
    </div>
    
    <div class="misc-pub-section"><label for="ngcp-type"><?php _e('Type:', 'ngcp'); ?></label>
		<select name='ngcp_type' id='ngcp_type'>
			<option value='Please Select'>Please Select</option>
			<option value='opinion' <?php selected($ngcp_type, 'opinion'); ?>><?php _e('Opinion', 'ngcp'); ?></option>
			<option value='creative' <?php selected($ngcp_type, 'creative'); ?>><?php _e('Creative', 'ngcp'); ?></option>
		</select>

		<div id="ngcp-type-select" class="<?php if($ngcp_type=="opinion") { echo "hide-if-js"; } ?>">
        Category: <select id="ngcp-cat-select" name="ngcp_category">
			<?php foreach ($categories as $cat_id => $cat_name): ?>
				<option value="<?php echo $cat_id; ?>" <?php selected($ngcp_category, $cat_id); ?>><?php _e($cat_name,'ngcp'); ?></option>
			<?php endforeach; ?>
		</select>
		</div>
	</div>
	
	<div class="misc-pub-section"><label for="ngcp-language"><?php _e('Language:', 'ngcp'); ?></label>
		<span id="ngcp-language-display" style="font-weight: bold;"><?php echo $languages[$ngcp_language] ?></span>
		<a href="#ngcp_language" class="edit-ngcp-language hide-if-no-js"><?php _e('Edit') ?></a>

		<div id="ngcp-language-select" class="hide-if-js">
		<input type="hidden" name="hidden_ngcp_language" id="hidden_ngcp_language" value="<?php echo $ngcp_language; ?>" />
		<select name='ngcp_language' id='ngcp_language'>
			<?php foreach($languages as $short => $long): ?>
                    <option value="<?php echo $short?>" <?php selected($ngcp_language, $short); ?>>
                        <?php echo $long?>
                    </option>
            <?php endforeach; ?>
		</select>
		<a href="#ngcp_language" class="save-ngcp-language hide-if-no-js button"><?php _e('OK'); ?></a>
		<a href="#ngcp_language" class="cancel-ngcp-language hide-if-no-js"><?php _e('Cancel'); ?></a>
		</div>
	</div>
	
	<div class="misc-pub-section"><label for="ngcp-license"><?php _e('License:', 'ngcp'); ?></label>
		<span id="ngcp-license-display" style="font-weight: bold;"><?php echo $licenses[$ngcp_license] ?></span>
		<a href="#ngcp_license" class="edit-ngcp-license hide-if-no-js"><?php _e('Edit') ?></a>

		<div id="ngcp-license-select" class="hide-if-js">
		<input type="hidden" name="hidden_ngcp_license" id="hidden_ngcp_license" value="<?php echo $ngcp_license; ?>" />
		<select name='ngcp_license' id='ngcp_license'>
			<?php foreach($licenses as $short => $long): ?>
                    <option value="<?php echo $short?>" <?php selected($ngcp_license, $short); ?>>
                        <?php echo $long?>
                    </option>
            <?php endforeach; ?>
		</select>
		<a href="#TB_inline?height=100&width=150&inlineId=ngcp-license-info&modal=true" class="thickbox"><?php _e("What do these licenses mean?"); ?></a><br /><br />
        <div id="ngcp-license-info" class="hide-if-js"><p><?php _e('<h1>Licenses</h1>
<p><strong>„by“</strong><br/>
You are free:<br/>
to <strong>Share</strong> — to copy, distribute and transmit the work<br/>
to <strong>Remix</strong> — to adapt the work<br/>
to make <strong>commercial use</strong> of the work<br/>
Under the following conditions:<br/>
<strong>Attribution</strong> — You must attribute the work in the manner specified by the author or licensor (but not in any way that suggests that they endorse you or your use of the work.<br/>
</p>
<p><strong>„by-nd“</strong><br/>
You are free:<br/>
to <strong>Share</strong> — to copy, distribute and transmit the work<br/>
to make commercial use of the work<br/>
Under the following conditions:<br/>
<strong>Attribution</strong> — You must attribute the work in the manner specified by the author or licensor (but not in any way that suggests that they endorse you or your use of the work).<br/>
No Derivative Works — You may not alter, transform, or build upon this work.<br/>
</p>

<p><strong>„by-nc“</strong><br/>
You are free:<br/>
to <strong>Share</strong> — to copy, distribute and transmit the work<br/>
to <strong>Remix</strong> — to adapt the work<br/>
Under the following conditions:<br/>
<strong>Attribution</strong> — You must attribute the work in the manner specified by the author or licensor (but not in any way that suggests that they endorse you or your use of the work).<br/>
Noncommercial — You may not use this work for commercial purposes.<br/>
</p>

<p><strong>„by-nc-nd“</strong><br/>
You are free to <strong>share</strong> — to copy, distribute and transmit the work<br/>
Under the following conditions:<br/>
<strong>Attribution</strong> — You must attribute the work in the manner specified by the author or licensor (but not in any way that suggests that they endorse you or your use of the work).<br/>
<strong>Noncommercial</strong> — You may not use this work for commercial purposes.<br/>
<strong>No Derivative Works</strong> — You may not alter, transform, or build upon this work.<br/>
</p>

<p><strong>„by-nc-sa“</strong><br/>
You are free:<br/>
to Share — to copy, distribute and transmit the work<br/>
to Remix — to adapt the work<br/>
Under the following conditions:<br/>
<strong>Attribution</strong> — You must attribute the work in the manner specified by the author or licensor (but not in any way that suggests that they endorse you or your use of the work).<br/>
<strong>Noncommercial</strong> — You may not use this work for commercial purposes.<br/>
<strong>Share Alike</strong> — If you alter, transform, or build upon this work, you may distribute the resulting work only under the same or similar license to this one.<br/>


<p><strong>„by-sa“</strong><br/>
You are free:<br/>
to <strong>Share</strong> — to copy, distribute and transmit the work<br/>
to <strong>Remix</strong> — to adapt the work<br/>
to make commercial use of the work<br/>
Under the following conditions:<br/>
<strong>Attribution</strong> — You must attribute the work in the manner specified by the author or licensor (but not in any way that suggests that they endorse you or your use of the work).<br/>
<strong>Share Alike</strong> — If you alter, transform, or build upon this work, you may distribute the resulting work only under the same or similar license to this one.<br/>
','ngcp'); ?><p><p style="text-align:center"><a href="#"onclick="tb_remove()" />close</a></p></div>
		<a href="#ngcp_license" class="save-ngcp-license hide-if-no-js button"><?php _e('OK'); ?></a>
		<a href="#ngcp_license" class="cancel-ngcp-license hide-if-no-js"><?php _e('Cancel'); ?></a>
		</div>
	</div>
	
    <div class="misc-pub-section  misc-pub-section-last">
		<a href="#" id="ngcp_more" class="hide-if-no-js"><?php _e('More Settings', 'ngcp'); ?></a>
		<a href="#" id="ngcp_less" class="hidden"><?php _e('Less Settings', 'ngcp'); ?></a>
        <div id="ngcp_more_inner" class="ngcp-setting hide-if-js">
            <h4>More Settings</h4>
            <label><input type="checkbox" name="ngcp_comments" id="ngcp_comments" <?php checked($ngcp_comments, '1'); ?>>  <?php _e('Allow Comments', 'ngcp'); ?></label><br />
            <label><input type="checkbox" name="ngcp_promotional" id="ngcp_promotional" <?php checked($ngcp_promotional, '1'); ?>>  <?php _e('This is a Promotional Article', 'ngcp'); ?></label>
            <a href="#TB_inline?height=100&width=150&inlineId=ngcp-promotional-info&modal=true" class="thickbox">What is a promotional article?</a>
            <div id="ngcp-promotional-info"><p><?php _e('Promotional articles have to be marked on Newsgrape, or users risk account suspendings.','ngcp'); ?><p><p style="text-align:center"><a href="#"onclick="tb_remove()" />close</a></p></div>
        </div>
    </div>

	<?php
	if(NGCP_DEBUG) {
		echo "<pre>";
		print_r( $post_meta );
		echo "ngcp_sync: $ngcp_sync";
		echo "</pre>";
		
	}
	?>
	<?php
}


function ngcp_metabox_js($hook) {
    if('post.php' != $hook && 'post-new.php' != $hook) {
        return;
    }

    wp_enqueue_script('ngcp_metabox', ngcp_plugin_dir_url().'ngcp-metabox.js'); 
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
		p.ngcp-cut-text { clear: both; }
		input#ngcp_cut_text { width: 90%; }
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

function ngcp_settings_css() { ?>
	<style type="text/css">
		table.editform th { text-align: left; }
		dl { margin-right: 2%; margin-top: 1em; color: #666; }
		dt { font-weight: bold; }
		#ngcp dd { font-style: italic; }
		ul#category-children { list-style: none; height: 15em; width: 30em; overflow-y: scroll; border: 1px solid #dfdfdf; padding: 0 1em; background: #fff; border-radius: 4px; -moz-border-radius: 4px; -webkit-border-radius: 4px; }
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
			background: #E6FFCC;
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

function ngcp_add_description_to_content($content) {
	global $id, $post;
	
	$description = get_post_meta($id, 'ngcp_description', true);

	if('' != $description) {
		return '<p class="ng_intro"><strong>'.$description.'</strong></p>'.$content;
	}
	return $content;
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
$ngcp_options = ngcp_get_options();

if(!array_key_exists('api_key', $ngcp_options) || "" == $ngcp_options['api_key']) {
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
