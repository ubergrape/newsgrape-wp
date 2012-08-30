<?php
// ---- Help Page -----

function ngcp_help_init() {
	wp_register_style('ngcp_help', ngcp_plugin_dir_url().'css/help.css');
}

function ngcp_add_help_page() {
	$pg = add_submenu_page('newsgrape', __('Help &amp; Support','ngcp'), __('Help &amp; Support','ngcp'), 'manage_options', basename(__FILE__), 'ngcp_display_help');
	wp_enqueue_style('ngcp_help');
	add_action('admin_print_styles', 'ngcp_help_styles' );
}

function ngcp_help_styles() {
	wp_enqueue_style('ngcp_help');
}

function ngcp_display_help() { ?>
	<p><?php _e('Any questions? Send an e-mail to ') ?><a href="#" onclick="javascript:location.href = 'mailto:office@newsgrape.com'">support[at]newsgrape[dot]com</a><?php _e(' - we\'re here to help!') ?></p>

	<div id='gsfn_search_widget'>
	<a href="https://getsatisfaction.com/newsgrape" class="widget_title"><?php _e('People-Powered User-Service for Newsgrape') ?></a>
	<div class='gsfn_content'>
	<form accept-charset='utf-8' action='https://getsatisfaction.com/newsgrape' id='gsfn_search_form' method='get' onsubmit='gsfn_search(this); return false;'>
	<div>
	<input name='style' type='hidden' value='' />
	<input name='limit' type='hidden' value='10' />
	<input name='utm_medium' type='hidden' value='widget_search' />
	<input name='utm_source' type='hidden' value='widget_newsgrape' />
	<input name='callback' type='hidden' value='gsfnResultsCallback' />
	<input name='format' type='hidden' value='widget' />
	<label class='gsfn_label' for='gsfn_search_query'><?php _e('Ask a question, share an idea, or report a problem.') ?></label>
	<input id='gsfn_search_query' maxlength='120' name='query' type='text' value='' />
	<input id='continue' type='submit' value='Continue' />
	</div>
	</form>
	<div id='gsfn_search_results' style='height: auto;'></div>
	</div>
	</div>
	<script src="https://getsatisfaction.com/newsgrape/widgets/javascripts/7c952b0/widgets.js" type="text/javascript"></script>


	<div id='gsfn_list_widget'>
	<a href="https://getsatisfaction.com/newsgrape" class="widget_title"></a>
	<div id='gsfn_content'><?php _e('Loading...') ?></div>
	<script src="https://getsatisfaction.com/newsgrape/topics.widget?callback=gsfnTopicsCallback&amp;style=idea" type="text/javascript"></script>



	<?php
}
