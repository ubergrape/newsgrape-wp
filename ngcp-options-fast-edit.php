<?php

// Validation/sanitization. Add errors to $msg[].
function ngcp_validate_fe_options($input) {
	$msg = array();
	$msgtype = 'error';
	$api = new NGCP_API();
	
	$options = ngcp_get_options();

	$updated_articles = array();
	
	// sometimes these input fields are not set. until we find out why, do this check:
	if(!isset($input['sync_hidden']) || !isset($input['type_hidden']) || !isset($input['is_synced_hidden']) || !isset($input['promotional_hidden']) || !isset($input['adult_only_hidden'])) {
	    add_settings_error( 'ngcp_fe', 'ngcp_fe', __('Newsgrape Fast editing failed'), 'error' );
	    unset($input);
	    return $input;
	}
	
	$fields = array( 'sync', 'type', 'promotional', 'adult_only');
	
	foreach ($fields as $field) {
		foreach ($input[$field.'_hidden'] as $post_id => $old_value) {
			if ($input[$field][$post_id] != $old_value) {
				update_post_meta($post_id, 'ngcp_'.$field, $input[$field][$post_id]);
				ngcp_debug(sprintf('%s (id %d) changed from "%s" to "%s"', $field, $post_id, $input[$field.'_hidden'][$post_id], $input[$field][$post_id]));
				$updated_articles[] = $post_id;
			}
		}
	}
	
	$updated_articles = array_unique($updated_articles);
	
	$unsynced_articles = array();
	
	foreach ($input['is_synced_hidden'] as $post_id => $is_synced) {
		// has not been synced but sync is now checked
		if (False == $is_synced && 1 == $input['sync'][$post_id]) {
			$unsynced_articles[] = $post_id;
			ngcp_debug('unsynced '.$post_id);
		// has been synced but sync is now unchecked
		} else if (True == $is_synced && (!array_key_exists($post_id,$input['sync']) || 0 == $input['sync'][$post_id])){
			$unsynced_articles[] = $post_id;
			ngcp_debug('unsynced '.$post_id);
		}
	}
	
	$articles_to_sync = array_unique(array_merge($updated_articles, $unsynced_articles));
	ngcp_debug('Articles to sync: '.sizeof($articles_to_sync));

	// Sync articles with newsgrape
	foreach ($articles_to_sync as $post_id) {
		ngcp_debug('Syncing article with ID '.$post_id);
		NGCP_Core_Controller::edit($post_id);
	}

	// Send custom updated message
	$msg = implode('<br />', $msg);
	
	if (empty($msg)) {
		if (0 == sizeof($updated_articles) && 0 == sizeof($articles_to_sync)) {
			$msg = __('Nothing has changed.', 'ngcp');
		} else {
			$msg = __('Settings saved.<br/><span style="font-weight:normal">'.sizeof($updated_articles).' articles have been updated.<br/>'.sizeof($articles_to_sync).' articles have been synced</span>', 'ngcp');
		}
		$msgtype = 'updated';
	}
	
	add_settings_error( 'ngcp_fe', 'ngcp_fe', $msg, $msgtype );
	
	// Nothing to save to options db ...
	unset($input);
	
	// ... except 'published_old', so the info box in the options page disappears
	$options['published_old'] = 1;
	update_option('ngcp',$options);
	
	return $input;
}

// ---- Options Page -----

function ngcp_add_fe_page() {
	$pg = add_submenu_page('newsgrape', __('Fast Edit Articles','ngcp'), __('Fast Edit Articles','ngcp'), 'manage_options', basename(__FILE__), 'ngcp_display_fast_edit');
	add_action("admin_head-$pg", 'ngcp_settings_css');
}

// Display the options page
function ngcp_display_fast_edit() {
?>

<? include_once 'options-head.php';  ?>

<script>
function deletion_check(form) {
	delete_count = 0;
	
	jQuery(".ngcp-all-articles tbody tr").each(function(){
		var old_checked = jQuery(this).find("input[name^='ngcp_fe[sync_hidden]']")[0].value != "";
		var new_checked = jQuery(this).find("input[name^='ngcp_fe[sync]']")[0].checked;
		if(old_checked == true && new_checked == false) {
			delete_count++;
		}
	});
	
	if(delete_count > 0) {
		var text = delete_count > 1 ? "<?php _e('Do you really want to unpublish {count} articles? They will be deleted on Newsgrape'); ?>" : "<?php _e('Do you really want to unpublish {count} article? It will be deleted on Newsgrape.'); ?>";
		return confirm(text.replace('{count}', delete_count));
	}
	
	return true;
}
</script>

<div class="wrap">
	<form method="post" id="ngcp_fe" name ="ngcp_fe" action="options.php" onsubmit="return deletion_check(this)">
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
				
				<tr class="<?php if($is_synced) echo 'ngcp-synced'; ?> <?php if ($has_type) { echo 'ngcp-has-type'; } else { echo 'ngcp-has-no-type'; } ?>">
					<input type="hidden" name="ngcp_fe[is_synced_hidden][<?php the_id(); ?>]" value="<?php echo $is_synced; ?>">
					<input type="hidden" name="ngcp_fe[sync_hidden][<?php the_id(); ?>]" value="<?php echo $post_meta['ngcp_sync'][0]; ?>">
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
			<input type="submit" name="ngcp_fe[save]" value="<?php esc_attr_e('Save Changes','ngcp'); ?>" class="button-primary" />
		</p>
		
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$(function () {
				$('#ngcp-help').click(function(event) {
					event.preventDefault();
					$('#ngcp-help-text').slideDown('fast');
					$(this).hide();
				});
				$('#ngcp-sync-all').change(function () {
					if (this.checked) {
						$('.ngcp-sync').prop("checked", true);
					} else {
						$('.ngcp-sync').prop("checked", false);
					}
				});
				$('#ngcp-type-all').change(function () {
					if ("" != this.value) {
						$('.ngcp-select-type').val(this.value);
					}
				});
				$('#ngcp-promotional-all').change(function () {
					if (this.checked) {
						$('.ngcp-promotional').prop("checked", true);
					} else {
						$('.ngcp-promotional').prop("checked", false);
					}
				});
	    		$('#ngcp-adult-all').change(function () {
					if (this.checked) {
						$('.ngcp-adult').prop("checked", true);
					} else {
						$('.ngcp-adult').prop("checked", false);
					}
				});
	
			});
		});
	</script>
</div>
		
<?php
}
?>
