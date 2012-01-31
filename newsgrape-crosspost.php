<?php
/*
Plugin Name: Newsgrape Crossposter
Description: The Newsgrape Crosstposts automatically crossposts wordpress articles to your newsgrape account. Editing or deleting a post will be replicated as well.
Version: 1.0
Author: Stefan Kröner
Author URI: http://www.kanen.at/
*/ 

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
    $label = __( 'Newsgrape Settings', 'ngcp' );
    add_meta_box('newsgrape', $label, 'ngcp_inner_meta_box', null, 'side', 'high');
}

function ngcp_inner_meta_box( $post ) {
	global $post;
	$options = ngcp_get_options();
	$languages = $options['languages'];
	$licenses = array(
	    'res' => __('Restricted', 'ngcp'),
	    'ccuc' => __('CC-UC','ngcp'),
	    'cc' => __('CC','ngcp')
	);
	$ngcp_crosspost = get_post_meta($post->ID, 'ngcp_crosspost', true);
	$ngcp_language = get_post_meta($post->ID, 'ngcp_language', true);
	$ngcp_type = get_post_meta($post->ID, 'ngcp_type', true);
	$ngcp_category = get_post_meta($post->ID, 'ngcp_category', true);
	$ngcp_license = get_post_meta($post->ID, 'ngcp_license', true);
	$ngcp_id = get_post_meta($post->ID, 'ngcp_id', true); 
	$ngcp_display_url = get_post_meta($post->ID, 'ngcp_display_url', true);
?>
    <div class="misc-pub-section  ngcp-info">
    	
		<?php if($ngcp_display_url): ?>
		    <p>Newsgrape URL: <a href="<?php echo $ngcp_display_url?>"><?php echo $ngcp_display_url?></a></p>
		    <p>Newsgrape ID: <?php echo $ngcp_id?></p> <?php //TODO remove dev only ?>
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
                    <option value="<?php echo $short?>" <?php selected( $options['language'], $short ); ?>>
                        <?php echo $long?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="ngcp-setting">
            <h4><?php _e('license', 'ngcp'); ?></h4>
            <select name="ngcp_language" id="ngcp_language">
                <?php foreach($licenses as $short => $long): ?>
                    <option value="<?php echo $short?>" <?php selected( $options['license'], $short ); ?>>
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

$class = 'NGCP_Core_Controller';

add_action('admin_menu', 'ngcp_add_pages'); // Add settings menu to admin
//add_action('admin_init', 'ngcp_meta_box', 1); //TODO old wordpress?
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
//add_action('save_post', 'ngcp_save', 1); //TODO
//add_action('admin_head-post.php', 'ngcp_error_notice'); //TODO
//add_action('admin_head-post-new.php', 'ngcp_error_notice'); //TODO


// Make Plugin Multilingual
load_plugin_textdomain('ngcp', false, basename($ngcp_dir.'/lang'));

?>
