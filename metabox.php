<?php

function ngcp_add_meta_box() {
    /* only show meta box when connected to newsgrape */
    $options = ngcp_get_options();
    if (!ngcp_is_current_user_connected()) {
        return;
    }

    /* show metabox for posts and pages? */
    $syncable = 'post';
    if ($options['sync_pages'] == 1) {
        $syncable = null;
    }

    $label = __( 'Newsgrape Article Intro', 'ngcp' );
    add_meta_box('newsgrape_description', $label, 'ngcp_inner_meta_box_description', $syncable, 'normal', 'high');
}

function ngcp_inner_meta_box_description($post) {
    global $post;
    $ngcp_description = get_post_meta($post->ID, 'ngcp_description', true);
?>
    <div id="newsgrape_description_inner">
    <label class="hide-if-no-js" style="<?php if ($ngcp_description!='') echo 'visibility:hidden'; ?>" id="ngcp_description-prompt-text" for="ngcp_description"><?php _e('Enter Newsgrape article intro here','ngcp'); ?></label>
    <input type="text" name="ngcp_description" size="30" maxlength="<?php echo NGCP_MAXLENGTH_DESCRIPTION ?>" tabindex="2" value="<?php echo $ngcp_description; ?>" id="ngcp_description" autocomplete="off">
    </div>

<?php

}

function ngcp_metabox_js($hook) {
    if('post.php' != $hook && 'post-new.php' != $hook) {
        return;
    }

    global $post;

    wp_enqueue_script('ngcp_metabox', ngcp_plugin_dir_url().'js/ngcp-metabox.js');
    wp_localize_script('ngcp_metabox', 'ngcp_ajax', array( 'id' => $post->ID ) );
}

