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
	

	// Only run this script if we're on the yarns_reader page!

	if ($('#yarns_reader').length > 0 ){

		//Hide the entry title, since we replace it with a header logo 
		$('.entry-title').hide();


    var pagenum =0; // Start at page 1
    //Load page 1 upon initial load
    yarns_reader_show_loading($('#yarns_reader-feed-container'));
    yarns_reader_showpage(pagenum);
    yarns_reader_showsubscriptions();
    yarns_reader_showrefreshtime();





	 // The refresh button
    $("#yarns_reader-button-refresh").on("click", function() {
        console.log('Clicked refresh button');
        //Set button to 
 		$('#yarns_reader-feed-container').html("Checking for new posts...");
 		disable_button($(this));
 		yarns_reader_show_loading($('#yarns_reader-feed-container'));
 		$('#yarns_reader-subscriptions').hide(); // Hide the subscription manager
		$('#yarns_reader-feed-container').show(); // Show the feed
		$('#yarns_reader-load-more').show(); // Show the load more button for the feed

        $.ajax({
				url : yarns_reader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'yarns_reader_aggregator',
				},
				success : function( response ) {
					pagenum =0;
					yarns_reader_showpage();
					yarns_reader_showrefreshtime();
					enable_button($("#yarns_reader-button-refresh"));
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

    $("#yarns_reader-button-addSite").on("click", function() {
        console.log('Clicked "Add Site" button');
        $('#yarns_reader-addSite-form').show();
        $(this).hide();
    });
    */



 	/* 
 	* The 'view feed' button 
 	*/
	$("#yarns_reader-button-feed").on("click", function() {
	
		$('#yarns_reader-subscriptions').hide(); // Hide the subscription manager
		$('#yarns_reader-feed-container').show(); // Show the feed
		$('#yarns_reader-load-more').show(); // Show the load more button for the feed
 		
 	});


 	/* 
 	* The 'manage subscriptions' button 
 	*/
 	$("#yarns_reader-button-subscriptions").on("click", function() {
		$('#yarns_reader-feed-container').hide(); // Hide the feed
		$('#yarns_reader-load-more').hide(); // Hide the load more button for the feed
 		$('#yarns_reader-subscriptions').show(); // Show the subscription manager
 	});

 	/*
 	*  Unsubscribe buttons
 	*/
    $( "body" ).on( "click", ".yarns_reader-button-unsubscribe", function() {
		var the_button = $(this);
		disable_button(the_button);
 		the_button.html("Unsubscribing...");
 		yarns_reader_show_loading(the_button);     	

    	var parent = $(this).parent($('.yarns_reader-subscription-item'));
    	var feed_id = parent.data('id');

    	if (feed_id) {
    		console.log('Unsubscribing from feed #' + feed_id);
        	$.ajax({
				url : yarns_reader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'yarns_reader_unsubscribe',
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
 	 $("#yarns_reader-load-more").on("click", function() {
 		disable_button($(this));
 		$(this).html("Loading...");
 		yarns_reader_show_loading($(this));
 		yarns_reader_showpage(pagenum);
 	});


 	/* 
 	* Read more button * To expand a summary and view the full post
 	*/
 
    $( "body" ).on( "click", ".yarns_reader-item-more", function() {
    	var feed_item_id = $(this).parents('.yarns_reader-feed-item').data('id');
	    var this_button = $(this);
	    var this_button_html = this_button.html();
	    yarns_reader_show_loading(this_button);


    	
    	 $.ajax({
			url : yarns_reader_ajax.ajax_url,
			type : 'post',
			data : {
				action : 'yarns_reader_display_full_content',
				id: feed_item_id,

			},
			success : function( response ) {
				// TO DO: refresh the feed display once posts have been fetched
				//console.log(response);
				if (response == 'error'){
					$the_content = "Sorry! There was an error loading this post";
					//console.log("error identified");

				} else {
					console.log("success!");
					$the_content = response;
				} 
				$('#yarns_reader-full-content').html($the_content);
				$('.yarns_reader-item-content').find('a').attr('target','_blank'); // add target="_blank" to links in the content
				$('body').addClass('noscroll');
				$('#yarns_reader-full-box').show();
				$('#yarns_reader-full-box').scrollTop(0);  
				

				//Reset the button to its initial state
				this_button.html(this_button_html);
			}
		});
	}); 


    /*
    * Reply buttons
    */
    
    // First, show the reply text when user clicks 'reply'
    $( "body" ).on( "click", ".yarns_reader-reply", function() {
        if ($(this).data('link')){
        	// If a reply already exists, open it in a new tab
        	openInNewTab($(this).data('link'));
        } else {
    		$(this).parents('.yarns_reader-feed-item').find('.yarns_reader-reply-input').show();
    	}
    }); 
    
    // Second, create reply post when user clicks 'Submit'
    $( "body, #yarns_reader-feed-container" ).on( "click", ".yarns_reader-reply-submit", function() {
    	// Create a reply with the entered text
        disable_button($(this));
        yarns_reader_show_loading($(this));

        reply_to = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-item-date').attr('href'); 
        reply_to_title = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-item-title').text();
        reply_to_content = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-item-content').html();

        feed_item_id = $(this).parents('.yarns_reader-feed-item').data('id');
	    this_response_button = $('*[data-id="'+feed_item_id+'"]').find($('.yarns_reader-reply'))

	    title = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-reply-title').val();
        content = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-reply-text').val();

        type = "reply";
        status = "draft";
	        
        yarns_reader_post(reply_to, reply_to_title, reply_to_content, type, status,title,content,this_response_button);
    });


    
    // Third, when user clicks 'Full editor', create a draft of the post, then open 
    // the full post editor in a new tab
	$( "body" ).on( "click", ".yarns_reader-reply-fulleditor", function() {
	//$(".yarns_reader-reply-fulleditor").on("click", function() {
           /* This should display the full editor (in a new tab?)
    */

    });     

    /*
    * Like buttons
    */
    $( "body, #yarns_reader-feed-container" ).on( "click", ".yarns_reader-like", function() {

        if ($(this).data('link')){
        	// If a like already exists, open it in a new tab
        	openInNewTab($(this).data('link'));
        } else {
	        // If this feed item has not been liked, add a like to the blog

	        disable_button($(this));
	        yarns_reader_show_loading($(this));

	        reply_to = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-item-date').attr('href'); 
	        reply_to_title = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-item-title').text();
	        reply_to_content = $(this).parents('.yarns_reader-feed-item').find('.yarns_reader-item-content').html();
	        feed_item_id = $(this).parents('.yarns_reader-feed-item').data('id');
	        this_response_button = $('*[data-id="'+feed_item_id+'"]').find($('.yarns_reader-like'))

	        title = "";
	        content = "";
	        
	        //Note: call yarns_reader_post with a callback to deal with the response
	        // https://stackoverflow.com/questions/5797930/how-to-write-a-jquery-function-with-a-callback
	        type = "like";
	        status = "draft";
	        
	        yarns_reader_post(reply_to, reply_to_title, reply_to_content, type, status,title,content,this_response_button, function(response){
	        
	        });
        
        }
       
    });



    
    //Update feed url when radio button is clicked
	$("#yarns_reader-addSite-form").on("click","input[name=yarns_reader_feed_option]", function() {
		console.log("test");
		var label = $("label[for='"+$(this).attr("id")+"']");

		$("input[name=yarns_reader-feedurl]").val($(this).val());
		$("span.yarns_reader-feed-type").text($("label[for='"+$(this).attr("id")+"']").find('.yarns_reader-feedpicker-type').text());
		
	});

    
        
	// Find feeds based on site url 
    $("#yarns_reader-addSite-findFeeds").on("click", function() {
    	disable_button($(this));
 		$(this).html("Searching for feeds...");
 		var the_button = $(this);
 		yarns_reader_show_loading($(this));     	

 		// Clear any existing values for feed url and site title
     	$("input[name=yarns_reader-sitetitle]").val("");	
     	$("input[name=yarns_reader-feedurl]").val("");	
     	$('.yarns_reader-feedpicker').empty();
     	//$("input[name=yarns_reader-siteurl]").val(addhttp($("input[name=yarns_reader-siteurl]").val()));
     	var new_siteURL = addhttp($("input[name=yarns_reader-siteurl]").val());
     	$("input[name=yarns_reader-siteurl]").val(new_siteURL);
     	if (new_siteURL) {
        	$.ajax({
				url : yarns_reader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'yarns_reader_findFeeds',
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
							$("input[name=yarns_reader-sitetitle]").val(r_data);	
						} 
						else {
							// The only other response type is a feed (either rss or h-feed)
							// Create radio buttons to choose between feed options	
							var radio_name = "yarns_reader-feedpicker_" + i;
							var radio_btn = '<input type="radio" name="yarns_reader_feed_option" value="'+r_data+'" id="'+radio_name+'" checked>';
							radio_btn += ' <label for="'+radio_name+'">'+r_data+' (<span class="yarns_reader-feedpicker-type">'+r_type+'</span>)</label><br>';
							$('.yarns_reader-feedpicker').append(radio_btn);
							// Automatically set the feedurl to the first returned feed
							/*if ($('.yarns_reader-feedpicker input').length ==1){
								$("input[name=yarns_reader-feedurl]").val(r_data);	
							}*/
						}
						console.log(json_response[i].type);
						console.log(json_response[i].data);
						// If >1 feeds were found, show the radio buttons
						if ($('.yarns_reader-feedpicker input').length >1){
							$('.yarns_reader-feedpicker').show();
						}
						// Check the first radio button
						$('.yarns_reader-feedpicker input').first().trigger("click");
						//$('.yarns_reader-feedpicker input').first().prop("checked", true).trigger("click");
					});
					// Show the feed chooser
					$('#yarns_reader-choose-feed').show();
				}
			});
        } else {
            yarns_reader_error("You must enter a Site URL. "+ new_siteURL + " failed.", $(this));
        }
    });

	// CLICK: When the user clicks the submit button to add a subscription	
    $("#yarns_reader-addSite-submit").on("click", function() {
    		disable_button($(this));
	 		$(this).html("Adding feed...");
	 		var the_button = $(this);
	 		yarns_reader_show_loading($(this));     	

	        console.log('Clicked to submit a new subscription');
			// Remove any existing errors
			clearErrors();

			var new_siteURL = $("input[name=yarns_reader-siteurl]").val();
	        var new_feedURL = $("input[name=yarns_reader-feedurl]").val();
	        var new_siteTitle = $("input[name=yarns_reader-sitetitle]").val();
	        var new_feedType = $(".yarns_reader-feed-type").text();

	        if (new_feedURL) {
	        	console.log("Adding feed " + new_feedURL);
	        	$.ajax({
					url : yarns_reader_ajax.ajax_url,
					type : 'post',
					data : {
						action : 'yarns_reader_new_subscription',
						siteurl: new_siteURL,
						feedurl: new_feedURL,
						sitetitle: new_siteTitle,
						feedtype: new_feedType,
					},
					success : function( response ) {
						enable_button(the_button); // $(this) won't work here, so we use the_button, which was created above
						
	 					the_button.html("Submit");
						$("#yarns_reader-choose-feed").hide();
						yarns_reader_msg(response, $("#yarns_reader-choose-feed"));
						console.log(response);
						yarns_reader_showsubscriptions();
					}
				});
	           
	        } else {
	            yarns_reader_error("You must enter a Feed URL. "+ new_feedURL + " failed.", $(this));
	        }
	    
    });

    

    

    /* Close the full screen view */



    $(document.body).on('click touchend','#yarns_reader-full-box, #yarns_reader-full-close', function(e){
    	if (e.target == e.currentTarget){
    		$("body").removeClass("noscroll");  
    		$("#yarns_reader-full-box").hide();
    		$("#yarns_reader-full-content").empty();
    	}
          	//Click on the close button or background container, close the full screen view
			
	});
    

/*
**
**   Main functions
**
*/

/* 
    ** Display a page from the feed database
    */
    function yarns_reader_showpage() {
		
	    // If #yarns_reader-feed-container exists, it means the user has permission to view the reader (i.e. is logged in).
	    // So, check if it exists and if so, load the first page of items from the reader 
	    
	    if ($('#yarns_reader-feed-container').length>0) {
	    	console.log ("user is logged in"); 
	    	 $.ajax({
				url : yarns_reader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'yarns_reader_display_page',
					pagenum: pagenum,

				},
				success : function( response ) {
					// TO DO: refresh the feed display once posts have been fetched
					if (response == 'finished'){
						//There are no more posts!
						$('#yarns_reader-load-more').html("There are no more posts :(");
						disable_button($('#yarns_reader-load-more')); // disable the button (only necesary if there are no posts in the database at all)
					} else{
						if (pagenum ==0){
							$('#yarns_reader-feed-container').html(response);
							$('#yarns_reader-load-more').html("Load more...");
							enable_button($('#yarns_reader-load-more')); // re-enable the button
						} else if (pagenum > 0){
							$('#yarns_reader-feed-container').append(response);
							$('#yarns_reader-load-more').html("Load more...");
							enable_button($('#yarns_reader-load-more'));// re-enable the button

						} 
						// add target="_blank" to all links in summaries
						$('.yarns_reader-item-summary').find('a').attr('target','_blank');
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
    function yarns_reader_showsubscriptions() {	    
	    if ($('#yarns_reader-subscription-list').length>0) {
	    	 yarns_reader_show_loading($('#yarns_reader-subscription-list'));
	    	//user is logged in so proceed
	    	 $.ajax({
				url : yarns_reader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'yarns_reader_subscription_list'
				},
				success : function( response ) {
					$('#yarns_reader-subscription-list').html(response);
				}
			});
	    } 
    }

    /* Load the "last refreshed" timestamp  */
    function yarns_reader_showrefreshtime() {	    
    	 $.ajax({
			url : yarns_reader_ajax.ajax_url,
			type : 'post',
			data : {
				action : 'yarns_reader_get_lastupdated'
			},
			success : function( response ) {
				$('#yarns_reader-last-updated').html(response);
			}
		});
    }


      // Generic post creator
    /* Call this from within likes, replies, etc.  It tells the backend to create a post
       and returns the post ID (if successful) or 0 (if unsuccessful)
       
       */
	function yarns_reader_post(reply_to, reply_to_title, reply_to_content, type, status, title, content,this_response_button) {
		console.log("Posting response: \n Type: " + type + "\n Content: " + content);
		 $.ajax({
				url : yarns_reader_ajax.ajax_url,
				type : 'post',
				data : {
					action : 'yarns_reader_response',
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
					this_response_button.addClass('yarns_reader-response-exists');
					this_response_button.attr('data-link',response);

					//For replies, hide the reply editor upon success
					if (this_response_button.hasClass("yarns_reader-reply")){
						this_response_button.parent($('.yarns_reader-feed-item')).find($('.yarns_reader-reply-input')).hide();
					}
/*
					if (type == 'like'){
						console.log ('like');
						$('*[data-id="'+feed_item_id+'"]').find($('.yarns_reader-like')).prop("disabled",false);
						$('*[data-id="'+feed_item_id+'"]').find($('.yarns_reader-like')).html('');
						$('*[data-id="'+feed_item_id+'"]').find($('.yarns_reader-like')).addClass('yarns_reader-liked');
						$('*[data-id="'+feed_item_id+'"]').find($('.yarns_reader-like')).attr('data-link',response);
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
    function yarns_reader_error(message, error_location) {
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
    function yarns_reader_msg(message, msg_location) {
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
 	function yarns_reader_show_loading(target) {
 		target.append('<div class="yarns_reader-loading"></div>');
 	}


    // Open a link in a new tab
    function openInNewTab(url) {
    	window.open(url, '_blank');
	}
    
    //disable the target element and prevent pointer events
    function disable_button(target) {
    	target.prop("disabled",true);
    	target.addClass("yarns_reader-disabled");
    }
    
    //enable the target elements and allow pointer events
    function enable_button(target) {
    	target.prop("disabled",false);
    	target.removeClass("yarns_reader-disabled");
    }

	function addhttp(url) {
		if (url) {
			if (!/^(f|ht)tps?:\/\//i.test(url)) {
				url = "http://" + url;
			}
	   		return url;
		}
	}


    
	} // End if (checking if #yarns_reader exists) 
	
})(jQuery);