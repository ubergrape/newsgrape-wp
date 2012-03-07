<?php
/*
Plugin Name: Newsgrape Syncer
Description: The Newsgrape Crosstposts automatically syncs wordpress articles to your newsgrape account. Editing or deleting a post will be replicated as well.
Version: 1.0
Author: Stefan Kröner
Author URI: http://www.kanen.at/
*/

define('NGCP_DEBUG', false);

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
	$ngcp_category = (array_key_exists("ngcp_category",$post_meta)) ? $post_meta['category'][0] : false;
	$ngcp_display_url = (array_key_exists("ngcp_display_url",$post_meta)) ? $post_meta['ngcp_display_url'][0] : false;
	
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
	
    <div class="misc-pub-section ngcp-info <?php if($ngcp_display_url) { echo "synced"; } ?>">    	
		<?php if($ngcp_display_url): ?>
		    <p><strong class="on-newsgrape">On Newsgrape: </strong><a href="<?php echo $ngcp_display_url?>"><?php echo substr($ngcp_display_url,11); ?></a></p>
		    <?php if(NGCP_DEBUG) { echo "<p>NG ID: $ngcp_id</p>"; }?>
		<?php else: ?>
		    <em><?php _e('Not synced yet.', 'ngcp');?></em>
		<?php endif; ?>
	</div>
	
	<div class="misc-pub-section">
		<div class="ngcp-setting">
            <label><input type="checkbox" name="ngcp_sync" id="ngcp_sync" <?php checked($ngcp_sync, '1'); ?>/><?php _e('Sync with Newsgrape', 'ngcp'); ?></label>
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
		<a href="#ngcp_license" class="save-ngcp-license hide-if-no-js button"><?php _e('OK'); ?></a>
		<a href="#ngcp_license" class="cancel-ngcp-license hide-if-no-js"><?php _e('Cancel'); ?></a>
		</div>
	</div>
	
	<div class="misc-pub-section"><label for="ngcp-type"><?php _e('Type:', 'ngcp'); ?></label>
		<span id="ngcp-type-display" style="font-weight: bold;"><?php echo ucfirst($ngcp_type); ?></span>
		<a href="#ngcp_type" class="edit-ngcp-type hide-if-no-js"><?php _e('Edit') ?></a>

		<div id="ngcp-type-select" class="hide-if-js">
		<input type="hidden" name="hidden_ngcp_type" id="hidden_ngcp_type" value="<?php echo $ngcp_type; ?>" />
		<label><input type="radio" name="ngcp_type" id="ngcp_type_opinion" value="opinion" <?php checked($ngcp_type, 'opinion'); ?>>  <?php _e('Opinion', 'ngcp'); ?></label><br />
        <label><input type="radio" name="ngcp_type" id="ngcp_type_creative" value="creative" <?php checked($ngcp_type, 'creative'); ?>>  <?php _e('Creative', 'ngcp'); ?></label><br />
        <select id="ngcp-cat-select" class="<?php if($ngcp_type=="opinion") { echo "hide-if-js"; } ?>" name="ngcp_category">
			<?php foreach ($categories as $cat_id => $cat_name): ?>
				<option value="<?php echo $cat_id; ?>" <?php selected($ngcp_category, $cat_id); ?>><?php _e($cat_name,'ngcp'); ?></option>
			<?php endforeach; ?>
		</select>
		<br />
		<br />
		<a href="#ngcp_type" class="save-ngcp-type hide-if-no-js button"><?php _e('OK'); ?></a>
		<a href="#ngcp_type" class="cancel-ngcp-type hide-if-no-js"><?php _e('Cancel'); ?></a>
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
	
		<script type="text/javascript">
	(function(){
		function ngcpUpdateText() {
			jQuery('#ngcp-language-display').html(jQuery('#ngcp_language option:selected').text());
			jQuery('#ngcp-license-display').html(jQuery('#ngcp_license option:selected').text());
			jQuery('#ngcp-type-display').html(jQuery('input[name=ngcp_type]:checked').parent().text());
		}
		
		jQuery('#ngcp-language-select').siblings('a.edit-ngcp-language').click(function() {
			if (jQuery('#ngcp-language-select').is(":hidden")) {
				jQuery('#ngcp-language-select').slideDown('fast');
				jQuery(this).hide();
			}
			return false;
		});
		
		jQuery('.save-ngcp-language', '#ngcp-language-select').click(function() {
			jQuery('#ngcp-language-select').slideUp('fast');
			jQuery('#ngcp-language-select').siblings('a.edit-ngcp-language').show();
			ngcpUpdateText();
			return false;
		});

		jQuery('.cancel-ngcp-language', '#ngcp-language-select').click(function() {
			jQuery('#ngcp-language-select').slideUp('fast');
			jQuery('#ngcp_language').val(jQuery('#hidden_ngcp_language').val());
			jQuery('#ngcp-language-select').siblings('a.edit-ngcp-language').show();
			ngcpUpdateText();
			return false;
		});
		
		jQuery('#ngcp-license-select').siblings('a.edit-ngcp-license').click(function() {
			if (jQuery('#ngcp-license-select').is(":hidden")) {
				jQuery('#ngcp-license-select').slideDown('fast');
				jQuery(this).hide();
			}
			return false;
		});

		jQuery('.save-ngcp-license', '#ngcp-license-select').click(function() {
			jQuery('#ngcp-license-select').slideUp('fast');
			jQuery('#ngcp-license-select').siblings('a.edit-ngcp-license').show();
			ngcpUpdateText();
			return false;
		});

		jQuery('.cancel-ngcp-license', '#ngcp-license-select').click(function() {
			jQuery('#ngcp-license-select').slideUp('fast');
			jQuery('#ngcp_license').val(jQuery('#hidden_ngcp_license').val());
			jQuery('#ngcp-license-select').siblings('a.edit-ngcp-license').show();
			ngcpUpdateText();
			return false;
		});
		
		jQuery('#ngcp-type-select').siblings('a.edit-ngcp-type').click(function() {
			if (jQuery('#ngcp-type-select').is(":hidden")) {
				jQuery('#ngcp-type-select').slideDown('fast');
				jQuery(this).hide();
			}
			return false;
		});

		jQuery('.save-ngcp-type', '#ngcp-type-select').click(function() {
			jQuery('#ngcp-type-select').slideUp('fast');
			jQuery('#ngcp-type-select').siblings('a.edit-ngcp-type').show();
			ngcpUpdateText();
			return false;
		});

		jQuery('.cancel-ngcp-type', '#ngcp-type-select').click(function() {
			jQuery('#ngcp-type-select').slideUp('fast');
			jQuery('#ngcp_type_' + jQuery('#hidden_ngcp_type').val()).prop('checked', true);
			jQuery('#ngcp-type-select').siblings('a.edit-ngcp-type').show();
			ngcpUpdateText();
			return false;
		});
		
		jQuery('#ngcp_more').click(function() {
			jQuery('#ngcp_more_inner').slideDown('fast');
			jQuery(this).hide();
			jQuery('#ngcp_less').show();
			return false;
		});
		jQuery('#ngcp_less').click(function() {
			jQuery('#ngcp_more_inner').slideUp('fast');
			jQuery(this).hide();
			jQuery('#ngcp_more').show();
			return false;
		});
		
		
		jQuery('input[name=ngcp_type]').change(function() {
			if ('creative' == this.value) {
				jQuery('#ngcp-cat-select').slideDown('fast');
			} else {
				jQuery('#ngcp-cat-select').slideUp('fast');
			}
		});
	})();
	</script>

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
		.ngcp-the-date{
			border-left: 1px solid #8f8f8f;
			padding-left: 8px;
			padding-right: 12px;
			color: #8f8f8f;
			font-size: 10px;
		}
		.ngcp-the-title {
			padding-right: 8px;
		}
		.ngcp-has-no-type {
			background: #f8f8f8;
		}
		.ngcp-all-articles {
			margin-top: 20px;
		}
		.ngcp-all-articles td{
			padding: 6px;
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
					$msg .= sprintf(__('Could not sync with Newsgrape. Please got to the <a href="%s">Newsgrape options screen</a> and enter enter your Newsgrape username and password.', 'ngcp'), 'options-general.php?page=ngcp-options.php');
					$class = 'error';
					break;
				case 'create' : 
					$msg .= sprintf(__('Could not sync with Newsgrape. (Error: %s)', 'ngcp'), 'options-general.php?page=ngcpoptions.php', $error );
					$class = 'error';
					break;
				case 'update' : 
					$msg .= sprintf(__('Could not sync the updated entry to (Error: %s)', 'ngcp'), $error );
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
	echo '<div class="'.$class.'"><p>'.$msg.'</p></div>';
	update_option('ngcp_error_notice', ''); // turn off the message
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

$class = 'NGCP_Core_Controller';

add_action('admin_menu', 'ngcp_add_pages'); // Add settings menu to admin
add_action('admin_menu', 'ngcp_add_fe_pages'); // Add fast edit page to admin
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
remove_action('wp_head', 'rel_canonical');
add_action('wp_head', 'ngcp_rel_canonical');


// Make Plugin Multilingual
load_plugin_textdomain('ngcp', false, basename($ngcp_dir.'/lang'));

?>
