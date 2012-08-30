<?php

add_action('wp_ajax_ngcp_sync', 'ngcp_ajax_sync');

function ngcp_load_custom_wp_admin_scripts() {
	wp_register_style('ngcp_progress', ngcp_plugin_dir_url().'css/progress-polyfill.css');
	wp_register_script('ngcp_progress', ngcp_plugin_dir_url().'js/progress-polyfill.min.js', null, null, True);
	wp_register_script('ngcp_fastedit', ngcp_plugin_dir_url().'js/fastedit.js');
    wp_enqueue_script('ngcp_progress');
    wp_enqueue_script('ngcp_fastedit');
    wp_enqueue_style('ngcp_progress');
    wp_localize_script( 'ngcp_fastedit', 'objectL10n', array(
		'unpublish_multiple' => __( 'Do you really want to unpublish {count} articles? They will be deleted on Newsgrape.','ngcp'),
		'unpublish_single' => __('Do you really want to unpublish {count} article? It will be deleted on Newsgrape.','ngcp'),
		'close' => __('Close','ngcp'),
		'finished_succes' => __('Finished syncing!','ngcp'),
		'finished_failed' => __('Some errors occured.','ngcp')
	) );
}

add_action( 'admin_enqueue_scripts', 'ngcp_load_custom_wp_admin_scripts' );

function ngcp_ajax_sync() {
	$id = $_POST['id'];
	ngcp_debug("AJAX request - sync $id");

	$fields = array( 'sync', 'type', 'promotional', 'adult_only');

	foreach ($fields as $field) {
		if (isset($_POST[$field])) {
			update_post_meta($id, 'ngcp_'.$field, $_POST[$field]);
			ngcp_debug('ngcp_'.$field.' = '.$_POST[$field]);
		}
	}

	$success = NGCP_Core_Controller::edit($id);

	global $ngcp_error;

	// response output
    header( "Content-Type: application/json" );
    $response = json_encode( array(
		'success' => ($ngcp_error===null),
		'title' => get_the_title($id),
		'id' => $id,
		'message' => $ngcp_error) );
    echo $response;

	//Important
	exit;
}

// ---- Options Page -----

function ngcp_add_fe_page() {
	$pg = add_submenu_page('newsgrape', __('Fast Edit Articles','ngcp'), __('Fast Edit Articles','ngcp'), 'manage_options', basename(__FILE__), 'ngcp_display_fast_edit');
	add_action("admin_head-$pg", 'ngcp_settings_css');
}

// Display the options page
function ngcp_display_fast_edit() { ?>

<?php include_once 'options-head.php'; ?>

<div class="wrap">
	<form method="post" id="ngcp_fe" name ="ngcp_fe" action="options.php">
		<?php
		settings_fields('ngcp_fe');
		//settings_errors('ngcp_fe');
		$options = ngcp_get_options();
		$categories = $options['categories'];
		$posts = query_posts('posts_per_page=-1'); /* Alters Main Loop */
		?>

		<h2><?php _e('Newsgrape Sync Fast Edit Articles', 'ngcp'); ?></h2>

		<a id="ngcp-help" href="#" class="hide-if-no-js"><?php _e('What is "News-Related", what is "Fiction"?', 'ngcp'); ?></a>

		<div id="ngcp-help-text" class="hide-if-js">
		<strong><?php _e('What is "News-Related"?</strong><br />
In school you write "opinion essays" - a "News-Related"-Article is basically the same thing. It expresses your personal point of view on some controversial topic. It might be in relation to a certain article or comment. However you could also just share your thoughts about something you heard about. The main difference to „Fiction“-Articles is that you cannot just make things up – a „News-Related“-Article is non-fictional so you should keep the facts straight.<br />
<br />
<strong>What is "Fiction"?</strong><br />
A „Fiction“-Article is any text that you just make up in your mind. When writing a "Fiction"-Article you can let your mind wander – it is fictional and you can write about whatever you want. It is usually not related to a certain article or comment. It might be a short story, parody or a poem.', 'ngcp'); ?>
		</div>


		<table class="widefat ngcp-all-articles">
			<thead>
				<tr class="ngcp-edit-all">
					<th class="title">Edit All</th>
					<th class="sync">
						<label>
							<input name="ngcp-sync-all" id="ngcp-sync-all" type="checkbox" value="1" />
							<?php _e('Sync all', 'ngcp'); ?>
						</label>
					</th>
					<th class='type'>
						<select name="ngcp-type-all" id="ngcp-type-all">
							<option value="" selected="selected"><?php _e('Type','ngcp'); ?></option>
							<option value="opinion"><?php _e('News-Related','ngcp'); ?></option>
							<option value="creative"><?php _e('Fiction','ngcp'); ?></option>
						</select>
					</th>
					<th class="promotional">
						<label>
							<input name="ngcp-promotional-all" id="ngcp-promotional-all" type="checkbox" value="1" />
							<?php _e('Mark all as promotional', 'ngcp'); ?>
						</label>
					</th>
					<th class="adult_only">
						<label>
							<input name="ngcp-adult-all" id="ngcp-adult-all" type="checkbox" value="1" />
							<?php _e('Mark all as adult content', 'ngcp'); ?>
						</label>
					</th>
				</tr>
			</thead>

			<tbody>
				<?php while ( have_posts() ) : the_post(); ?>

				<?php
					setup_postdata($post);
					$post_meta = get_post_custom($post->ID);
					$has_type = array_key_exists('ngcp_type', $post_meta) && !empty($post_meta['ngcp_type']);
					$is_synced = array_key_exists('ngcp_id', $post_meta) && $post_meta['ngcp_id'][0] != 0 && (!array_key_exists('ngcp_deleted', $post_meta) || False == $post_meta['ngcp_deleted']);
					$is_promotional = False == $post_meta['ngcp_promotional'];
				?>

				<script type="text/javascript">post_ids.push(<?php the_id(); ?>); names["<?php the_id(); ?>"] = "<?php the_title(); ?>";</script>

				<tr class="<?php if($is_synced) echo 'ngcp-synced'; ?> <?php if ($has_type) { echo 'ngcp-has-type'; } else { echo 'ngcp-has-no-type'; } ?>">
					<input type="hidden" name="ngcp_fe[is_synced_hidden][<?php the_id(); ?>]" value="<?php echo $is_synced; ?>">
					<input type="hidden" name="ngcp_fe[sync_hidden][<?php the_id(); ?>]" value="<?php echo $post_meta['ngcp_sync'][0]||0; ?>">
					<input type="hidden" name="ngcp_fe[type_hidden][<?php the_id(); ?>]" value="<?php echo $post_meta['ngcp_type'][0]; ?>">
					<input type="hidden" name="ngcp_fe[promotional_hidden][<?php the_id(); ?>]" value="<?php echo $post_meta['ngcp_promotional'][0]; ?>">
					<input type="hidden" name="ngcp_fe[adult_only_hidden][<?php the_id(); ?>]" value="<?php echo $post_meta['ngcp_adult_only'][0]; ?>">

					<td>
						<a href="<?php the_permalink(); ?>" class="ngcp-the-title"><?php the_title(); ?></a>
						<?php edit_post_link(); ?>
						<span class="ngcp-the-date"><?php the_time(get_option('date_format')); ?></span>
					<td>
						<label>
							<input class="ngcp-sync" name="ngcp_fe[sync][<?php the_id(); ?>]" type="checkbox" value="1" <?php checked($post_meta['ngcp_sync'][0]!=0 && $post_meta['ngcp_sync'][0]!=''); ?> />
							<?php _e('Sync with Newsgrape', 'ngcp'); ?>
						</label>
						<br />
						<span class="ngcp-sync-state <?php if($is_synced) echo 'ngcp-synced'; ?>">
							<?php if($is_synced) {
								_e('synced', 'ngcp');
							} else {
								_e('not synced yet', 'ngcp');
							} ?>
						</span>
					</td>
					<td>
						<select class="ngcp-select-type" name="ngcp_fe[type][<?php the_id(); ?>]">
							<option value="opinion" <?php selected($post_meta['ngcp_type'][0], 'opinion'); ?>><?php _e('News-Related','ngcp'); ?></option>
							<option value="creative" <?php selected($post_meta['ngcp_type'][0], 'creative'); ?>><?php _e('Fiction','ngcp'); ?></option>
						</select>
					</td>
					<td>
						<label>
							<input class="ngcp-promotional" name="ngcp_fe[promotional][<?php the_id(); ?>]" type="checkbox" value="1" <?php checked($post_meta['ngcp_promotional'][0]!=0 && $post_meta['ngcp_promotional'][0]!=''); ?> />
							<?php _e('Mark as promotional article', 'ngcp'); ?>
						</label>
						<br />
						<span class="ngcp-promotional-state <?php if($is_promotional) echo 'ngcp-promotional'; ?>">
							<?php if($is_promotional) {
								_e('promotional', 'ngcp');
							} else {
								_e('', 'ngcp');
							} ?>
						</span>
					</td>
	    			<td>
						<label>
							<input class="ngcp-adult" name="ngcp_fe[adult_only][<?php the_id(); ?>]" type="checkbox" value="1" <?php checked($post_meta['ngcp_adult_only'][0]!=0 && $post_meta['ngcp_adult_only'][0]!=''); ?> />
							<?php _e('Mark as adult content', 'ngcp'); ?>
						</label>
						<br />
						<span class="ngcp-adult-state <?php if($adult_only) echo 'ngcp-adult'; ?>">
							<?php if($adult_only) {
								_e('adult content', 'ngcp');
							} else {
								_e('', 'ngcp');
							} ?>
						</span>
					</td>
				</tr>
				<?php endwhile; ?>
			</tbody>
		</table>

		<p class="submit">
			<input type="submit" id="ngcp_fe_save" name="ngcp_fe[save]" value="<?php esc_attr_e('Save Changes','ngcp'); ?>" class="button-primary" />
		</p>
</div>


<div id="ngcp-lightbox" style="display:none">
	<div class="ngcp-box-header"></div>
	<span id="ngcp-sync-status"><?php _e('Syncing articles','ngcp'); ?></span>
	<label><?php _e('Progress', 'ngcp'); ?>: <span id="ngcp-sync-current">0</span> <?php _e('of', 'ngcp'); ?> <span id="ngcp-sync-goal">0</span>
		<progress id="ngcp-sync-progress" value="0" max="100"></progress>
	</label>
	<ul class="errors"></ul>
	<button class="ngcp-sync-button" onclick="ngcp_overlay('none')"><?php _e('Cancel', 'ngcp'); ?></button>
</div>

<?php
}
?>
