<?php
// ---- Help Page -----

function ngcp_add_help_page() {
	$pg = add_submenu_page('newsgrape', __('Help &amp; Support','ngcp'), __('Help &amp; Support','ngcp'), 'manage_options', basename(__FILE__), 'ngcp_display_help');
	add_action("admin_head-$pg", 'ngcp_help_css');
}

function ngcp_help_css() { ?>
	<style media='all' type='text/css'>
	div#gsfn_search_widget img { border: none; }
	div#gsfn_search_widget { font-size: 12px;  background: #ebebe4; padding: 10px; border-radius: 8px; margin-top: 10px;}
	div#gsfn_search_widget a.widget_title { color: #000; display: block; margin-bottom: 10px; }
	div#gsfn_search_widget .powered_by { margin-top: 8px; padding-top: 8px; border-top: 1px solid #DDD; } 
	div#gsfn_search_widget .powered_by a { color: #333; font-size: 90%; }      
	div#gsfn_search_widget form { margin-bottom: 8px; }
	div#gsfn_search_widget form label { margin-bottom: 5px; display: block; }
	div#gsfn_search_widget form #gsfn_search_query { width: 60%; }
	div#gsfn_search_widget div.gsfn_content { }
	div#gsfn_search_widget div.gsfn_content li { text-align:left; margin-bottom:6px; }
	div#gsfn_search_widget div.gsfn_content a.gsfn_link { line-height: 1; }
	div#gsfn_search_widget div.gsfn_content span.time { font-size: 90%; padding-left: 3px; }
	div#gsfn_search_widget div.gsfn_content p.gsfn_summary { margin-top: 2px }
	</style>
<?php }


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
