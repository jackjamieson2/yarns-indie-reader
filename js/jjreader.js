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


	 // The refresh button
    $("#jjreader-button-refresh").on("click", function() {
        console.log('Clicked refresh button');
        $.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_aggregator',
				},
				success : function( response ) {
					// TO DO: refresh the feed display once posts have been fetched
					console.log("finished refreshing posts");
				}
			});
    });
    

    //Toggle the field for adding subscriptions
    $("#jjreader-button-addSite").on("click", function() {
        console.log('Clicked "Add Site" button');
        $('#jjreader-addSite-form').show();
        $(this).hide();

    });
    
    
    //Update feed url when radio button is clicked
	$("#jjreader-addSite-form").on("click","input[name=jjreader_feed_option]", function() {
		console.log("test");
		$("input[name=jjreader-feedurl]").val($(this).val());	
	});

    
        
	// Find feeds based on site url 
    $("#jjreader-addSite-findFeeds").on("click", function() {
     	// Clear any existing values for feed url and site title
     	$("input[name=jjreader-sitetitle]").val("");	
     	$("input[name=jjreader-feedurl]").val("");	
     	$('.jjreader-feedpicker').empty();
     	//$("input[name=jjreader-siteurl]").val(addhttp($("input[name=jjreader-siteurl]").val()));
     	var new_siteURL = addhttp($("input[name=jjreader-siteurl]").val());
     	$("input[name=jjreader-siteurl]").val(new_siteURL);
     	if (new_siteURL) {
        	$.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_findFeeds',
					siteurl: new_siteURL,
				},
				success : function( response ) {
				 	console.log(response);
				 	var json_response = JSON.parse(response);
					console.log(json_response);
					$.each(json_response, function(i,item){
						var r_type = json_response[i].type;
						var r_data = json_response[i].data;
						if (r_type=="title"){
							// Set the sitetitle to the title of the site if found
							$("input[name=jjreader-sitetitle]").val(r_data);	
						} 
						else {
							// The only other response type is a feed (either rss or h-feed)
							// Create radio buttons to choose between feed options	
							var radio_name = "jjreader-feedpicker_" + i;
							var radio_btn = '<input type="radio" name="jjreader_feed_option" value="'+r_data+'" id="'+radio_name+'" checked>';
							radio_btn += ' <label for="'+radio_name+'">'+r_data+'</label><br>';
							$('.jjreader-feedpicker').append(radio_btn);
							// Automatically set the feedurl to the first returned feed
							/*if ($('.jjreader-feedpicker input').length ==1){
								$("input[name=jjreader-feedurl]").val(r_data);	
							}*/
						}
						console.log(json_response[i].type);
						console.log(json_response[i].data);
						// If >1 feeds were found, show the radio buttons
						if ($('.jjreader-feedpicker input').length >1){
							$('.jjreader-feedpicker').show();
						}
						// Check the first radio button
						$('.jjreader-feedpicker input').first().trigger("click");
						//$('.jjreader-feedpicker input').first().prop("checked", true).trigger("click");
					});
				}
			});
        } else {
            jjreader_error("You must enter a Site URL. "+ new_siteURL + " failed.", $(this));
        }
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
        	console.log("Adding feed " + new_feedURL);
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
					jjreader_msg(response, $("#jjreader-addSite-submit"));
					console.log(response);
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
    	msg_content = '<div id="' + msg_id + '" class="ui-state-highlight ui-corner-all" style="padding: 0 .7em;">';
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
    
    

	function addhttp(url) {
		if (url) {
			if (!/^(f|ht)tps?:\/\//i.test(url)) {
				url = "http://" + url;
			}
	   		return url;
		}
	}


    

})(jQuery);