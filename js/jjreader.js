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

    var pagenum =1; // Start at page 1
    //Load page 1 upon initial load
    jjreader_showpage(pagenum);

	 // The refresh button
    $("#jjreader-button-refresh").on("click", function() {
        console.log('Clicked refresh button');
        //Set button to 
 		$('#jjreader-feed-container').html("Checking for new posts...");
 		jjreader_show_loading($('#jjreader-feed-container'));

        $.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_aggregator',
				},
				success : function( response ) {
					pagenum =1;
					jjreader_showpage();
					// TO DO: refresh the feed display once posts have been fetched
					console.log("finished refreshing posts");
				}
			});
    });
    
    

    /* 
    ** Display a page from the feed database
    */
    function jjreader_showpage() {
		
	    // If #jjreader-feed-container exists, it means the user has permission to view the reader (i.e. is logged in).
	    // So, check if it exists and if so, load the first page of items from the reader 
	    
	    if ($('#jjreader-feed-container').length>0) {
	    	console.log ("user is logged in"); 
	    	 $.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_display_page',
					pagenum: pagenum,

				},
				success : function( response ) {
					// TO DO: refresh the feed display once posts have been fetched
					if (response == 'finished'){
						//There are no more posts!
						$('#jjreader-load-more').html("There are no more posts :(");
					} else if (pagenum ==1){
						$('#jjreader-feed-container').html(response);
						$('#jjreader-load-more').html("Load more...");
						$('#jjreader-load-more').prop("disabled", false); // re-enable the button
					} else if (pagenum > 1){
						$('#jjreader-feed-container').append(response);
						$('#jjreader-load-more').html("Load more...");
						$('#jjreader-load-more').prop("disabled", false); // re-enable the button

					} 
					console.log("finished showing page " + pagenum);
					console.log(response);
					pagenum+=1;

				}
			});
	    	
	    } else {
	    	console.log ("user is not logged in"); 
	    }
    }

 	/* 
 	* The 'load more' button 
 	*/
 	$("#jjreader-load-more").on("click", function() {
 		$(this).prop("disabled",true);
 		$(this).html("Loading...");
 		jjreader_show_loading($(this));
 		jjreader_showpage(pagenum);
 	});

 	/* 
 	* Show loading animation at the specified element
 	*/
 	function jjreader_show_loading(target) {
 		target.append('<div class="jjreader-loading"></div>');
 	}


    /*
    * Reply buttons
    */
    
    // First, show the reply text when user clicks 'reply'
    $(".jjreader-reply").on("click", function() {

    	$(this).parents('.jjreader-feed-item').find('.jjreader-reply-input').show();
    });
    
    // Second, create reply post when user clicks 'Submit'
      $(".jjreader-reply-submit").on("click", function() {
        console.log('Clicked submit button');
        reply_to = $(this).parents('.jjreader-feed-item').find('.jjreader-item-date').attr('href'); 
        reply_to_title = $(this).parents('.jjreader-feed-item').find('.jjreader-item-title').text();
        reply_to_content = $(this).parents('.jjreader-feed-item').find('.jjreader-item-content').html();
        
        title = $(this).parents('.jjreader-feed-item').find('.jjreader-reply-title').val();
        content = $(this).parents('.jjreader-feed-item').find('.jjreader-reply-text').val();

        type= "reply";
        status = "draft";
        jjreader_post(reply_to, reply_to_title, reply_to_content, type, status, title, content, function(response){
        	// This should return the post id as response
        });	
    });
    
    // Third, when user clicks 'Full editor', create a draft of the post, then open 
    // the full post editor in a new tab
	 $(".jjreader-reply-fulleditor").on("click", function() {
           /* This should display the full editor (in a new tab?)
    */

    });    

    /*
    * Like buttons
    */
    $(".jjreader-like").on("click", function() {
        console.log('Clicked like button');
        reply_to = $(this).parents('.jjreader-feed-item').find('.jjreader-item-date').attr('href'); 
        reply_to_title = $(this).parents('.jjreader-feed-item').find('.jjreader-item-title').text();
        reply_to_content = $(this).parents('.jjreader-feed-item').find('.jjreader-item-content').html();

        title = "";
        content = "";
        
        //Note: call jjreader_post with a callback to deal with the response
        // https://stackoverflow.com/questions/5797930/how-to-write-a-jquery-function-with-a-callback
        type = "like";
        status = "draft";
        
        jjreader_post(reply_to, reply_to_title, reply_to_content, type, status,title,content, function(response){
        	// This should call jjreader_post and return the post id as 'response' 
        
        });
        
       
    });
    
    // Generic post creator
    /* Call this from within likes, replies, etc.  It tells the backend to create a post
       and returns the post ID (if successful) or 0 (if unsuccessful)
       
       */
	function jjreader_post(reply_to, reply_to_title, reply_to_content, type, status, title, content) {
		 $.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_response',
					response_type: type,
					in_reply_to: reply_to,
					in_reply_to_title: reply_to_title,
					in_reply_to_content: reply_to_content, 
					reply_status : status,
					title : title,
					content: content
				},
				success : function( response ) {
					/* TO DO: Return the response 
					return response; 
					*/
				
					// TO DO: refresh the feed display once posts have been fetched
					console.log("response = " . response);
				}
			});
		
	}

    

    //Toggle the field for adding subscriptions
    $("#jjreader-button-addSite").on("click", function() {
        console.log('Clicked "Add Site" button');
        $('#jjreader-addSite-form').show();
        $(this).hide();

    });
    
    
    //Update feed url when radio button is clicked
	$("#jjreader-addSite-form").on("click","input[name=jjreader_feed_option]", function() {
		console.log("test");
		var label = $("label[for='"+$(this).attr("id")+"']");

		$("input[name=jjreader-feedurl]").val($(this).val());
		$("span.jjreader-feed-type").text($("label[for='"+$(this).attr("id")+"']").find('.jjreader-feedpicker-type').text());
		
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
							radio_btn += ' <label for="'+radio_name+'">'+r_data+' (<span class="jjreader-feedpicker-type">'+r_type+'</span>)</label><br>';
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
        var new_feedType = $(".jjreader-feed-type").text();

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
					feedtype: new_feedType,
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
		error_content += message;
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
		msg_content += message;
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