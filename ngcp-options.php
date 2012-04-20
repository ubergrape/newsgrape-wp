<?php

function ngcp_get_options() {
	// set defaults
	$defaults = array(
			'username'			=> '',
			'api_key'			=> '',
			'multiuser'			=> 'single',
			'sync'				=> 1,
			'published_old'		=> 0,
			'privacy'			=> 'public',
			'privacy_private'	=> 'ngcp_no',
			'comments'			=> 1,
			'excerpt'			=> 1,
			'tag'				=> '2',
			'more'				=> 'link',
			'skip_cats'			=> array(),
			'type'				=> array(),
			'header_loc'		=> 0,		// 0 means top, 1 means bottom
			'languages'			=> array(),
			'language'			=> substr(get_bloginfo('language'),0,2),
			'licenses'			=> array(),
			'categories'		=> array(),
			'ng_category'		=> array(),
			'license'			=> '1',
	);
	
	$options = get_option('ngcp');
	if (!is_array($options)) $options = array();

	// still need to get the defaults for the new settings, so we'll merge again
	return array_merge( $defaults, $options );
}

// Validation/sanitization. Add errors to $msg[].
function ngcp_validate_options($input) {
	global $ngcp_error;
	$msg = array();
	$msgtype = 'error';
	
	// if we handle a login we need an API
	if (isset($input['login'])) {
		$api = new NGCP_API();
	}
	
	// API key
	if (isset($input['login']) && 'single' == $input['multiuser'] && isset($input['password']) && !empty($input['password'])) {
		$key = $api->fetch_new_key($input['username'],$input['password']);
		if ($key) {
			$input['api_key'] = $key;
			$msg[] .= __('Sucessfully connected to Newsgrape!', 'ngcp');
			$msgtype = 'updated';
		} else {
			$msg[] .= __('Could not connect to Newsgrape: ', 'ngcp') . $ngcp_error;
		}
	}
	
	// Multiuser API Key
	if (isset($input['login']) && 'multi' == $input['multiuser'] && isset($input['multiuser_password']) && !empty($input['multiuser_password'])) {
		$key = $api->fetch_new_key($input['multiuser_username'],$input['multiuser_password']);
		if ($key) {
			$userdata = get_userdata($input['multiuser_id']);
			update_user_meta($input['multiuser_id'], 'ngcp', array('username' => $input['multiuser_username'], 'api_key' => $key));
			$msg[] .= sprintf(__('Sucessfully connected Newsgrape user %1$s to %2$s\'s WordPress Account!', 'ngcp'), $input['multiuser_username'], $userdata->user_login);
			$msgtype = 'updated';
		} else {
			$msg[] .= sprintf(__('Could not connect %1$s to Newsgrape: %2$s', 'ngcp'), $input['multiuser_username'], $ngcp_error);
		}
	}
	
	
	// Delete Multiuser
	if (isset($input['delete_multiuser'])) {
		foreach ($input['delete_multiuser'] as $key => $value) {
			$wp_username = get_userdata($key)->user_login;
			$ngcp_meta = get_user_meta($key, 'ngcp', True);
			$ng_username = '';
			if (array_key_exists('username',$ngcp_meta)) {
				$ng_username = $ngcp_meta['username'];
			}
			ngcp_debug('deleting user '.$key);
			delete_user_meta($key, 'ngcp');
			$msg[] .= sprintf(__('Disconnected %1$s\'s Newsgrape account (%2$s)!', 'ngcp'), $wp_username, $ng_username);
			$msgtype = 'updated';
		}
	}
	
	// Delete all options (only available in debug mode)
	if (isset($input['delete_options'])) {
		delete_option('ngcp');
	}
	
	// Delete blog id (only available in debug mode)
	if (isset($input['delete_blog_id'])) {
		delete_option('ngcp_blog_id');
	}

	$options = ngcp_get_options();
	
	// Do not lose settings
	$fields = array('languages', 'licenses', 'categories', 'api_key', 'published_old');
	foreach ($fields as $field) {
		if(empty($input[$field])) {
			$input[$field] = $options[$field];
		}
	}
	
	// Logout
	if (isset($input['logout'])) {
		$input['api_key'] = "";
		
		$msg[] .=  __('Disconnected from Newsgrape...', 'ngcp');
		$msgtype = 'updated';
	}

	// If we're handling a submission, save the data
	if (isset($input['update_ngcp_options']) || isset($input['sync_all']) || isset($input['delete_all'])) {
		
		// Uncheck boxes
		if (!isset($input['sync'])) {
			$input['sync'] = '0';
		}
		
		if (!isset($input['comments'])) {
			$input['comments'] = '0';
		}
		
		if (!isset($input['excerpt'])) {
			$input['excerpt'] = '0';
		}
				
		if (isset($input['delete_all'])) {
			// If we need to delete all, grab a list of all entries that have been synced
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

		if (isset($input['sync_all'])) {
			$msg[] .= __('Settings saved.', 'ngcp');
			$msg[] .= ngcp_post_all();
			$msgtype = 'updated';
		}
		
	} // if updated
	
	// Send custom updated message
	if( isset($input['login']) || isset($input['logout']) || isset($input['delete_all']) || isset($input['sync_all']) ||
		isset($input['update_ngcp_options']) || isset($input['delete_options']) || isset($input['delete_multiuser'])) {
		$msg = implode('<br />', $msg);
		
		if (empty($msg)) {
			$msg = __('Settings saved.', 'ngcp');
			$msgtype = 'updated';
		}
		
		add_settings_error( 'ngcp', 'ngcp', $msg, $msgtype );
	}
	
	// do not save in options db
	unset($input['delete_all']);
	unset($input['sync_all']);
	unset($input['update_ngcp_options']);
	unset($input['delete_options']);
	unset($input['delete_multiuser']);
	unset($input['multiuser_username']);
	unset($input['multiuser_password']);
	unset($input['multiuser_id']);
	
	return $input;
}

// ---- Options Page -----

function ngcp_add_menu() {
	$pg = add_utility_page(
		__('Newsgrape Sync Options','ngcp'),
		__('Newsgrape','ngcp'),
		'manage_options',
		'newsgrape',
		'ngcp_display_options',
		ngcp_plugin_dir_url().'menu_icon.png'
	);
	add_action("admin_head-$pg", 'ngcp_settings_css');
	// register setting
	add_action('admin_init', 'register_ngcp_settings');

	ngcp_add_fe_page();
}

// Add link to options page from plugin list
add_action('plugin_action_links_' . plugin_basename(__FILE__), 'ngcp_plugin_actions');
function ngcp_plugin_actions($links) {
	$new_links = array();
	$new_links[] = '<a href="admin.php?page=newsgrape">' . __('Settings', 'ngcp') . '</a>';
	return array_merge($new_links, $links);
}

// Display the options page
function ngcp_display_options() {
?>

<? include_once 'options-head.php';  ?>
<div class="wrap">
	<form method="post" id="ngcp" action="options.php">
		<?php
		settings_fields('ngcp');
		//settings_errors('ngcp');
		$options = ngcp_get_options();
		?>
		<h2><?php _e('Newsgrape Sync Options', 'ngcp'); ?></h2>
		
		<img id="ngcp_header_img" src="<?php echo ngcp_plugin_dir_url(); ?>header.jpeg" />

		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e('User Management', 'ngcp'); ?></th>
				<td>
					<label><input type="radio" name="ngcp[multiuser]" value="single" class="ngcp-single-multi" id="ngcp-user-single" <?php checked($options['multiuser'], 'single'); ?>>Sync all my WordPress users with one Account (Single-User)</label><br />
					<label><input type="radio" name="ngcp[multiuser]" value="multi" class="ngcp-single-multi" id="ngcp-user-multi" <?php checked($options['multiuser'], 'multi'); ?>>Sync one WordPress user per Newsgrape account (Multi-User)</label>
				</td>
			</tr>
		</table>
		
		<?php

		?>
      
				
		
		<?php if (!ngcp_is_current_user_connected()) : ?>
		
		
		<?
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
		
			<h3><?php _('Login to Newsgrape', 'ngcp'); ?></h3>
			<script>var ngcp_is_connected = false;</script>
			<table class="form-table ngcp-single-user" style="<?php if('multi' == $options['multiuser']) echo 'display:none;';?>">
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
					_e('Your password will not be saved. It is needed only once to connect your WordPress with Newsgrape', 'ngcp');
					?></span>
					</td>
				<tr valign="top">
					<td>
						<input type="submit" name="ngcp[login]" id="ngcp-login" value="<?php esc_attr_e('Connect with Newsgrape', 'ngcp'); ?>" class="button-primary" /> 
					</td>
				</tr>
			</table>
			
		<? else: ?>
			<script>var ngcp_is_connected = true;</script>
			<table class="form-table ngcp-single-user"  style="<?php if('multi' == $options['multiuser']) echo 'display:none;';?>>
				<tr valign="top">
					<th scope="row"><?php _e('Newsgrape Username', 'ngcp'); ?></th>
					<td>
						<input name="ngcp[username]" type="text" id="username" value="<?php esc_attr_e($options['username']); ?>" size="40" readonly="readyonly" /><br />
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
		<? endif; ?>
			
			<div id="ngcp-user-management" style="<?php if('multi' != $options['multiuser']) echo 'display:none;';?>">
			
				<h3><?php _e('Newsgrape users', 'ngcp'); ?></h3>
				<table class="widefat" id="ngcp-user-table">
					<tr valign="top">
						<th><?php _e('Newsgrape Username', 'ngcp'); ?></th>
						<th><?php _e('Wordpress Username', 'ngcp'); ?></th>
						<?php if(NGCP_DEBUG) { ?><th><?php _e('API key', 'ngcp'); ?></th><?php } ?>
						<th><?php _e('Action', 'ngcp'); ?></th>
					</tr>
					<?php
					$args = array(
						'orderby' => 'display_name',
						'meta_query' => array(
							array(
								'key' => 'ngcp',
								'value' => '',
								'compare' => '!=',
							),
						),
					);
					$wp_user_query = new WP_User_Query($args);
					$authors = $wp_user_query->get_results();
					if (!empty($authors)):
						echo '<script>var ngcp_has_users = true;</script>';
						foreach ($authors as $author):
							$author_info = get_userdata($author->ID);
							$author_ngcp = get_user_meta($author->ID, 'ngcp', True);  ?>
							<tr>
								<td><?php echo $author_ngcp['username']; ?></td>
								<td><?php echo $author_info->user_login; ?></td>
								<?php if(NGCP_DEBUG) { ?><td><? echo $author_ngcp['api_key']; ?></td><?php } ?>
								<td><input type="submit" name="ngcp[delete_multiuser][<? echo $author->ID; ?>]" class="button-secondary" value="<?php _e('delete'); ?>" /></td>
							</tr>
					<?	endforeach;
					else : ?>
						<tr><td colspan="4"><?php _e('No authors with Newsgrape settings found, please add one'); ?></td></tr>
					<? endif; ?>
				</table>
				
				<a href="#" class="button-secondary" id="ngcp-multiuser-add-button">Add user</a>
				
				<div class="ngcp-box hide-if-js" id="ngcp-multiuser-add-div">
					<h3><?php _e('Add new user', 'ngcp'); ?></h3>
					<table class="form-table" id="ngcp-multiuser-add">
						<tr valign="top">
							<th scope="row"><?php _e('Newsgrape Username', 'ngcp'); ?></th>
							<td><input name="ngcp[multiuser_username]" type="text" id="multiuser-username" value="<?php esc_attr_e($input['multiuser_username']); ?>" size="40" /></td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Newsgrape Password', 'ngcp'); ?></th>
							<td><input name="ngcp[multiuser_password]" type="password" id="multiuser-password" size="40" />
								<a href="http://www.newsgrape.com/accounts/password/reset/" style="margin-left: 20px"><?php _e('Forgot Password', 'ngcp'); ?></a>
								<br />
								<span class="description"><?php
								_e('Your password will not be saved. It is needed only once to connect your WordPress with Newsgrape', 'ngcp');
								?></span>
							</td>
						</tr>
						<tr valign="top">
							<th scope="row"><?php _e('Sync with Wordpress Account', 'ngcp'); ?></th>
							<td><?php wp_dropdown_users( array('id' => 'ngcp-multi-user', 'name' => 'ngcp[multiuser_id]') ); ?></td>
						</tr>
						<tr valign="top">
							<td>
								<input type="submit" name="ngcp[login]" id="ngcp-multiuser-login-button" value="<?php esc_attr_e('Connect with Newsgrape', 'ngcp'); ?>" class="button-primary" /> 
							</td>
						</tr>
					</table>
				</div>
			</div>
			
		<div id="ngcp-settings" style="<?php if(False == ngcp_is_current_user_connected()) echo 'display: none';?>">
			<?php if (0 == $options['published_old']): ?>
				<div class="ngcp-box" id="ngcp-fast-edit">
					<span class="info"><?php _e('Some Types for your Articles have not been set yet.'); ?></span>
					<a href="admin.php?page=ngcp-options-fast-edit.php" id="ngcp-fast-edit-button" class="button-primary"><?php _e('Fast-Edit Articles', 'ngcp'); ?></a>
					<br />
					<br />
					<span class="description"><?php _e('Opinions are News-Related and get tagged automatically, Creative articles are anything non-news related and have to be categorized.'); ?></span>
				</div>
			<?php endif; ?>
			
			<fieldset class="options">
				<legend><h3><?php _e('Main Options', 'ngcp'); ?></h3></legend>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Sync', 'ngcp'); ?></th>
						<td>
						<label>
							<input name="ngcp[sync]" type="checkbox" value="1" <?php checked($options['sync'], 1); ?>/>
							<?php _e('Sync with Newsgrape', 'ngcp'); ?>
						</label>
						<br />
						<span class="description">
						<?php
						_e('You can enable/disable syncing for individual posts.', 'ngcp');
						?>
						</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Comments', 'ngcp'); ?></th>
						<td>
						<label>
							<input name="ngcp[comments]" type="checkbox" value="1" <?php checked($options['comments'], 1); ?>/>
							<?php _e('Newsgrape Comments', 'ngcp'); ?>
						</label>
						<br />
						<span class="description">
						<?php
						_e('Show Newsgrape comment system instead of WordPress comments', 'ngcp');
						?>
						</span>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row"><?php _e('Intro', 'ngcp'); ?></th>
						<td>
						<label>
							<input name="ngcp[excerpt]" type="checkbox" value="1" <?php checked($options['excerpt'], 1); ?>/>
							<?php _e('Show intro field below title', 'ngcp'); ?>
						</label>
						<br />
						<span class="description">
						<?php
						_e('When editing an article you can write an intro.<br/>The intro will be highlighted on Newsgrape and also be shown on your blog.', 'ngcp');
						?>
						</span>
						</td>
					</tr>
				</table>
			</fieldset>
			
			<fieldset class="options">
				<legend><h3><?php _e('Category Selection', 'ngcp'); ?></h3></legend>
				<table class="form-table">
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
						<?php _e('Any post that has <em>at least one</em> of the above categories selected will be synced.'); ?><br />
						</span>
						<a id="ngcp-help" href="#" class="hide-if-no-js"><?php _e('What is "Opinion", what is a "Creative Article"?', 'ngcp'); ?></a>
						<div id="ngcp-help-text" class="hide-if-js">
							<?php _e('What is "Opinion"?<br />
In school you write "opinion essays" - an "Opinion" is basically the same thing. It expresses your personal point of view on some controversial topic. It might be in relation to a certain article or comment. However you could also just share your thoughts about something you heard about. The main difference to „Creative“ is that you cannot just make things up – an „Opinion“ is non-fictional so you should keep the facts straight.<br />
<br />
What is "Creative"?<br />
A „Creative“ is any text that you just make up in your mind. When writing a "Creative" you can let your mind wander – it is fictional and you can write about whatever you want. It is usually not related to a certain article or comment. It might be a short story, parody or a poem.', 'ngcp'); ?>
						</div>
						</td>
					</tr>
				</table>
			</fieldset>

			<p class="submit">
				<input type="submit" name="ngcp[update_ngcp_options]" value="<?php esc_attr_e('Update Options'); ?>" class="button-primary" />
			</p>
		</div>
		
		<?php if(NGCP_DEBUG): ?>
			<h3>Options Debug Output</h3>
			<pre><?php print_r($options); ?></pre>
			<pre>ngcp_blog_id: <?php print_r(get_option('ngcp_blog_id','No NGCP Blog ID')); ?></pre>
			<pre>current user connected: <?php print_r(ngcp_is_current_user_connected()); ?></pre>
			<input type="submit" name="ngcp[delete_options]" id="ngcp-delete-options" value="<?php esc_attr_e('Delete all options', 'ngcp'); ?>" class="button-primary" /> 
			<span class="description">This forces the plugin to fetch a list of languages and licenses again</span><br/><br/>
			<input type="submit" name="ngcp[delete_blog_id]" id="ngcp-delete-blog-id" value="<?php esc_attr_e('Delete blog id', 'ngcp'); ?>" class="button-primary" /> 
			<span class="description">A new unique blog id will be generated</span>
		<?php endif; ?>
		
	</form>
	<script type="text/javascript">
	jQuery(document).ready(function($){
		$(function () {
			$('#ngcp-help').click(function(event) {
				event.preventDefault();
				$('#ngcp-help-text').slideDown('fast');
				$(this).hide();
			});
			$('#ngcp-multiuser-add-button').click(function(event) {
				event.preventDefault();
				$('#ngcp-multiuser-add-div').show();
			});
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
			$('input[name="ngcp[multiuser]"]').change(function(event){
				if($('input:radio:checked').val()=='multi') {
					$('#ngcp-user-management').show();
					$('.ngcp-single-user').hide();
					if(ngcp_has_users) {
						$('#ngcp-settings').show();
					}
				} else {
					$('#ngcp-user-management').hide();
					$('.ngcp-single-user').show();
					if(!ngcp_is_connected) {
						$('#ngcp-settings').hide();
					}
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
