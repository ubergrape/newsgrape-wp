var post_ids = Array();
var names = {};

function deletion_check(form) {
	delete_count = 0;

	jQuery(".ngcp-all-articles tbody tr").each(function(){
		var old_checked = jQuery(this).find("input[name^='ngcp_fe[sync_hidden]']")[0].value != "";
		var new_checked = jQuery(this).find("input[name^='ngcp_fe[sync]']")[0].checked;
		if(old_checked == true && new_checked == false) {
			delete_count++;
		}
	});

	if(delete_count > 0) {
		var text = delete_count > 1 ? objectL10n.unpublish_multiple : objectL10n.unpublish_single;
		return confirm(text.replace('{count}', delete_count));
	}

	return true;
}

jQuery(document).ready(function($){
	$(function () {
		$('#ngcp_fe_save').click(function(event) {
			event.preventDefault();

			if (!deletion_check(ngcp_fe))
				return;

			var values = {};
			var sync = Array();
			$.each($('#ngcp_fe').serializeArray(), function(i, field) {
				values[field.name] = field.value;
			});

			for (var i = 0; i <post_ids.length; i++) {
				id = post_ids[i];
				// compare sync to is_synced_hidden --> articles will be synced if not synced yet but checked
				should_be_synced = (values['ngcp_fe[sync]['+id+']']||0) != (values['ngcp_fe[is_synced_hidden]['+id+']']||0)
								|| (values['ngcp_fe[type]['+id+']'] != values['ngcp_fe[type_hidden]['+id+']'])
								|| (values['ngcp_fe[adult_only]['+id+']']||0) != (values['ngcp_fe[adult_only_hidden]['+id+']']||0)
								|| (values['ngcp_fe[promotional]['+id+']']||0) != (values['ngcp_fe[promotional_hidden]['+id+']']||0);

				if(should_be_synced) {
					sync.push(id);
				}
			}

			if (sync.length == 0) {
				alert("Nothing changed");
			} else {
				p = $('#ngcp-sync-progress')[0];
				p.max = sync.length;
				$('#ngcp-sync-goal').html(sync.length);

				ngcp_overlay('display');

				var has_errors = false;

				for (var i = 0; i <sync.length; i++) {
					id = sync[i];

					var data = {
						action: 'ngcp_sync',
						id: id,
						sync: values['ngcp_fe[sync]['+id+']'] || 0,
						type: values['ngcp_fe[type]['+id+']'],
						promotional: values['ngcp_fe[promotional]['+id+']'] || 0,
						adult_only: values['ngcp_fe[adult_only]['+id+']'] || 0
					};

					// since wp 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
					$.post(ajaxurl, data, function(response) {
						p.value++;
						$('#ngcp-sync-current')[0].innerHTML = p.value;

						if (!response.success) {
							if(!has_errors){
								$('#ngcp-lightbox .errors').show().animate({height:'200px'});
								$('#ngcp-lightbox').animate({marginTop:'-200px'});
							}
							has_errors = true;
							jQuery('#ngcp-lightbox .errors').append("<li><b>" + response.title + "</b>: " + response.message + "</li>");
						}
						if (p.value==p.max) {
							$('.ngcp-sync-button').html(objectL10n.close);
							$('.ngcp-sync-button').attr("onclick","javascript:location.reload(true);");
							if(has_errors) {
								$('#ngcp-sync-status').html(objectL10n.finished_failed);
							} else {
								$('#ngcp-sync-status').html(objectL10n.finished_success);
							}
						}
					}, 'json');
				}
			}
		});
		$('#ngcp-help').click(function(event) {
			event.preventDefault();
			$('#ngcp-help-text').slideDown('fast');
			$(this).hide();
		});
		$('#ngcp-sync-all').change(function () {
			if (this.checked) {
				$('.ngcp-sync').prop("checked", true);
			} else {
				$('.ngcp-sync').prop("checked", false);
			}
		});
		$('#ngcp-type-all').change(function () {
			if ("" != this.value) {
				$('.ngcp-select-type').val(this.value);
			}
		});
		$('#ngcp-promotional-all').change(function () {
			if (this.checked) {
				$('.ngcp-promotional').prop("checked", true);
			} else {
				$('.ngcp-promotional').prop("checked", false);
			}
		});
		$('#ngcp-adult-all').change(function () {
			if (this.checked) {
				$('.ngcp-adult').prop("checked", true);
			} else {
				$('.ngcp-adult').prop("checked", false);
			}
		});

	});

});

function ngcp_overlay(mode) {
		if(mode == 'display') {
			if(document.getElementById("ngcp-overlay") === null) {
				div = document.createElement("div");
				div.setAttribute('id', 'ngcp-overlay');
				div.setAttribute('className', 'ngcp-overlay-bg');
				div.setAttribute('class', 'ngcp-overlay-bg');
				document.getElementsByTagName("body")[0].appendChild(div);

				jQuery('#ngcp-lightbox').show();
			}
		} else {
			jQuery('#ngcp-lightbox').hide();
			document.getElementsByTagName("body")[0].removeChild(document.getElementById("ngcp-overlay"));
		}
	}