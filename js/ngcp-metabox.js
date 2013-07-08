jQuery(function(){
	$ = jQuery;

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

});
