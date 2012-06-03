<?php

function ngcp_get_options() {
	// set defaults
	$defaults = array(
			'username'			=> '',
			'api_key'			=> '',
			'sync'				=> 1,
			'published_old'		=> 0,
			'privacy'			=> 'public',
			'privacy_private'	=> 'ngcp_no',
			'comments'			=> 1,
			'excerpt'			=> 1,
			'canonical'			=> 1,
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
	
	// API key
	if (isset($input['login']) && isset($input['password']) && !empty($input['password'])) {
		$api = new NGCP_API();
		$key = $api->fetch_new_key($input['username'],$input['password']);
		if ($key) {
			$input['api_key'] = $key;
			$msg[] .= __('Sucessfully connected to Newsgrape!', 'ngcp');
			$msgtype = 'updated';
		} else {
			$msg[] .= __('Could not connect to Newsgrape: ', 'ngcp') . $ngcp_error;
		}
	}
	
	if (isset($input['delete_options'])) {
		delete_option('ngcp');
	}
	
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
		
		if (!isset($input['canonical'])) {
			$input['canonical'] = '0';
		}
		
		// canonical option has been changed?
		if ($input['canonical'] != $options['canonical']) {
		    $api = new NGCP_API();
		    $result = $api->change_site_settings($canonical=$input['canonical']);
			if ($result) {
				$msg[] .= __('Synced settings to Newsgrape', 'ngcp');
				$msgtype = 'updated';
			} else {
				$msg[] .= __('Could not sync settings to Newsgrape. ', 'ngcp') . $ngcp_error;
			}
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
	$do_not_saves = array('login', 'password', 'logout', 'delete_all', 'sync_all', 'update_ngcp_options', 'delete_options');
	foreach($do_not_saves as $do_not_save) { unset($input[$do_not_save]); }
	
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
	ngcp_add_help_page();
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
		<h1 style="display: none"><?php _e('Newsgrape Sync Options', 'ngcp'); ?></h1>
		
		<img id="ngcp_header_img" src="<?php echo ngcp_plugin_dir_url(); ?>header.png" />
		
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
		
			<table class="form-table ui-tabs-panel ng-connect-box">
			<h2><?php _e('Login to Newsgrape', 'ngcp'); ?></h2>
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
					_e('Your password will not be saved. It is needed only once to connect your WordPress Blog with Newsgrape', 'ngcp');
					?></span>
					</td>
				<tr valign="top">
					<td>
						<input type="submit" name="ngcp[login]" id="ngcp-login" value="<?php esc_attr_e('Connect with Newsgrape', 'ngcp'); ?>" class="button-primary" /> 
					</td>
				</tr>
			</table>
			
		<?php else: ?>
		
			<table class="form-table ui-tabs-panel ng-connect-box ">
				<tr valign="top">
					<td>
						<h2><?php _e('Synced with Newsgrape', 'ngcp'); ?> &#x2713;</h2>
						<input class="" name="ngcp[username]" type="text" id="username" value="<?php esc_attr_e($options['username']); ?>" size="40" readonly="readyonly" />
						<input type="submit" name="ngcp[logout]" id="ngcp-logout" value="<?php esc_attr_e('Disconnect ', 'ngcp'); ?>" class="button-quiet" />
						<br />
						<span  class="description"><?php
					_e('Wundervoll! Your WordPress Blog is connected to Newsgrape.', 'ngcp');
					?></span>

					</td>
				</tr>
				<tr valign="top">
					<td>
						
					</td>
				</tr>
			</table>
			
			<?php if (0 == $options['published_old']): ?>
				<div id="ngcp-fast-edit" class="ng-edit-box">
					<div class="ng-connected-button">
						<?php _e('Edit your Synced Articles in less than a minute.'); ?>
						<a href="admin.php?page=ngcp-options-fast-edit.php" id="ngcp-fast-edit-button" class="button-secondary"><?php _e('Fast-Edit Articles', 'ngcp'); ?></a>
					</div>
					<span class="description"><?php _e('See all your articles in a list, select which you want to sync and if they are Fiction or News-Related.'); ?></span>
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
				<legend><h3><?php _e('Sync your Categories', 'ngcp'); ?></h3></legend>
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
						<a id="ngcp-help" href="#" class="hide-if-no-js"><?php _e('What is "News-Related", what is "Fiction"?', 'ngcp'); ?></a>
						<div id="ngcp-help-text" class="hide-if-js">
							<strong><?php _e('What is "News-Related"?</strong><br />
In school you write "opinion essays" - a "News-Related"-Article is basically the same thing. It expresses your personal point of view on some controversial topic. It might be in relation to a certain article or comment. However you could also just share your thoughts about something you heard about. The main difference to „Fiction“ is that you cannot just make things up – a „News-Related“-Article is non-fictional so you should keep the facts straight.<br />
<br />
<strong>What is "Fiction"?</strong><br />
A „Fiction“-Article is any text that you just make up in your mind. When writing a "Fiction"-Article you can let your mind wander – it is fictional and you can write about whatever you want. It is usually not related to a certain article or comment. It might be a short story, parody or a poem.', 'ngcp'); ?>
						</div>
						</td>
					</tr>
				</table>
			</fieldset>
	
			<fieldset class="options" id="ngcp-advanced-options">
				<legend><h3><?php _e('Advanced Options', 'ngcp'); ?></h3></legend>
				<table class="form-table">
					<tr valign="top">
						<th scope="row"><?php _e('Redirect Search Engines', 'ngcp'); ?></th>
						<td>
						<label>
							<select name="ngcp[canonical]">
								<option value="1" <?php selected($options['canonical'], '1'); ?>><?php _e('to Newsgrape') ?></option>
								<option value="0" <?php selected($options['canonical'], '0'); ?>><?php _e('to my blog') ?></option>
							</select>
						</label>
						<br />
						<span class="description">
						<?php
						_e('Automatically adds &lt;link rel=&quot;canonical&quot; ...&gt; to every synced post if you select "to my blog"', 'ngcp');
						?>
						</span>
						</td>
					</tr>
				</table>
			</fieldset>

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
		$(function () {
			$('#ngcp-help').click(function(event) {
				event.preventDefault();
				$('#ngcp-help-text').slideDown('fast');
				$(this).hide();
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
			$('#ngcp-show-advanced-options').click(function(event) {
				event.preventDefault();
				$('#ngcp-advanced-options').slideDown('fast');
				$(this).hide();
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
		$output .= '<option value="opinion" '.selected($options['type']['category-'.$category->term_id], 'opinion', false).' >'.__('News-Related','ngcp').'</option>';
		$output .= '<option value="creative" '.selected($options['type']['category-'.$category->term_id], 'creative', false).' >'.__('Fiction','ngcp').'</option>';
		$output .= '</select>';
		$output .= "</li>\n";
     }
}
?>
