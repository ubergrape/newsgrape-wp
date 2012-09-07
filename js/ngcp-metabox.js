jQuery(function(){
	$ = jQuery;

	function ngcpUpdateText() {
		$('#ngcp-language-display').html($('#ngcp_language option:selected').text());
		$('#ngcp-license-display').html($('#ngcp_license option:selected').text());
		$('#ngcp-type-display').html($('input[name=ngcp_type]:checked').parent().text());
	}

	$('#ngcp-language-select').siblings('a.edit-ngcp-language').click(function() {
		if ($('#ngcp-language-select').is(":hidden")) {
			$('#ngcp-language-select').slideDown('fast');
			$(this).hide();
		}
		return false;
	});

	$('.save-ngcp-language', '#ngcp-language-select').click(function() {
		$('#ngcp-language-select').slideUp('fast');
		$('#ngcp-language-select').siblings('a.edit-ngcp-language').show();
		ngcpUpdateText();
		return false;
	});

	$('.cancel-ngcp-language', '#ngcp-language-select').click(function() {
		$('#ngcp-language-select').slideUp('fast');
		$('#ngcp_language').val($('#hidden_ngcp_language').val());
		$('#ngcp-language-select').siblings('a.edit-ngcp-language').show();
		ngcpUpdateText();
		return false;
	});

	$('#ngcp-license-select').siblings('a.edit-ngcp-license').click(function() {
		if ($('#ngcp-license-select').is(":hidden")) {
			$('#ngcp-license-select').slideDown('fast');
			$(this).hide();
		}
		return false;
	});

	$('.save-ngcp-license', '#ngcp-license-select').click(function() {
		$('#ngcp-license-select').slideUp('fast');
		$('#ngcp-license-select').siblings('a.edit-ngcp-license').show();
		ngcpUpdateText();
		return false;
	});

	$('.cancel-ngcp-license', '#ngcp-license-select').click(function() {
		$('#ngcp-license-select').slideUp('fast');
		$('#ngcp_license').val($('#hidden_ngcp_license').val());
		$('#ngcp-license-select').siblings('a.edit-ngcp-license').show();
		ngcpUpdateText();
		return false;
	});

	$('.cancel-ngcp-type').click(function() {
		$('#ngcp_type_' + $('#hidden_ngcp_type').val()).prop('checked', true);
		ngcpUpdateText();
		return false;
	});

	$('#ngcp_more').click(function() {
		$('#ngcp_more_inner').slideDown('fast');
		$(this).hide();
		$('#ngcp_less').show();
		return false;
	});
	$('#ngcp_less').click(function() {
		$('#ngcp_more_inner').slideUp('fast');
		$(this).hide();
		$('#ngcp_more').show();
		return false;
	});

	/* hide/show ngcp_description-prompt-text */

	wptitlehint = function(id) {
		id = id || 'title';

		var title = $('#' + id), titleprompt = $('#' + id + '-prompt-text');

		if ( title.val() == '' )
			titleprompt.css('visibility', '');

		titleprompt.click(function(){
			$(this).css('visibility', 'hidden');
			title.focus();
		});

		title.blur(function(){
			if ( this.value == '' )
				titleprompt.css('visibility', '');
		}).focus(function(){
			titleprompt.css('visibility', 'hidden');
		}).keydown(function(e){
			titleprompt.css('visibility', 'hidden');
			$(this).unbind(e);
		});
	}

	/* only run where the newsgrape description is enabled. posts/pages */
	if($('#newsgrape_description').length != 0) {
		wptitlehint('ngcp_description');

		/* Move newsgrape description box above article body editor*/
		$('#newsgrape_description').appendTo('#titlediv');

		/* Fix tab indices */
		$('#titlediv input')[0].tabIndex = 100; // title
		$('#newsgrape_description_inner input')[0].tabIndex = 101; // newsgrape description
		$('#postdivrich textarea')[0].tabIndex = 102; // main text
	}


	/* Update trending points. do this async because we don't want to slow loading times of the wordpress admin.
	We don't need to do this via wp-cron because trending points are only interesting when editing an article */
	if(adminpage=='post-php') {
		var data = {
			action: 'ngcp_trending_percentage',
			id: ngcp_ajax.id
		};

		$.post(ajaxurl, data, function(response) {
			if (response.success) {
				$('#newsgrape .ngcp-trendingbar .bar').css('width', response.trending_percentage + '%');
			}
		}, 'json');
	}
});
