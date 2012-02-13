<?php

function ngcp_get_options() {
	// set defaults
	$defaults = array(
			'username'			=> '',
			'api_key'			=> '',
			'crosspost'			=> 1,
			'published_old'		=> 0,
			'privacy'			=> 'public',
			'privacy_private'	=> 'ngcp_no',
			'comments'			=> 1,
			'tag'				=> '2',
			'more'				=> 'link',
			'skip_cats'			=> array(),
			'type'				=> array(),
			'header_loc'		=> 0,		// 0 means top, 1 means bottom
			'custom_header'		=> '',
			'cut_text'			=> __('Read the rest of this entry &raquo;', 'ngcp'),
			'languages'			=> array(),
			'language'			=> substr(get_bloginfo('language'),0,2),
			'licenses'			=> array(),
			'categories'		=> array(),
			'ng_category'		=> array(),
			'license'			=> ''
			
	);
	
	$options = get_option('ngcp');
	if (!is_array($options)) $options = array();

	// still need to get the defaults for the new settings, so we'll merge again
	return array_merge( $defaults, $options );
}

// Validation/sanitization. Add errors to $msg[].
function ngcp_validate_options($input) {
	$msg = array();
	$linkmsg = '';
	$msgtype = 'error';
	$api = new NGCP_API();
	
	// API key
	if (isset($input['password']) && !empty($input['password'])) {
		$key = $api->fetch_new_key($input['username'],$input['password']);
		if ($key) {
			$input['api_key'] = $key;
			$msg[] .= __('Sucessfully connected to Newsgrape!', 'ngcp');
			$msgtype = 'updated';
		} else {
			$msg[] .= __('Newsgrape Username or Password wrong. Sorry, please try again.', 'ngcp');
		}
	}
	
	if (isset($input['delete_options'])) {
		delete_option('ngcp');
	}
	
	if (isset($input['delete_blog_id'])) {
		delete_option('ngcp_blog_id');
	}
	
	$options = ngcp_get_options();

	// If we're handling a submission, save the data
	if (isset($input['update_ngcp_options']) || isset($input['crosspost_all']) || isset($input['delete_all'])) {
		
		if (isset($input['delete_all'])) {
			// If we need to delete all, grab a list of all entries that have been crossposted
			$beenposted = get_posts(array('meta_key' => 'ngcp_id', 'post_type' => 'any', 'post_status' => 'any', 'numberposts' => '-1'));
			foreach ($beenposted as $post) {
				$repost_ids[] = $post->ID;
			}
			$msg[] .= __('Settings saved.', 'ngcp');
			$msg[] .= ngcp_delete_all($repost_ids);
			$msgtype = 'updated';
		}

		$input['skip_cats'] = array_diff(get_all_category_ids(), (array)$input['category']);

		unset($input['category']);

		// trim and stripslash
		if (!empty($input['username']))		$input['username'] = 		trim($input['username']);
		if (!empty($input['community']))	$input['community'] = 		trim($input['community']);
		if (!empty($input['custom_name']))	$input['custom_name'] = 	trim(stripslashes($input['custom_name']));
		if (!empty($input['custom_header'])) $input['custom_header'] = 	trim(stripslashes($input['custom_header']));

		if (isset($input['crosspost_all'])) {
			$msg[] .= __('Settings saved.', 'ngcp');
			$msg[] .= ngcp_post_all();
			$msgtype = 'updated';
		}
		
	} // if updated
	unset($input['delete_all']);
	unset($input['crosspost_all']);
	unset($input['update_ngcp_options']);
		
	// Send custom updated message
	$msg = implode('<br />', $msg);
	
	if (empty($msg)) {
		$msg = __('Settings saved.', 'ngcp');
		$msgtype = 'updated';
	}
	
	add_settings_error( 'ngcp', 'ngcp', $msg, $msgtype );
	return $input;
}

// ---- Options Page -----

function ngcp_add_pages() {
	$pg = add_options_page("Newsgrape", "Newsgrape", 'manage_options', basename(__FILE__), 'ngcp_display_options');
	add_action("admin_head-$pg", 'ngcp_settings_css');
	// register setting
	add_action( 'admin_init', 'register_ngcp_settings' );
	
	// Help screen //TODO
	$text = '<h3>'.__('How To', 'ngcp')."</h3>";
	$text .= 'help help help';
	$text .= '<h3>' . __( 'More Help', 'ngcp' ) . '</h3>';
	$text .= '<ul>';
	$text .= '<li><a href="http://www.newsgrape.com">' . __( 'Newsgrape.com', 'ngcp' ) . '</a></li>';
	$text .= '</ul>';

	add_contextual_help( $pg, $text );	
}

// Add link to options page from plugin list
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'ngcp_plugin_actions');
function ngcp_plugin_actions($links) {
	$new_links = array();
	$new_links[] = '<a href="options-general.php?page=ngcp-options.php">' . __('Settings', 'ngcp') . '</a>';
	return array_merge($new_links, $links);
}

// Display the options page
function ngcp_display_options() {
?>
<div class="wrap">
	<form method="post" id="ngcp" action="options.php">
		<?php
		settings_fields('ngcp');
		get_settings_errors( 'ngcp' );	
		settings_errors( 'ngcp' );
		$options = ngcp_get_options();
		?>
		<h2><?php _e('Newsgrape Crossposter Options', 'ngcp'); ?></h2>
		
		<?php if (!isset($options['api_key']) || '' == $options['api_key']): ?>
		
			<?php
				/* Fill database with fresh values:
				 * - Generate unique blog id;
				 * - Fetch languages, licenses and creative categories via API
				 */
				
				if(!get_option('ngcp_blog_id')) {
					update_option('ngcp_blog_id',ngcp_random(24));
				}
				 
				$api = new NGCP_API();
				$update = False;
				
				if (empty($options['languages']) && ($languages = $api->get_languages())) {
					$options['languages'] = $languages;
					$update = True;
				}
				if (empty($options['licenses']) && ($licenses =$api->get_licenses())) {
					$options['licenses'] = $licenses;
					$options['license'] = $licenses[0]['code'];
					$update = True;
				}
				if (empty($options['categories']) && ($categories = $api->get_creative_categories())) {
					$options['categories'] = $categories;
					$update = True;
				}
				if ($update) {
					update_option('ngcp',$options);
				}
			?>
		
			<h3>Login to Newsgrape</h3>
			<table class="form-table ui-tabs-panel">
				<tr valign="top">
					<th scope="row"><?php _e('Newsgrape Username', 'ngcp'); ?></th>
					<td>
						<input name="ngcp[username]" type="text" id="username" value="<?php esc_attr_e($options['username']); ?>" size="40" <?php if ('' != $options['api_key']) echo 'readonly="readyonly"'; ?> />
						<a href="http://www.newsgrape.com/register/" style="margin-left: 20px"><?php _e('Create Newsgrape Account', 'ngcp'); ?></a>
						</td>
				</tr>
				
				<tr valign="top">
					<th scope="row"><?php _e('Newsgrape Password', 'ngcp'); ?></th>
					<td><input name="ngcp[password]" type="password" id="password" size="40" />
					<a href="http://www.newsgrape.com/accounts/password/reset/" style="margin-left: 20px"><?php _e('Forgot Password', 'ngcp'); ?></a>
					<br />
					<span  class="description"><?php
					_e('Your password will not be saved. It is needed only once to connect your wordpress with newsgrape', 'ngcp');
					?></span>
					</td>
				<tr valign="top">
					<td>
						<input type="submit" name="ngcp[login]" id="ngcp-login" value="<?php esc_attr_e('Connect with Newsgrape', 'ngcp'); ?>" class="button-primary" /> 
					</td>
				</tr>
			</table>
			
		<?php else: ?>
		
			<table class="form-table ui-tabs-panel">
				<tr valign="top">
					<th scope="row"><?php _e('Newsgrape Username', 'ngcp'); ?></th>
					<td>
						<input name="ngcp[username]" type="text" id="username" value="<?php esc_attr_e($options['username']); ?>" size="40" readonly="readyonly" />
						<br />
						<span  class="description"><?php
					_e('Your WordPress is connected to Newsgrape', 'ngcp');
					?></span>
					</td>
				</tr>
				<tr valign="top">
					<td>
						
						<input type="submit" name="ngcp[logout]" id="ngcp-logout" value="<?php esc_attr_e('Disconnect from Newsgrape', 'ngcp'); ?>" class="button-secondary" />
					</td>
				</tr>
			</table>
			
			<?php if (0 == $options['published_old']): ?>
				<div id="ngcp-fast-edit">
					<span class="info"><?php _e('Some Types for your Articles have not been set yet.'); ?></span>
					<a href="options-general.php?page=ngcp-options-fast-edit.php" id="ngcp-fast-edit-button" class="button-primary"><?php _e('Fast-Edit Articles', 'ngcp'); ?></a>
					<br />
					<br />
					<span class="description"><?php _e('Opinions are News-Related and get tagged automatically, Creative articles are anything non-news related and have to be categorized.'); ?></span>
				</div>
			<?php endif; ?>
			
			<fieldset class="options">
				<legend><h3><?php _e('Main Options', 'ngcp'); ?></h3></legend>
				<table class="form-table ui-tabs-panel">
					<tr valign="top">
						<th scope="row"><?php _e('Crosspost', 'ngcp'); ?></th>
						<td>
						<label>
							<input name="ngcp[crosspost]" type="checkbox" value="1" <?php checked($options['crosspost'], 1); ?>/>
							<?php _e('Crosspost to Newsgrape', 'ngcp'); ?>
						</label>
						<br />
						<span class="description">
						<?php
						_e('You can enable/disable crossposting for individual posts.', 'ngcp');
						?>
						</span>
						</ td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Comments', 'ngcp'); ?></th>
						<td>
						<label>
							<input name="ngcp[comments]" type="checkbox" value="1" <?php checked($options['crosspost'], 1); ?>/>
							<?php _e('Newsgrape Comments', 'ngcp'); ?>
						</label>
						<br />
						<span class="description">
						<?php
						_e('Show Newsgrape comment system instead of WordPress comments', 'ngcp');
						?>
						</span>
						</ td>
					</tr>
				</table>
			</fieldset>
			<!--<a href="#" onclick="javascript: jQuery('#ngcp-advanced-options').show('fast');"><?php _e('Show advanced options', 'ngcp'); ?></a>
			<br />-->
			<div id="ngcp-advanced-options">
				<fieldset class="options">
					<!--<legend><h3><?php _e('Post Privacy', 'ngcp'); ?></h3></legend>
					<table class="form-table ui-tabs-panel">
						<tr valign="top">
							<th scope="row"><?php _e('Newsgrape privacy level for all published WordPress posts', 'ngcp'); ?></th>
							<td>
								<label>
									<input name="ngcp[privacy]" type="radio" value="public" <?php checked($options['privacy'], 'public'); ?>/>
									<?php _e('Public', 'ngcp'); ?>
								</label>
								<br />
								<label>
									<input name="ngcp[privacy]" type="radio" value="private" <?php checked($options['privacy'], 'private'); ?> />
									<?php _e('Private', 'ngcp'); ?>
								</label>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Newsgrape privacy level for all private WordPress posts', 'ngcp'); ?></th>
							<td>
								<label>
									<input name="ngcp[privacy_private]" type="radio" value="public" <?php checked($options['privacy_private'], 'public'); ?>/>
									<?php _e('Public', 'ngcp'); ?>
								</label>
								<br />
								<label>
									<input name="ngcp[privacy_private]" type="radio" value="private" <?php checked($options['privacy_private'], 'private'); ?> />
									<?php _e('Private', 'ngcp'); ?>
								</label>
								<br />
								<label>
									<input name="ngcp[privacy_private]" type="radio" value="ngcp_no" <?php checked($options['privacy_private'], 'ngcp_no'); ?>/>
									<?php _e('Do not crosspost at all', 'ngcp'); ?>
								</label>
							</td>
						</tr>
					</table>
				</fieldset>-->
				
				<!--<fieldset class="options">
					<legend><h3><?php _e('Newsgrape Tags', 'ngcp'); ?></h3></legend>
					<table class="form-table ui-tabs-panel">
						<tr valign="top">
							<th scope="row"><?php _e('Tag entries on Newsgrape?', 'ngcp'); ?></th>
							<td>
								<label>
									<input name="ngcp[tag]" type="radio" value="1" <?php checked($options['tag'], 2); ?>/>
									<?php _e('Tag Newsgrape entries with WordPress tags only', 'ngcp'); ?>
								</label>
								<br />
								<label>
									<input name="ngcp[tag]" type="radio" value="2" <?php checked($options['tag'], 2); ?>/>
									<?php _e('Tag Newsgrape entries with WordPress categories only', 'ngcp'); ?>
								</label>
								<br />
								<label>
									<input name="ngcp[tag]" type="radio" value="3" <?php checked($options['tag'], 3); ?>/>
									<?php _e('Tag Newsgrape entries with WordPress categories and tags', 'ngcp'); ?>
								</label>
								<br />
								<label>
									<input name="ngcp[tag]" type="radio" value="0" <?php checked($options['tag'], 0); ?>/>
									<?php _e('Do not tag Newsgrape entries', 'ngcp'); ?>
								</label>
							</td>
						</tr>
					</table>
				</fieldset>-->
				
				<fieldset class="options">
					<legend><h3><?php _e('Category Selection', 'ngcp'); ?></h3></legend>
					<table class="form-table ui-tabs-panel">
						<tr valign="top">
							<th scope="row"><?php _e('Select which of your Categories should be posted to Newsgrape and which default type should be used', 'ngcp'); ?></th>
							<td>
								<ul id="category-children">
									<li><label class="selectit"><input type="checkbox" class="checkall"> 
										<em><?php _e("Check all", 'ngcp'); ?></em></label></li>
									<?php
									if (!is_array($options['skip_cats'])) $options['skip_cats'] = (array)$options['skip_cats'];
									$selected = array_diff(get_all_category_ids(), $options['skip_cats']);
									wp_category_checklist(0, 0, $selected, false, $walker = new ngcp_Walker_Category_Checklist, false, $options = $options);
									?>
								</ul>
							<span class="description">
							<?php _e('Any post that has <em>at least one</em> of the above categories selected will be crossposted.'); ?><br />
							</span>
							</td>
						</tr>
					</table>
				</fieldset>
				
				<!--<fieldset class="options">
					<legend><h3><?php _e('Crosspost or delete all entries', 'ngcp'); ?></h3></legend>
					<table class="form-table ui-tabs-panel">
						<tr valign="top">
							<th scope="row"> </th>
							<td>
							<?php printf(__('If you have changed your username, you might want to crosspost all your entries, or delete all the old ones from your journal. These buttons are hidden so you don\'t press them by accident. <a href="%s" %s>Show the buttons.</a>', 'ngcp'), '#scary-buttons', 'onclick="javascript: jQuery(\'#scary-buttons\').show(\'fast\');"'); ?>
							</td>
						</tr>
						<tr valign="top" id="scary-buttons">
							<th scope="row"> </th>
							<td>
							<input type="submit" name="ngcp[crosspost_all]" id="crosspost_all" value="<?php esc_attr_e('Update options and crosspost all WordPress entries', 'ngcp'); ?>" class="button-secondary" />
							<input type="submit" name="ngcp[delete_all]" id="delete_all" value="<?php esc_attr_e('Update options and delete all journal entries', 'ngcp'); ?>" class="button-secondary" />
							</td>
						</tr>
					</table>
				</fieldset>
			</div>-->
			<p class="submit">
				<input type="submit" name="ngcp[update_ngcp_options]" value="<?php esc_attr_e('Update Options'); ?>" class="button-primary" />
			</p>
		<?php endif; ?>
		
		<?php if(NGCP_DEBUG): ?>
			<h3>Options Debug Output</h3>
			<pre><?php print_r($options); ?></pre>
			<pre>ngcp_blog_id: <?php print_r(get_option('ngcp_blog_id','No NGCP Blog ID')); ?></pre>
			<input type="submit" name="ngcp[delete_options]" id="ngcp-delete-options" value="<?php esc_attr_e('Delete all options', 'ngcp'); ?>" class="button-primary" /> 
			<span class="description">This forces the plugin to fetch a list of languages and licenses again</span><br/><br/>
			<input type="submit" name="ngcp[delete_blog_id]" id="ngcp-delete-blog-id" value="<?php esc_attr_e('Delete blog id', 'ngcp'); ?>" class="button-primary" /> 
			<span class="description">A new unique blog id will be generated</span>
		<?php endif; ?>
		
	</form>
	<script type="text/javascript">
	jQuery(document).ready(function($){
		$(function () { // this line makes sure this code runs on page load
			$('.checkall').click(function () {
				$(this).parents('fieldset:eq(0)').find(':checkbox').attr('checked', this.checked);
			});
			$('.ngcp-select-type').change(function () {
				if ("creative" == this.value) {
					$(this).siblings('.ngcp-select-cat').css('visibility','visible');
				} else {
					$(this).siblings('.ngcp-select-cat').css('visibility','hidden');
				}
			});
		});
		
	});
	</script>
</div>
		
<?php
}

// pre-3.1 compatibility
if (!function_exists('esc_textarea')) {
	function esc_textarea( $text ) {
	     $safe_text = htmlspecialchars( $text, ENT_QUOTES );
	     return apply_filters( 'esc_textarea', $safe_text, $text );
	}
}


// custom walker so we can change the name attribute of the category checkboxes (until #16437 is fixed)
// mostly a duplicate of Walker_Category_Checklist
class ngcp_Walker_Category_Checklist extends Walker {
     var $tree_type = 'category';
     var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');
     
 	function start_lvl(&$output, $depth, $args) {
         $indent = str_repeat("\t", $depth);
         $output .= "$indent<ul class='children'>\n";
     }
 
 	function end_lvl(&$output, $depth, $args) {
         $indent = str_repeat("\t", $depth);
         $output .= "$indent</ul>\n";
     }
 
 	function start_el(&$output, $category, $depth, $args) {
         extract($args);
         if ( empty($taxonomy) )
             $taxonomy = 'category';
 
		// This is the part we changed
         $name = 'ngcp['.$taxonomy.']';
 
         $class = in_array( $category->term_id, $popular_cats ) ? ' class="popular-category"' : '';
         $output .= "\n<li id='{$taxonomy}-{$category->term_id}'$class>" . '<label class="selectit"><input value="' . $category->term_id . '" type="checkbox" name="'.$name.'[]" id="in-'.$taxonomy.'-' . $category->term_id . '"' . checked( in_array( $category->term_id, $selected_cats ), true, false ) . disabled( empty( $args['disabled'] ), false, false ) . ' /> ' . esc_html( apply_filters('the_category', $category->name )) . '</label>';
     }
 
 	function end_el(&$output, $category, $depth, $args) {
		$options = ngcp_get_options();
		$output .= '<select name="ngcp[ng_category][category-'.$category->term_id.']" ';
		if ("creative" == $options['type']['category-'.$category->term_id]) {
			$output .= 'class="ngcp-select-cat">';
		} else {
			$output .= 'class="ngcp-select-cat ngcp-hidden">';
		}
		foreach ($options['categories'] as $cat_id => $cat_name) {
			$output .= '<option value="'.$cat_id.'" '.selected($options['ng_category']['category-'.$category->term_id], $cat_id, false).' >'.__($cat_name,'ngcp').'</option>';
		}
		$output .= '</select>';
		$output .= '<select name="ngcp[type][category-'.$category->term_id.']" class="ngcp-select-type">';
		$output .= '<option value="opinion" '.selected($options['type']['category-'.$category->term_id], 'opinion', false).' >'.__('Opinion','ngcp').'</option>';
		$output .= '<option value="creative" '.selected($options['type']['category-'.$category->term_id], 'creative', false).' >'.__('Creative','ngcp').'</option>';
		$output .= '</select>';
		$output .= "</li>\n";
     }
}
?>
