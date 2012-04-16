jQuery(function(){
	function ngcpUpdateText() {
		jQuery('#ngcp-language-display').html(jQuery('#ngcp_language option:selected').text());
		jQuery('#ngcp-license-display').html(jQuery('#ngcp_license option:selected').text());
		jQuery('#ngcp-type-display').html(jQuery('input[name=ngcp_type]:checked').parent().text());
	}
	
	jQuery('#ngcp-language-select').siblings('a.edit-ngcp-language').click(function() {
		if (jQuery('#ngcp-language-select').is(":hidden")) {
			jQuery('#ngcp-language-select').slideDown('fast');
			jQuery(this).hide();
		}
		return false;
	});
	
	jQuery('.save-ngcp-language', '#ngcp-language-select').click(function() {
		jQuery('#ngcp-language-select').slideUp('fast');
		jQuery('#ngcp-language-select').siblings('a.edit-ngcp-language').show();
		ngcpUpdateText();
		return false;
	});

	jQuery('.cancel-ngcp-language', '#ngcp-language-select').click(function() {
		jQuery('#ngcp-language-select').slideUp('fast');
		jQuery('#ngcp_language').val(jQuery('#hidden_ngcp_language').val());
		jQuery('#ngcp-language-select').siblings('a.edit-ngcp-language').show();
		ngcpUpdateText();
		return false;
	});
	
	jQuery('#ngcp-license-select').siblings('a.edit-ngcp-license').click(function() {
		if (jQuery('#ngcp-license-select').is(":hidden")) {
			jQuery('#ngcp-license-select').slideDown('fast');
			jQuery(this).hide();
		}
		return false;
	});

	jQuery('.save-ngcp-license', '#ngcp-license-select').click(function() {
		jQuery('#ngcp-license-select').slideUp('fast');
		jQuery('#ngcp-license-select').siblings('a.edit-ngcp-license').show();
		ngcpUpdateText();
		return false;
	});

	jQuery('.cancel-ngcp-license', '#ngcp-license-select').click(function() {
		jQuery('#ngcp-license-select').slideUp('fast');
		jQuery('#ngcp_license').val(jQuery('#hidden_ngcp_license').val());
		jQuery('#ngcp-license-select').siblings('a.edit-ngcp-license').show();
		ngcpUpdateText();
		return false;
	});
	

	jQuery('#ngcp_type').click(function() {
		if (jQuery('#ngcp_type')[0].value == "creative") {
			if (jQuery('#ngcp-type-select').is(":hidden")) {
				jQuery('#ngcp-type-select').slideDown('fast');
			}
		} else {
			jQuery('#ngcp-type-select').slideUp('fast');
		}
		return false;
	});

	jQuery('.cancel-ngcp-type', '#ngcp-type-select').click(function() {
		jQuery('#ngcp-type-select').slideUp('fast');
		jQuery('#ngcp_type_' + jQuery('#hidden_ngcp_type').val()).prop('checked', true);
		jQuery('#ngcp-type-select').siblings('a.edit-ngcp-type').show();
		ngcpUpdateText();
		return false;
	});
	
	jQuery('#ngcp_more').click(function() {
		jQuery('#ngcp_more_inner').slideDown('fast');
		jQuery(this).hide();
		jQuery('#ngcp_less').show();
		return false;
	});
	jQuery('#ngcp_less').click(function() {
		jQuery('#ngcp_more_inner').slideUp('fast');
		jQuery(this).hide();
		jQuery('#ngcp_more').show();
		return false;
	});
	
	
	jQuery('input[name=ngcp_type]').change(function() {
		if ('creative' == this.value) {
			jQuery('#ngcp-cat-select').slideDown('fast');
		} else {
			jQuery('#ngcp-cat-select').slideUp('fast');
		}
	});
	
	/* hide/show ngcp_description-prompt-text */
	
	wptitlehint = function(id) {
		id = id || 'title';

		var title = jQuery('#' + id), titleprompt = jQuery('#' + id + '-prompt-text');

		if ( title.val() == '' )
			titleprompt.css('visibility', '');

		titleprompt.click(function(){
			jQuery(this).css('visibility', 'hidden');
			title.focus();
		});

		title.blur(function(){
			if ( this.value == '' )
				titleprompt.css('visibility', '');
		}).focus(function(){
			titleprompt.css('visibility', 'hidden');
		}).keydown(function(e){
			titleprompt.css('visibility', 'hidden');
			jQuery(this).unbind(e);
		});
	}

	wptitlehint('ngcp_description');
	
	/* Move newsgrape description box above article body editor*/
	jQuery('#newsgrape_description').appendTo('#titlediv');
});