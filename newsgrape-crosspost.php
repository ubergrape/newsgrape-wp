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
	delete_option('ngcp_error_notice');
}
register_uninstall_hook( __FILE__, 'ngcp_remove_options' );


function ngcp_add_meta_box() {
    add_meta_box( 
        'newsgrape_crosspost',
        __( 'Newsgrape Crossposter', 'ngcp' ),
        'ngcp_inner_meta_box',
        'post' 
    );
}

function ngcp_inner_meta_box( $post ) {
	global $post;
	$options = ngcp_get_options();
?>
	<div class="ngcp-radio-column">
		<h4><?php _e("Crosspost?", 'ngcp'); ?></h4>
		<ul>
			<?php $ngcp_crosspost = get_post_meta($post->ID, 'ngcp_no', true);  ?>
				<li><label class="selectit" for="ngcp_crosspost_go">
					<input type="radio" <?php checked($ngcp_crosspost, 1); ?> value="1" name="ngcp_crosspost" id="ngcp_crosspost_go"/>
					<?php _e('Crosspost', 'ngcp'); if ($options['crosspost'] == 1) _e(' <em>(default)</em>', 'ngcp'); ?>
				</label></li>

				<li><label class="selectit" for="ngcp_crosspost_nogo">
					<input type="radio" <?php checked($ngcp_crosspost, 0); ?> value="0" name="ngcp_crosspost" id="ngcp_crosspost_nogo"/>
					<?php _e('Do not crosspost', 'ngcp'); if ($options['crosspost'] == 0) _e(' <em>(default)</em>', 'ngcp'); ?>
				</label></li>

		</ul>
	</div>
	<div class="ngcp-radio-column">
		<h4><?php _e("Comments", 'ngcp'); ?></h4>
		<ul>
			<?php 
			$ngcp_comments = get_post_meta($post->ID, 'ngcp_comments', true); ?>
				<li><label class="selectit" for="ngcp_comments_on">
					<input type="radio" <?php checked($ngcp_comments, 1); ?> value="1" name="ngcp_comments" id="ngcp_comments_on"/>
					<?php _e('Comments on', 'ngcp'); if ($options['comments'] == 1) _e(' <em>(default)</em>', 'ngcp'); ?>
				</label></li>
				<li><label class="selectit" for="ngcp_comments_off">
					<input type="radio" <?php checked($ngcp_comments, 0); ?> value="0" name="ngcp_comments" id="ngcp_comments_off"/>
					<?php _e('Comments off', 'ngcp'); if ($options['comments'] == 0) _e(' <em>(default)</em>', 'ngcp'); ?>
				</label></li>

			</ul>
	</div>
	<div class="ngcp-radio-column">
		<h4><?php _e("Privacy", 'ngcp'); ?></h4>
		<ul>
			<?php 
			$ngcp_privacy = get_post_meta($post->ID, 'ngcp_privacy', true);
			if (!isset($ngcp_privacy)) 
				$ngcp_privacy = $options['privacy'];
			?>
			<li><label class="selectit" for="ngcp_privacy_public">
				<input type="radio" <?php checked($ngcp_privacy, 'public'); ?> value="public" name="ngcp_privacy" id="ngcp_privacy_public"/>
				<?php _e('Public post', 'ngcp'); if ($options['privacy'] == 'public') _e(' <em>(default)</em>', 'ngcp'); ?>
			</label></li>
			<li><label class="selectit" for="ngcp_privacy_private">
				<input type="radio" <?php checked($ngcp_privacy, 'private'); ?> value="private" name="ngcp_privacy" id="ngcp_privacy_private"/>
				<?php _e('Private post', 'ngcp'); if ($options['privacy'] == 'private') _e(' <em>(default)</em>', 'ngcp'); ?>
			</label></li>
			</ul>
	</div>
	<div style="clear:both"></div>
	<div> <?php //TODO remove dev only ?>
		<?php
		$ngcp_id = get_post_meta($post->ID, 'ngcp_id', true); 
		$ngcp_display_url = get_post_meta($post->ID, 'ngcp_display_url', true);
		?>
		<p>Newsgrape ID: <?php echo $ngcp_id?></p>
		<p>Newsgrape URL: <a href="<?php echo $ngcp_display_url?>"><?php echo $ngcp_display_url?></a></p>
	</div>
		

	<?php
}

// ---- Style -----
function ngcp_css() { ?>
	<style type="text/css">
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
