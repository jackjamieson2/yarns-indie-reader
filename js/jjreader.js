/* 
 **
 **
 **
 **/
(function($) {

/*
**
**   Run on initial page load
**
*/

	/*
    //Hide the 'add site' form if javascript is enabled
    $('#jjreader-addSite-form').hide();
    //Show the 'add site' button if javascript is enable
    $('#jjreader-button-addSite').show();
    */

    var pagenum =0; // Start at page 1
    //Load page 1 upon initial load
    jjreader_show_loading($('#jjreader-feed-container'));
    jjreader_showpage(pagenum);
    jjreader_showsubscriptions();

	 // The refresh button
    $("#jjreader-button-refresh").on("click", function() {
        console.log('Clicked refresh button');
        //Set button to 
 		$('#jjreader-feed-container').html("Checking for new posts...");
 		disable_button($(this));
 		jjreader_show_loading($('#jjreader-feed-container'));

        $.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_aggregator',
				},
				success : function( response ) {
					pagenum =0;
					jjreader_showpage();
					enable_button($("#jjreader-button-refresh"));
					// TO DO: refresh the feed display once posts have been fetched
					console.log("finished refreshing posts");
				}
			});
    });
    


/*
**
**   Click events
**
*/


    //Toggle the field for adding subscriptions
    /*

    $("#jjreader-button-addSite").on("click", function() {
        console.log('Clicked "Add Site" button');
        $('#jjreader-addSite-form').show();
        $(this).hide();
    });
    */



 	/* 
 	* The 'view feed' button 
 	*/
	$("#jjreader-button-feed").on("click", function() {
	
		$('#jjreader-subscriptions').hide(); // Hide the subscription manager
		$('#jjreader-feed-container').show(); // Show the feed
		$('#jjreader-load-more').show(); // Show the load more button for the feed
 		
 	});


 	/* 
 	* The 'manage subscriptions' button 
 	*/
 	$("#jjreader-button-subscriptions").on("click", function() {
		$('#jjreader-feed-container').hide(); // Hide the feed
		$('#jjreader-load-more').hide(); // Hide the load more button for the feed
 		$('#jjreader-subscriptions').show(); // Show the subscription manager
 	});

 	/*
 	*  Unsubscribe buttons
 	*/
    $( "body" ).on( "click", ".jjreader-button-unsubscribe", function() {
		var the_button = $(this);
		disable_button(the_button);
 		the_button.html("Unsubscribing...");
 		jjreader_show_loading(the_button);     	

    	var parent = $(this).parent($('.jjreader-subscription-item'));
    	var feed_id = parent.data('id');

    	if (feed_id) {
    		console.log('Unsubscribing from feed #' + feed_id);
        	$.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_unsubscribe',
					feed_id: feed_id
				},
				success : function( response ) {
					console.log ("Unsubscribed from " + feed_id + ". response = " + response);
					parent.remove();
				}
			});
        } 
 	});

	/* 
 	* Load more button * To load the next page of posts
 	*/
 	 $("#jjreader-load-more").on("click", function() {
 		disable_button($(this));
 		$(this).html("Loading...");
 		jjreader_show_loading($(this));
 		jjreader_showpage(pagenum);
 	});


 	/* 
 	* Read more button * To expand a summary and view the full post
 	*/
 
    $( "body" ).on( "click", ".jjreader-item-more", function() {
    	$(this).hide();
    	$(this).parent($('.jjreader-feed-item')).find($('.jjreader-item-summary')).hide();
    	$(this).parent($('.jjreader-feed-item')).find($('.jjreader-item-content')).show();
	});


    /*
    * Reply buttons
    */
    
    // First, show the reply text when user clicks 'reply'
    $( "body" ).on( "click", ".jjreader-reply", function() {

    //$(".jjreader-reply").on("click", function() {
    	console.log('Clicked reply button');

    	$(this).parents('.jjreader-feed-item').find('.jjreader-reply-input').show();
    }); 
    
    // Second, create reply post when user clicks 'Submit'
    $( "body" ).on( "click", ".jjreader-reply-submit", function() {
    //$(".jjreader-reply-submit").on("click", function() {
        console.log('Clicked submit button');
        reply_to = $(this).parents('.jjreader-feed-item').find('.jjreader-item-date').attr('href'); 
        reply_to_title = $(this).parents('.jjreader-feed-item').find('.jjreader-item-title').text();
        reply_to_content = $(this).parents('.jjreader-feed-item').find('.jjreader-item-content').html();
        
        title = $(this).parents('.jjreader-feed-item').find('.jjreader-reply-title').val();
        content = $(this).parents('.jjreader-feed-item').find('.jjreader-reply-text').val();
        feed_item_id = $(this).parents('.jjreader-feed-item').data('id');

        type= "reply";
        status = "draft";
        jjreader_post(reply_to, reply_to_title, reply_to_content, type, status, title, content,feed_item_id, function(response){
        	// This should return the post id as response
        });	
    });
    
    // Third, when user clicks 'Full editor', create a draft of the post, then open 
    // the full post editor in a new tab
	$( "body" ).on( "click", ".jjreader-reply-fulleditor", function() {
	//$(".jjreader-reply-fulleditor").on("click", function() {
           /* This should display the full editor (in a new tab?)
    */

    });     

    /*
    * Like buttons
    */
    $( "body" ).on( "click", ".jjreader-like", function() {
    //$(".jjreader-like").on("click", function() {
        console.log('Clicked like button');

        if ($(this).data('link')){
        	// If a like already exists, open it in a new tab
        	openInNewTab($(this).data('link'));
        	console.log($(this).data('link'));
        } else {
	        // If this feed item has not been liked, add a like to the blog

	        disable_button($(this));
	        jjreader_show_loading($(this));

	        reply_to = $(this).parents('.jjreader-feed-item').find('.jjreader-item-date').attr('href'); 
	        reply_to_title = $(this).parents('.jjreader-feed-item').find('.jjreader-item-title').text();
	        reply_to_content = $(this).parents('.jjreader-feed-item').find('.jjreader-item-content').html();
	        feed_item_id = $(this).parents('.jjreader-feed-item').data('id');
	        this_response_button = $('*[data-id="'+feed_item_id+'"]').find($('.jjreader-like'))

	        title = "";
	        content = "";
	        
	        //Note: call jjreader_post with a callback to deal with the response
	        // https://stackoverflow.com/questions/5797930/how-to-write-a-jquery-function-with-a-callback
	        type = "like";
	        status = "draft";
	        
	        jjreader_post(reply_to, reply_to_title, reply_to_content, type, status,title,content,this_response_button, function(response){
	        
	        });
        
        }
       
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
    	disable_button($(this));
 		$(this).html("Searching for feeds...");
 		var the_button = $(this);
 		jjreader_show_loading($(this));     	

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
					enable_button(the_button); // $(this) won't work here, so we use the_button, which was created above
 					the_button.html("Find feeds");

 					

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
					// Show the feed chooser
					$('#jjreader-choose-feed').show();
				}
			});
        } else {
            jjreader_error("You must enter a Site URL. "+ new_siteURL + " failed.", $(this));
        }
    });

	// CLICK: When the user clicks the submit button to add a subscription	
    $("#jjreader-addSite-submit").on("click", function() {
    		disable_button($(this));
	 		$(this).html("Adding feed...");
	 		var the_button = $(this);
	 		jjreader_show_loading($(this));     	

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
						enable_button(the_button); // $(this) won't work here, so we use the_button, which was created above
						
	 					the_button.html("Submit");
						$("#jjreader-choose-feed").hide();
						jjreader_msg(response, $("#jjreader-choose-feed"));
						console.log(response);
						jjreader_showsubscriptions();
					}
				});
	           
	        } else {
	            jjreader_error("You must enter a Feed URL. "+ new_feedURL + " failed.", $(this));
	        }
	    
    });


/*
**
**   Main functions
**
*/

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
						disable_button($('#jjreader-load-more')); // disable the button (only necesary if there are no posts in the database at all)
					} else if (pagenum ==0){
						$('#jjreader-feed-container').html(response);
						$('#jjreader-load-more').html("Load more...");
						enable_button($('#jjreader-load-more')); // re-enable the button
					} else if (pagenum > 0){
						$('#jjreader-feed-container').append(response);
						$('#jjreader-load-more').html("Load more...");
						enable_button($('#jjreader-load-more'));// re-enable the button

					} 
					console.log("finished showing page " + pagenum);
					//console.log(response);
					pagenum+=1;

				}
			});
	    	
	    } else {
	    	console.log ("user is not logged in"); 
	    }
    }
    
    /* Load the subscription list */
    function jjreader_showsubscriptions() {	    
	    if ($('#jjreader-subscription-list').length>0) {
	    	 jjreader_show_loading($('#jjreader-subscription-list'));
	    	//user is logged in so proceed
	    	 $.ajax({
				url : jjreader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'jjreader_subscription_list'
				},
				success : function( response ) {
					$('#jjreader-subscription-list').html(response);
				}
			});
	    } 
    }



      // Generic post creator
    /* Call this from within likes, replies, etc.  It tells the backend to create a post
       and returns the post ID (if successful) or 0 (if unsuccessful)
       
       */
	function jjreader_post(reply_to, reply_to_title, reply_to_content, type, status, title, content,this_response_button) {
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
					content: content,
					feed_item_id: feed_item_id
				},
				success : function( response ) {
					/* TO DO: Return the response 
					return response; 
					*/

				
					// TO DO: refresh the feed display once posts have been fetched
					console.log(response);
					// Add links to the newly created response posts
		    		enable_button(this_response_button);
					this_response_button.html('');
					this_response_button.addClass('jjreader-response-exists');
					this_response_button.attr('data-link',response);
/*
					if (type == 'like'){
						console.log ('like');
						$('*[data-id="'+feed_item_id+'"]').find($('.jjreader-like')).prop("disabled",false);
						$('*[data-id="'+feed_item_id+'"]').find($('.jjreader-like')).html('');
						$('*[data-id="'+feed_item_id+'"]').find($('.jjreader-like')).addClass('jjreader-liked');
						$('*[data-id="'+feed_item_id+'"]').find($('.jjreader-like')).attr('data-link',response);
					}
					*/
				}
			});
	}

    

/*
**
**   Utility Functions
**
*/



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
    	error_content = '<div id="' + error_id + '" class="ui-state-error" style="padding: 0 .7em;">';
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
    	msg_content = '<div id="' + msg_id + '" class="ui-state-highlight" style="padding: 0 .7em;">';
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

 	/* 
 	* Show loading animation at the specified element
 	*/
 	function jjreader_show_loading(target) {
 		target.append('<div class="jjreader-loading"></div>');
 	}


    // Open a link in a new tab
    function openInNewTab(url) {
    	window.open(url, '_blank');
	}
    
    //disable the target element and prevent pointer events
    function disable_button(target) {
    	target.prop("disabled",true);
    	target.addClass("jjreader-disabled");
    }
    
    //enable the target elements and allow pointer events
    function enable_button(target) {
    	target.prop("disabled",false);
    	target.removeClass("jjreader-disabled");
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