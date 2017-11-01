/* 
 **
 **
 **
 **/
(function($) {

    //Hide the 'add site' form if javascript is enabled
    $('#jjreader-addSite-form').hide();
    //Show the 'add site' button if javascript is enable
    $('#jjreader-button-addSite').show();


    //Toggle the field for adding subscriptions
    $("#jjreader-button-addSite").on("click", function() {
        console.log('Clicked "Add Site" button');
        $('#jjreader-addSite-form').show();
        $(this).hide();

    });
    
    
	// Find feeds based on site url 
    $("#jjreader-addSite-findFeeds").on("click", function() {
     	
     	var new_siteURL = $("input[name=jjreader-siteurl]").val();
	
     	$.ajax({
			url: new_siteURL,
			success: function( data ) {
				// Get the site <title>
				var title = $(data).find('title');
				console.log(title);
				// Input the title to the 'site title' field
				// Find all the <link> elements on the site
				// Select all rss feeds from the <links>
				
				
			alert( 'Your home page has ' + $(data).find('div').length + ' div elements.');
			}
		})
     	
    });

	// CLICK: When the user clicks the submit button to add a subscription	
    $("#jjreader-addSite-submit").on("click", function() {

        console.log('Clicked to submit a new subscription');
		// Remove any existing errors
		clearErrors();

		var new_siteURL = $("input[name=jjreader-siteurl]").val();
        var new_feedURL = $("input[name=jjreader-feedurl]").val();
        var new_siteTitle = $("input[name=jjreader-sitetitle]").val();
       
		

        if (new_feedURL) {
        	$.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_new_subscription',
					siteurl: new_siteURL,
					feedurl: new_feedURL,
					sitetitle: new_siteTitle,
					feedtype: 'rss',
				},
				success : function( response ) {
					alert(response);
				}
			});
           
        } else {
            jjreader_error("You must enter a Feed URL. "+ new_feedURL + " failed.", $(this));
        }

        //$('#jjreader_addSite_form').show();
        //$(this).hide();

    });
    
   
    
    // Clears any errors or highlights that were displayed previously 
	function clearErrors(){
		
		$(".ui-state-error").each(function(){
			$(this).remove();
		});
		$(".ui-state-highlight").each(function(){
			$(this).remove();
		});
	}

	// Display an error message at the specified location
    function jjreader_error(message, error_location) {
    	//construct the error box
    	var error_num = $(".ui-state-error").length + 1;
    	var error_id = "error-" + error_num;
    	error_content = '<div id="' + error_id + '" class="ui-state-error ui-corner-all" style="padding: 0 .7em;">';
    	error_content += '<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>';
		error_content += message
    	error_content += '</div>';
    	
        error_location.after(error_content);
        
        //Scroll to the error
        /* 
        IMPROVEMENT - ONLY SCROLL TO THE ERROR IF IT IS NOT ALREADY IN THE VIEWPORT
        */
        $('html,body').animate({scrollTop: $("#" + error_id ).offset().top - 100}); 
    }

	// Display a highlighted message at the specified location
    function jjreader_msg(message, msg_location) {
    	//construct the error box
    	var msg_num = $("highlight").length + 1;
    	var msg_id = "msg-" + msg_num;
    	msg_content = '<div id="' + msg_id + '" class="ui-state-error ui-corner-all" style="padding: 0 .7em;">';
    	msg_content += '<p><span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>';
		msg_content += message
    	msg_content += '</div>';
    	
        msg_location.after(msg_content);
        
        //Scroll to the message
        /* 
        IMPROVEMENT - ONLY SCROLL TO THE ERROR IF IT IS NOT ALREADY IN THE VIEWPORT
        */
        // Disabled for now
        //$('html,body').animate({scrollTop: $("#" + msg_id ).offset().top - 100}); 
    }
    

})(jQuery);