<?php

function ngcp_add_meta_box() {
	/* only show meta box when connected to newsgrape */
	$options = ngcp_get_options();
	if (!ngcp_is_current_user_connected()) {
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
	
	$multiuser = ('multi' == $options['multiuser']);
	
	if ($multiuser) {	
		$user_meta = ngcp_user_meta();
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
            <label><input type="checkbox" name="ngcp_sync" id="ngcp_sync" <?php checked($ngcp_sync!=0); ?>/><?php _e('Sync with Newsgrape', 'ngcp'); ?> <?php if($multiuser) printf(__('(as %1$s)', 'ngcp'), $user_meta['username']); ?></label>
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
','ngcp'); ?><p><p style="text-align:center"><a href="#" onclick="tb_remove()" />close</a></p></div>
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

?>
