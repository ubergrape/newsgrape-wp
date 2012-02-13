<?php

// Validation/sanitization. Add errors to $msg[].
function ngcp_validate_fe_options($input) {
	$msg = array();
	$msgtype = 'error';
	$api = new NGCP_API();
	
	$options = ngcp_get_options();
	
	foreach ($input['crosspost'] as $post_id => $value) {
		update_post_meta($post_id, 'ngcp_crosspost', $value);
	}
	
	foreach ($input['type'] as $post_id => $value) {
		update_post_meta($post_id, 'ngcp_type', $value);
	}
	
	foreach ($input['category'] as $post_id => $value) {
		update_post_meta($post_id, 'ngcp_category', $value);
	}

	// Send custom updated message
	$msg = implode('<br />', $msg);
	
	if (empty($msg)) {
		$msg = __('Settings saved.', 'ngcp');
		$msgtype = 'updated';
	}
	
	add_settings_error( 'ngcp', 'ngcp', $msg, $msgtype );
	
	// Nothing to save to options db
	unset($input);
	
	return $input;
}

// ---- Options Page -----

function ngcp_add_fe_pages() {
	$pg = add_options_page("Newsgrape Fast Edit", "Newsgrape Fast Edit", 'manage_options', basename(__FILE__), 'ngcp_display_fast_edit');
	add_action("admin_head-$pg", 'ngcp_settings_css');
}

// Display the options page
function ngcp_display_fast_edit() {
?>
<div class="wrap">
	<form method="post" id="ngcp" action="options.php">
		<?php 
		settings_fields('ngcp');
		get_settings_errors('ngcp');	
		settings_errors('ngcp');
		$options = ngcp_get_options();
		$categories = $options['categories'];
		$posts = query_posts('posts_per_page=-1');
		?>
		
		<h2><?php _e('Newsgrape Crossposter Fast Edit Articles', 'ngcp'); ?></h2>
		
		<a id="ngcp-help" href="#" class="hide-if-no-js">What is "Opinion", what is "Creative-Article"?</a>

		<div id="ngcp-help-text" class="hide-if-js">
		Opinion ....
		</div>
		
		
		<table class="ngcp-all-articles">
			<tr class="ngcp-edit-all">
				<td>Edit All</td>
				<td>
					<label>
						<input name="ngcp-crosspost-all" id="ngcp-crosspost-all" type="checkbox" value="1" />
						<?php _e('Crosspost all', 'ngcp'); ?>
					</label>
				</td>
				<td>
					<select name="ngcp-type-all" id="ngcp-type-all">
						<option value="" selected="selected"><?php _e('Type','ngcp'); ?></option>
						<option value="opinion"><?php _e('as opinion','ngcp'); ?></option>
						<option value="creative"><?php _e('as creative article','ngcp'); ?></option>		
					</select>
					<select name="ngcp-cat-all" id="ngcp-cat-all">
						<option value=""><?php _e('Category','ngcp'); ?></option>
						<?php foreach ($categories as $cat_id => $cat_name): ?>
							<option value="<?php echo $cat_id; ?>"><?php _e($cat_name,'ngcp'); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
				
			<?php while ( have_posts() ) : the_post(); ?>
			
			<?php
				setup_postdata($post);
				$post_meta = get_post_custom($post->ID);
				$has_type = !empty($post_meta['ngcp_type']);
			?>
			
			<tr class="<?php if ($has_type) { echo 'ngcp-has-type'; } else { echo 'ngcp-has-no-type'; } ?>">
				<td>
					<a href="<?php the_permalink(); ?>" class="ngcp-the-title"><?php the_title(); ?></a>
					<span class="ngcp-the-date"><?php the_time(get_option('date_format')); ?></span>
				<td>
					<label>
						<input class="ngcp-crosspost" name="ngcp_fe[crosspost][<?php the_id(); ?>]" type="checkbox" value="1" <?php checked($post_meta['ngcp_crosspost'], 1); ?> />
						<?php _e('Crosspost to Newsgrape', 'ngcp'); ?>
					</label>
				</td>
				<td>
					<select class="ngcp-select-type" name="ngcp_fe[type][<?php the_id(); ?>]">
						<option value="opinion" <?php selected($post_meta['ngcp_type'][0], 'opinion'); ?>><?php _e('as opinion','ngcp'); ?></option>
						<option value="creative" <?php selected($post_meta['ngcp_type'][0], 'creative'); ?>><?php _e('as creative article','ngcp'); ?></option>		
					</select>
					<select class="ngcp-select-cat <?php if(!$has_type || $post_meta['ngcp_type'][0]=="opinion") { echo "hide-if-js"; } ?>" name="ngcp_fe[category][<?php the_id(); ?>]">
						<?php foreach ($categories as $cat_id => $cat_name): ?>
							<option value="<?php echo $cat_id; ?>" <?php selected($post_meta['ngcp_category'][0], $cat_id); ?>><?php _e($cat_name,'ngcp'); ?></option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
			<?php endwhile; ?>
		</table>
		
		<p class="submit">
			<input type="submit" name="ngcp_fe[save]" value="<?php esc_attr_e('Save Changes','ngcp'); ?>" class="button-primary" />
		</p>
		
	<script type="text/javascript">
		jQuery(document).ready(function($){
			$(function () {
				$('#ngcp-help').click(function () {
					$('#ngcp-help-text').slideDown('fast');
					$(this).hide();
				});
				$('.ngcp-select-type').change(function () {
					if ("creative" == this.value) {
						$(this).siblings('.ngcp-select-cat').show();
					} else {
						$(this).siblings('.ngcp-select-cat').hide();
					}
				});
				$('#ngcp-crosspost-all').change(function () {
					if (this.checked) {
						$('.ngcp-crosspost').prop("checked", true);
					} else {
						$('.ngcp-crosspost').prop("checked", false);
					}
				});
				$('#ngcp-type-all').change(function () {
					if ("" != this.value) {
						$('.ngcp-select-type').val(this.value).trigger('change');
						
					}
				});
				$('#ngcp-cat-all').change(function () {
					if ("" != this.value) {
						$('.ngcp-select-cat').val(this.value);
					}
				});
			});
		});
	</script>
</div>
		
<?php
}
?>