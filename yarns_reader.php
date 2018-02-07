<?php
/**
 * Plugin Name: Yarns Indie Reader
 * Plugin URI: jackjamieson.net
 * Description: Yarns Indie Reader is a feed reader. You can subscribe to blogs and websites, view their updates in a feed, then post likes and replies directly to your WordPress site. Replies and likes are marked-up with microformats 2, so posts created with this plugin will support webmentions. 

 * Version: 0.1
 * Author: Jack Jamieson
 * Author URI: http://jackjamieson.net
 * Text Domain: yarns_reader
 */
 
 /* Copyright 2017 Jack Jamieson (email : jackjamieson@gmail.com)
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
*/

/* 
 *	Portions of code are modified from Ashton McAllan's WhisperFollow plugin. 
 *	- http://acegiak.machinespirit.net/2012/01/25/whisperfollow/
 	- https://github.com/acegiak/WhisperFollow.
 */ 

/*
 * MF2 Parser by Barnaby Walters: https://github.com/indieweb/php-mf2
 */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
 
// Require the mf2 parser only if it has not already been added by another plugin
if ( ! class_exists( 'Mf2\Parser' ) ) {
    require_once plugin_dir_path( __FILE__ ) .  'lib/Mf2/Parser.php'; // For parsing h-feed
} 

if ( ! class_exists( 'phpUri' ) ) {
	require_once plugin_dir_path( __FILE__ ) .  'lib/phpuri.php'; // For converting relative URIs to absolute 
}



 

global $yarns_reader_db_version;
$yarns_reader_db_version = "1.7"; // Updated database structure 
	//version 1.5 - added tags to 'following' table 
	// version 1.6 changed table text format to utf8mb4 (to support emojis and special characters)
	// version 1.7 add syndication and in_reply_to properties to feed  items




/* Enqueue scripts and styles for the reader page */ 
add_action( 'wp_enqueue_scripts', 'yarns_reader_enqueue_scripts' );
function yarns_reader_enqueue_scripts() {
	//register (not enqueue) scripts so they can be loaded later only if the yarns_reader_page shortcode is used
		//wp_enqueue_script( 'jquery-ui', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.js', array('jquery'), null, true);
		//wp_register_script( 'jquery-ui', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.js', array('jquery'), null, true);
	wp_register_script( 'yarns_reader_js', plugin_dir_url( __FILE__ ).'js/yarns_reader.js', array('jquery'), null, true);

	wp_enqueue_style( 'yarns_reader-style', plugin_dir_url( __FILE__ ).'css/yarns_reader.css' );

	// also enqueue the css for the yarns_reader_admin page in the dashboard
	wp_enqueue_style( 'yarns_reader-style', plugin_dir_url( __FILE__ ).'css/yarns_reader.css' );

	//Add ajax support for the yarns_reader_js script
	wp_localize_script( 'yarns_reader_js', 'yarns_reader_ajax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' )
	));
}

/* Enqueue scripts and styles for the yarns_reader_admin page */
add_action( 'admin_enqueue_scripts', 'yarns_reader_admin_enqueue_scripts' );
function yarns_reader_admin_enqueue_scripts($hook) {
    if ( 'settings_page_yarns_reader_settings' != $hook ) {
        return;
    }

    wp_enqueue_style( 'yarns_reader-style', plugin_dir_url( __FILE__ ).'css/yarns_reader.css' );
}

/* Define what page should be displayed when "Yarns Indie Reader Settings" is clicked in the dashboard*/
function yarns_reader_admin() {
    include('yarns_reader_admin.php');
}

/* Create the menu option "Yarns Indie Reader Settings" */ 
function yarns_reader_admin_actions() {
	/* add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);*/
	add_options_page("Yarns Indie Reader", "Yarns Indie Reader", 1, "yarns_reader_settings", "yarns_reader_admin");
}
/* Hook to run yarns_reader_admin_actions when WordPress generates the admin menu */ 
add_action('admin_menu', 'yarns_reader_admin_actions');


/* Display an admin notice to configure the plugin when newly installed */
/*
function initial_setup_admin_notice() {
    ?>
    <div class="notice notice-info is-dismissible">
        <p><?php _e( 'Yarns Indie Reader needs to be configured (Add a link to the settings page)', 'sample-text-domain' ); ?></p>
    </div>
    <?php
}
*/
	

/*
**
**   Installation/Setup functions
**
*/

function yarns_reader_install() {
	// Activates the plugin and checks for compatible version of WordPress 
	if ( version_compare( get_bloginfo( 'version' ), '2.9', '<' ) ) {
		deactivate_plugins ( basename( __FILE__ ));     // Deactivate plugin
		wp_die( "This plugin requires WordPress version 2.9 or higher." );
	}
		/*
		Determine wordpress version requirements using:
		https://de.wpseek.com/pluginfilecheck/
		*/ 

	//Set up cron job to check for posts
	if ( !wp_next_scheduled( 'yarns_reader_generate_hook' ) ) {            
		wp_schedule_event( time(), 'sixtymins', 'yarns_reader_generate_hook' );
	}
	//Flush rewrite rules - see: https://codex.wordpress.org/Function_Reference/flush_rewrite_rules
	flush_rewrite_rules( false );
}
	

/* Create a new table for the reader settings */ 
function yarns_reader_create_tables() {
	global $wpdb;
	global $yarns_reader_db_version;
	
	// Create table to store log
	$table_log = $wpdb->prefix . "yarns_reader_log";

	$sql = "CREATE TABLE " . $table_log . " (
		id int NOT NULL AUTO_INCREMENT,
		date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		log text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		PRIMARY KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	yarns_reader_log("Created yarns_reader_log table");
	
	// Create table to store list of sites to be followed
	$table_following = $wpdb->prefix . "yarns_reader_following";

	$sql = "CREATE TABLE " . $table_following . " (
		id int NOT NULL AUTO_INCREMENT,
		added datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		siteurl text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		feedurl text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		sitetitle text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		feedtype text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		tags text COLLATE utf8mb4_unicode_ci DEFAULT '',
		PRIMARY KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	yarns_reader_log("Created yarns_reader_following table");
	// Create table to store posts from followed sites

	$table_posts = $wpdb->prefix . "yarns_reader_posts";

	$sql = "CREATE TABLE " . $table_posts . " (
		id int NOT NULL AUTO_INCREMENT,
		feedid int DEFAULT 0 NOT NULL,
		sitetitle text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		siteurl text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		title text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		summary mediumtext COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		content mediumtext COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		published datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		updated datetime DEFAULT '0000-00-00 00:00:00' ,
		authorname text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		authorurl text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		authoravurl text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		permalink text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		location text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		photo text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		posttype text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		viewed boolean DEFAULT FALSE NOT NULL,
		liked text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		replied text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		reposted text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		rsvped text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		syndication text COLLATE utf8mb4_unicode_ci DEFAULT '' ,
		in_reply_to text COLLATE utf8mb4_unicode_ci DEFAULT '' ,

		PRIMARY KEY id (id)
	);";

	
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	yarns_reader_log("Created yarns_reader_posts table");
	yarns_reader_log("updating db version from ".get_site_option('yarns_reader_db_version')." to ". $yarns_reader_db_version);
	update_option( 'yarns_reader_db_version', $yarns_reader_db_version );
	yarns_reader_log("updated db version to ". get_site_option('yarns_reader_db_version'));
}


/* Check if the database version has changed, and update the database if so */
function yarns_reader_update_db_check() {
	global $yarns_reader_db_version;
	if (get_site_option('yarns_reader_db_version') != $yarns_reader_db_version) {
		yarns_reader_create_tables();
	}
}


/* Create the following page */ 
function create_following_page(){
	echo "Creating following page";
    $feedlocation = get_option('yarns_reader_feedlocation');

        if (!get_page_by_title( $feedlocation )){
        $current_user = wp_get_current_user();
         if ( !($current_user instanceof WP_User) )
             wp_die("Couldn't get current user to create follow page");
        $post = array(
                'comment_status' => 'closed', 
                'ping_status' => 'closed', 
                'post_author' => $current_user->ID,
                'post_content' => '[yarns_indie_reader]', 
                'post_name' => $feedlocation, 
                'post_status' => 'publish', 
                'post_title' => 'Yarns Indie Reader', 
                'post_type' => 'page' 
            ); 
            if(wp_insert_post( $post )<1)
                wp_die("Could not create the followpage");
        
        }else{
            wp_die("There is already a page called '" + $feedlocation +"'. Please choose a different name" );
            /* THIS SHOULD BE REPLACED BY FUNCTION TO RENAME THE EXISTING PAGE*/
            
    } 
}




/*
** Defines the interval for the cron job (60 minutes) 
*/
function yarns_reader_cron_definer($schedules){
	/*
	$schedules['fivemins'] = array(
		'interval'=> 300,
		'display'=>  __('Once Every 5 Minutes')
	);

	$schedules['twentymins'] = array(
		'interval'=> 1200,
		'display'=>  __('Once Every 20 Minutes')
	);
	*/

	$schedules['sixtymins'] = array(
		'interval'=> 3600,
		'display'=>  __('Once Every 60 Minutes')
	);
	return $schedules;
}

/*
**
**   Display feed reader page when [yarns_indie_reader] shortcode is used
**
*/

// Function to make the yarns_reader_page shortcode work
function yarns_reader_page_shortcode() {
    yarns_reader_page();
}
add_shortcode('yarns_indie_reader', 'yarns_reader_page_shortcode');


// The Following page, visible on the front end
function yarns_reader_page(){
	// enqueue the reader js (which was registered previously)
	wp_enqueue_script( 'yarns_reader_js');
	?> <div id="yarns_reader"> 
		<div id="yarns_reader_header">
			<?php echo '<img src="'. plugins_url('images/yarns_heading.png', __FILE__ ).'" alt="Yarns Indie Reader">'; ?> 

		</div>
		<?php 
	if (current_user_can('read')){  // Only logged in users can access this page
		// Show controls for visitors with permission
		if(current_user_can( 'edit_pages')){ // Only editors or admins can access the controls to manage subscriptions and refresh the feed
			?>
			<div class="yarns_reader-controls">
				
				<button id="yarns_reader-button-feed">View feed</button>
				<button id="yarns_reader-button-subscriptions" >Manage subscriptions</button>
				<button id="yarns_reader-button-refresh" >Update feed</button> 
				<time id="yarns_reader-last-updated"></time>
			</div><!--.yarns_reader-controls-->

			<?php yarns_reader_subscription_editor(); ?>


			<?php

		}
		
		// SHow the feed for logged in visitors
		?>
		<div id = "yarns_reader-feed-container"></div><!--#yarns_reader-feed-container-->


		<button  id="yarns_reader-load-more">Load more...</button> 
		<?php 
		// Add placeholder box for 'full' content 
		?>
		<div id="yarns_reader-full-box" class="yarns_reader-hidden">
  			<span id="yarns_reader-full-close" >&times;</span>
  			<div id="yarns_reader-full-content"></div>
  		</div><!--#yarns_reader-full-box-->
 
		<?php



	} else {
		// The visitor is not logged in
		?>
		<div id = "yarns_reader-feed-error">Sorry, you must be logged in to view this page.</div>
		<?php
	}
	?> </div><!--#yarns_reader--> <?php
}

/* Show interface for adding/removing/editing subscriptions */
function yarns_reader_subscription_editor(){
	?>
	<div id="yarns_reader-subscriptions" class="yarns_reader-hidden">
	<div id="yarns_reader-addSite-form"  method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<h2>Add a subscription</h2><br>
        <input type="text" name="yarns_reader-siteurl" value="" size="30"  placeholder = "Enter a website address to subscribe to its content."></input><br>
        <button id="yarns_reader-addSite-findFeeds" >Find feeds</button>
		<br><br>
		<div id="yarns_reader-choose-feed" class="yarns_reader-hidden">
		<form class="yarns_reader-feedpicker yarns_reader-hidden "></form>
		<label for="yarns_reader-feedurl">Feed URL </label><input type="text" name="yarns_reader-feedurl" value="" size="30"><br>
		<label for="yarns_reader-sitetitle">Site Title </label><input type="text" name="yarns_reader-sitetitle" value="" size="30"><br>
		<div>Feed type:<span class="yarns_reader-feed-type"></span></div>
		<button id="yarns_reader-addSite-submit" >Submit</button>
		</div><!--#yarns_reader-choose-feed-->
	</div>

	<h2> Manage subscriptions </h2><br>
	<div id="yarns_reader-subscription-list">
	</div><!--#yarns_reader-subscription-list-->
	
	</div><!--#yarns_reader-subscriptions-->
	<?php
}

// Displays a full list of the subscriptions 
add_action( 'wp_ajax_yarns_reader_subscription_list', 'yarns_reader_subscription_list' );
function yarns_reader_subscription_list(){
	// Start with a blank page
	$subscriptions_list = '';
	global $wpdb;
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'yarns_reader_following` 
		ORDER BY  `sitetitle`  ASC;'  
	);
	// Note: Currently this sorts the subscription list as case sensitive. It would be better to sort case insensitive. 

	// Generate HTML for each subscription item
	if ( !empty( $items ) ) { 			
		foreach ( $items as $item ) {
			$subscriptions_list .= '<div class="yarns_reader-subscription-item" data-id="'.$item->id.'">';
			$subscriptions_list .= '<span class="yarns_reader-subscription-title">'.$item->sitetitle.'</span>';
			$subscriptions_list .= '<button class="yarns_reader-button-edit-subscription yarns_reader-hidden" >Edit</button>';
			$subscriptions_list .= '<button class="yarns_reader-button-unsubscribe">Unsubscribe</button>';

			$subscriptions_list .= '<div class="yarns_reader-subscription-options yarns_reader-hidden">';
			$subscriptions_list .= '<h3>Edit this subscription</h3>';
			$subscriptions_list .='<label for="yarns_reader-sitetitle">Site Title </label><input type="text" name="yarns_reader-sitetitle" value="'.$item->sitetitle.'" size="30"><br>';
			$subscriptions_list .='<label for="yarns_reader-feedurl">Feed URL </label><input type="text" name="yarns_reader-feedurl" value="'.$item->feedurl.'" size="30"><br>';
			$subscriptions_list .='<label for="yarns_reader-siteurl">Site URL </label><input type="text" name="yarns_reader-siteurl" value="'.$item->siteurl.'" size="30"><br>';
			$subscriptions_list .='<label for="yarns_reader-siteurl">Site URL </label><input type="text" name="yarns_reader-feedtags" value="'.$item->tags.'" size="30"><br>';
			$subscriptions_list .= '<button class="yarns_reader-subscription-save">Save changes</button>';
			$subscriptions_list .= '</div><!--.yarns_reader-subscription-options-->';
			$subscriptions_list .= '</div><!--.yarns_reader-subscription-item-->'; 
		}
	} else {
		$subscriptions_list .= "You have not subscribed to any sites yet! Click 'Add Subscription' to do so.";
	}
	echo $subscriptions_list;
	wp_die();
}

add_action( 'wp_ajax_yarns_reader_get_lastupdated', 'yarns_reader_get_lastupdated' );
function yarns_reader_get_lastupdated(){
	echo user_datetime(get_option('yarns_reader_last_updated'));
	wp_die();
}



/* Return html for reply actions if the user has permission to create posts */
function yarns_reader_reply_actions($post_type, $liked, $replied, $rsvped){
	$the_reply_actions = '';
	if(current_user_can( 'publish_posts')){ // Reply actions are only available to users who can publish posts
		$the_reply_actions .= '<div class ="yarns_reader-response-controls"> ';
		if ($liked){
			//this post has been liked
			$the_reply_actions .= '<button class="yarns_reader-like yarns_reader-response-exists " data-link="'.$liked.'"></button>';
		} else {
			// this post has not been liked
			$the_reply_actions .= '<button class="yarns_reader-like " ></button>';
		}

		if ($replied){
			//this post has been liked
			$the_reply_actions .= '<button class="yarns_reader-reply yarns_reader-response-exists " data-link="'.$replied.'"></button>';
		} else {
			// this post has not been liked
			$the_reply_actions .= '<button class="yarns_reader-reply " ></button>';
		}

		// Disabling RSVP replies for now — Will add again later.
		/*
		if ($post_type=="h-event"){
		
			$the_reply_actions .= '<span class ="yarns_reader-rsvp-buttons">RSVP:';
			$the_reply_actions .= '<button class="yarns_reader-rsvp-yes ">Yes</button>';
			$the_reply_actions .= '<button class="yarns_reader-rsvp-no ">No</button>';
			$the_reply_actions .= '<button class="yarns_reader-rsvp-interested ">Interested</button>';
			$the_reply_actions .= '<button class="yarns_reader-rsvp-yes ">Maybe</button>';
			$the_reply_actions .= '</span>';
		}
		*/
		$the_reply_actions .= '<div class="yarns_reader-reply-input yarns_reader-hidden">';
			$the_reply_actions .= '<input class ="yarns_reader-reply-title" placeholder = "Enter a reply title (if desired)"></input>';
			$the_reply_actions .= '<textarea class ="yarns_reader-reply-text" placeholder="Enter your reply here" ></textarea>';
			$the_reply_actions .= '<button class="yarns_reader-reply-submit ">Submit</button>';
		$the_reply_actions .= '</div>';

		$the_reply_actions .= '</div><!--.yarns_reader-response-controls-->';
	}
	return $the_reply_actions;
}







/*
**
**   Major functions
		- yarns_reader_display_page (ajax)
		- yarns_reader_add_feeditem 
		- yarns_reader_new_subscription (ajax)
		- yarns_reader_response (ajax)
		- yarns_reader_findFeeds (ajax)
		- yarns_reader_aggregator 

		yarns_reader_fetch_feed
		yarns_reader_fetch_hfeed

**	
*/

/* Returns a single page for display */ 
add_action( 'wp_ajax_yarns_reader_display_page', 'yarns_reader_display_page' );
function yarns_reader_display_page($pagenum){
	// load a page into variable $the_page then echo it
	$pagenum = $_POST['pagenum'];

	// Access databsae
	global $wpdb;
	// Start at post 0, show 15 posts per page
	$length = 15;
	//$table_following = $wpdb->prefix . "yarns_reader_posts";
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'yarns_reader_posts` 
		ORDER BY  `published` DESC 
		LIMIT '.($pagenum*$length).' , '.$length.';'
	);

	//Iterate through all the posts in the database. Display the first 15 
	if ( !empty( $items ) ) { 
		//$the_page = '<div class="yarns_reader-page-'.$pagenum.'">';
		$the_page = '<div class="yarns_reader-test">';
		//$the_page = "Page ". $pagenum ;
		foreach ( $items as $item ) {
			if ($item->posttype=="h-event"){
				$display_type = "Event";
			} else {
				$display_type = ""; // unless specified, do not display post type
			}
			/*
			//// Deprecated for now since replies are displayed on the buttons themselves
			//Generate html for responses (likes, replies) if they exist
			$the_replies ='';
			if ($item->liked) { $the_replies .= '<a href="'.get_permalink($item->liked).'">like</a>'; }
			if ($item->replied) { $the_replies .= '<a href="'.get_permalink($item->replied).'">reply</a>'; }
			if ($item->rsvped){ $the_replies .= '<a href="'.get_permalink($item->rsvped).'">rsvp</a>'; }
			*/

			// Display an individual feed item
			$the_page .= '<div class="yarns_reader-feed-item" data-id="'.$item->id.'">'; // container for each feed item
		
			$the_page .= '<div class="yarns_reader-item-meta">'; // container for meta 
			$the_page .= '<a class="yarns_reader-item-authorname" href="'.$item->siteurl.'" target="_blank">'.$item->sitetitle.'</a> '; // authorname
			$the_page .= '<a class="yarns_reader-item-date" href="'.$item->permalink.'" target="_blank">at '.user_datetime($item->published).'</a>'; // date/permalink
			//$the_page .= '<span class="yarns_reader-item-type">'.$display_type.'</span>'; // display type
			
			$the_page .= '</div><!--.yarns_reader-item-meta-->';
			if ($item->title !=""){
				$the_page .= '<a class="yarns_reader-item-title" href="'.$item->permalink.'" target="_blank">'.$item->title.'</a>';
			}

			$tidy = new tidy();
			$config = array(
     			//'doctype' => 'omit',
     			'quote-marks' =>true,
			);
			$clean_summary = $tidy->repairString($item->summary, $config, 'utf8'); // Need to set to utf8 other quotation marks display incorrectly

			if (strlen($item->photo)>0 ){
				//the feed item has a photo

				//Only show the photo if there is not already a photo in the summary
				// To avoid showing duplicate photos when different sizes are 
				if (!findPhotos($clean_summary)[0]){
				//if (findPhotos($clean_summary)[0] != $item->photo) {
					
					$the_page .='<div class="yarns_reader-item-photo">';
					$the_page .='<img src="'.$item->photo.'">';
					$the_page .='</div>'; 					
				}
				
			}
			
			$the_page .='<div class="yarns_reader-item-summary">';
			if (strlen($item->in_reply_to)>0){
				$the_page .= '<div class="yarns_reader-item-reply">reply to post at <a href = "'.$item->in_reply_to.'" target="_blank">'.parse_url($item->in_reply_to,PHP_URL_HOST).'</a></div>';
			}
			// Clean up the summary using tidy()

			
		

			$the_page .= $clean_summary;

			// Display 'read more' button if there is additional content beyond the summary)
			if (strlen($item->content)>0 ){
				$the_page .='<a class="yarns_reader-item-more">See more...</a><!--.yarns_reader-item-more-->'; 
				//$the_page .='<div class="yarns_reader-item-content yarns_reader-hidden">';
				//$the_page .= $item->content;
				//$the_page .= '</div><!--.yarns_reader-item-content-->';
			}

			$the_page .='</div><!--.yarns_reader-item-summary-->'; 

			$the_page .= '<div class="yarns_reader-item-meta2">'; // container for meta2
			if (strlen($item->location)>0){
				$the_page .= '<div class="yarns_reader-item-location">'.$item->location.'</div>'; // display type
			}

			if (strlen($item->syndication)>0){
				$syndication_items = json_decode($item->syndication);
				$the_page .= '<div class="yarns_reader-item-syndication">';
				foreach($syndication_items as $item){

					$the_page .= '<a href ="'.$item.'" target="_blank">'.parse_url($item,PHP_URL_HOST) .'</a>';
				}
				$the_page .= '</div>';
			}

			$the_page .= '</div><!--.yarns_reader-item-meta2-->';

			

			$the_page .= '<div class="yarns_reader-item-response">'.yarns_reader_reply_actions($item->posttype,$item->liked,$item->replied,$item->rsvped);
			$the_page .= '</div><!--.yarns_reader-item-response-->';

			$the_page .= '</div><!--.yarns_reader-feed-item-->';	
		}
		$the_page .= '</div><!--yarns_reader-page-'.$pagenum.'-->';
		echo $the_page;

	} else {
		// There are no more items!
		echo "finished";
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}


/* Returns FULL CONTENT for a single item display */ 
add_action( 'wp_ajax_yarns_reader_display_full_content', 'yarns_reader_display_full_content' );
function yarns_reader_display_full_content($id){
	$id = $_POST['id'];

	global $wpdb;

	$query = "SELECT * FROM ".$wpdb->prefix."yarns_reader_posts WHERE id = '".$id."'";
 

	$item = $wpdb->get_row($query);
		

	if ( !empty( $item ) ) { 
		$the_page .= '<div class="yarns_reader-feed-item" data-id="'.$item->id.'">'; // container for each feed item
			
		$the_page .= '<div class="yarns_reader-item-meta">'; // container for meta 
		$the_page .= '<a class="yarns_reader-item-authorname" href="'.$item->siteurl.'">'.$item->sitetitle.'</a> '; // authorname
		$the_page .= '<a class="yarns_reader-item-date" href="'.$item->permalink.'">at '.user_datetime($item->published).'</a>'; // date/permalink
		$the_page .= '<span class="yarns_reader-item-type">'.$display_type.'</span>'; // display type
		$the_page .= '</div><!--.yarns_reader-item-meta-->';
		if ($item->title !=""){
			$the_page .= '<a class="yarns_reader-item-title" href="'.$item->permalink.'">'.$item->title.'</a>';
		}

		$the_page .='<div class="yarns_reader-item-content">';
		if (strlen($item->in_reply_to)>0){
				$the_page .= '<div class="yarns_reader-item-reply">reply to post at <a href = "'.$item->in_reply_to.'" target="_blank">'.parse_url($item->in_reply_to,PHP_URL_HOST).'</a></div>';

			}
		$the_page .= $item->content;
		$the_page .= '</div><!--.yarns_reader-item-content-->';
		
		$the_page .= '<div class="yarns_reader-item-meta2">'; // container for meta2
		if (strlen($item->location)>0){
			$the_page .= '<div class="yarns_reader-item-location">'.$item->location.'</div>'; // display type
		}

		if (strlen($item->syndication)>0){
			$syndication_items = json_decode($item->syndication);
			$the_page .= '<div class="yarns_reader-item-syndication">';
			foreach($syndication_items as $item){

				$the_page .= '<a href ="'.$item.'" >'.parse_url($item,PHP_URL_HOST) .'</span>';
			}
			$the_page .= '</div>';
		}

		$the_page .= '</div><!--.yarns_reader-item-meta2-->';		

		$the_page .= '<div class="yarns_reader-item-response">'.yarns_reader_reply_actions($item->posttype,$item->liked,$item->replied,$item->rsvped);

		echo $the_page;

		$the_page .= '</div><!--.yarns_reader-feed-item-->';
		} else {
			// something went wrong fetching the item
			
			$lastquery = $wpdb->last_query;
			$lasterror = $wpdb->last_error;
			yarns_reader_log("could not fetch post with id = " . $id);
			yarns_reader_log($lastquery);
			yarns_reader_log($lasterror);
			echo "error";
		}	

	wp_die();	
}

 
/* Add a post to the yarns_reader_posts table in the database */
function yarns_reader_add_feeditem($feedid,$title,$summary,$content,$published=0,$updated=0,$authorname='',$authorurl='',$avurl='',$permalink,$location,$photo,$type,$siteurl,$sitetitle,$syndication='',$in_reply_to=''){

	//yarns_reader_log("adding post: ".$permalink.": ".$title);
	global $wpdb;


	//yarns_reader_log("published = " . $published);
	if($published < 1){
		$published = time();
	}
	//yarns_reader_log("published2 = " . $published);
	$published = date('Y-m-d H:i:s',$published);
	//yarns_reader_log("published3 = " . $published);
	$updated = date('Y-m-d H:i:s',$updated);



	// If there is no featured photo defined, search for a first image in the content
	if ($photo == ''){
		$photos = findPhotos($content);	

		if (count($photos)<1){
			$photos = findPhotos($summary);
		}
		if (count($photos) > 0){
			$photo = $photos[0];
		}
	} 
	

	

	


	// if there is no summary, copy the content to the summary
	if (strlen($summary) <1) {
		$summary = $content;
	} 
	// truncate the summary if it is too long or contains more than one image
	if (strlen(strip_tags($summary))>500 || count($photos)>1) { 
		// since we're truncating, copy summary to content if content is empty
		if (strlen($content)<1){
			$content = $summary;
		}
		//Strip imgs and any tags not listed as allowed below:  ("<a><p><br>.....")
		$summary = substr(strip_tags($summary,"<a><p><br><blockquote><b><code><del><em><h1><h2><h3><h4><h5><h6><li><ol><ul><pre><q><strong><sub><u>"),0,500) . "..."; 
	}



	// If the summary is exactly the same as the content, then empty content since it is redundant
	if ($summary == $content){
		$content = "";
	}


	

	//If the author url is not known, then just use the site url
	if (empty($authorurl)){$authorurl = $siteurl;}

	// Add the post (if it doesn't already exist)
	$table_name = $wpdb->prefix . "yarns_reader_posts";
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE permalink LIKE \"".$permalink."\";")<1){
		$rows_affected = $wpdb->insert( $table_name,
			array(	
				'feedid'=>$feedid,
				'sitetitle'=>$sitetitle,
				'siteurl'=>$siteurl,
				'title' => $title,
				'summary'=> $summary,
				'content' => $content,
				'published'=> $published,
				'updated'=> $updated,
				'authorname' => $authorname,
				'authorurl' => $authorurl,
				'authoravurl' => $avurl,
				'permalink' => $permalink,
				'location'=> $location,
				'syndication'=> $syndication,
				'in_reply_to'=> $in_reply_to,
				'photo'=> $photo,
				'posttype' => $type,
				'viewed' =>false,
			 ) );

		if($rows_affected == false){
			$lastquery = $wpdb->last_query;
			$lasterror = $wpdb->last_error;
			yarns_reader_log("could not insert post into database!");
			yarns_reader_log($lastquery);
			yarns_reader_log($lasterror);


			die("could not insert post into database!" .$permalink.": ".$title);
		}else{
			yarns_reader_log("added ".$permalink.": ".$title);
		}
	}else{
		//yarns_reader_log("post already exists: " .$permalink.": ".$title);
	}	

}

/*
** Add a new subscription
*/ 
add_action( 'wp_ajax_yarns_reader_new_subscription', 'yarns_reader_new_subscription' );
function yarns_reader_new_subscription($siteurl, $feedurl, $sitetitle, $feedtype){
	$siteurl = $_POST['siteurl'];
	$feedurl = $_POST['feedurl'];
	$sitetitle = $_POST['sitetitle'];
	$feedtype = $_POST['feedtype'];
	yarns_reader_log("adding subscription: ". $feedurl. " @ ". $sitetitle);
	
	global $wpdb;
	$table_name = $wpdb->prefix . "yarns_reader_following";
	// Check if the site is already subscribed
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE feedurl LIKE \"".$feedurl."\";")<1){
		//yarns_reader_log("no duplicate found");
		$rows_affected = $wpdb->insert( $table_name,
			array(
				'added' => current_time( 'mysql' ), 
				'siteurl' => $siteurl,
				'feedurl' => $feedurl,
				'sitetitle'=> $sitetitle,
				'feedtype' => $feedtype,
			 ) );
		if($rows_affected == false){
			yarns_reader_log("Could not insert subscription info into database.");
			echo "Could not insert subscription info into database.";
			die("Could not insert subscription info into database.");
		}else{
			yarns_reader_log("Success! Added subscription: ". $feedurl. " @ ". $sitetitle);
			echo "Success! Added subscription: ". $feedurl. " @ ". $sitetitle;
		}
	}else{
		yarns_reader_log("You are already subscribed to " . $feedurl);
		echo "You are already subscribed to " . $feedurl;
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Post a response to an item from the feed
*/
add_action( 'wp_ajax_yarns_reader_response', 'yarns_reader_response' );
function yarns_reader_response ($response_type, $in_reply_to, $reply_to_title, $reply_to_content, $title, $content, $feed_item_id){	
	$response_type = $_POST['response_type'];
	$in_reply_to = $_POST['in_reply_to'];
	$reply_to_title = $_POST['reply_to_title'];
	$reply_to_content = $_POST['reply_to_content'];
	$post_title = $_POST['title'];
	$post_content = $_POST['content'];
	$feed_item_id = $_POST['feed_item_id'];

	yarns_reader_log("Response: " . $response_type . " — " . $in_reply_to);

	//If the post has a title, then we will use the title for display. If not, use the url
	// for display
	if ($reply_to_title){
		$display_title = $reply_to_title;
	} else {
		$display_title = $in_reply_to;
	}
	$attribution = '<em><a href="'.$in_reply_to.'" rel="in-reply-to" class="u-in-reply-to h-cite" target="_blank">'.$display_title.'</a></em>';

	if ($response_type == "reply" ){
		$content = "Reply to " . $attribution . "<br><br>"; 
		$content .= $post_content;
		$title = $post_title;
		if (strlen($title)>0){
			//If the reply has a title, it's post kind is 'post'
			$post_type = "post";
			$post_kind = "article";
		} else {
			//If the reply does not have a title, it's post kind is 'aside'	
			$post_type = "aside";
			$post_kind = "note";		
		}
	} elseif ($response_type == "like"){
		$content = "Liked " . $attribution;
		$title = "Liked ". $display_title;
		$post_type = "link";
		$post_kind = "like";
	} 

	//yarns_reader_log("posting response");

	/* 
	Note regarding post_status:
	potential values (that are useful for this plugin) are: 
	- draft
	- publish
	- pending
	- private
	*/
	$my_post = array(
		'post_title' => $title,
		'post_content' => $content,
		'post_status' => 'draft',
	);
	$the_post_id = wp_insert_post( $my_post );
	yarns_reader_log(" response posted: " . $the_post_id);
	//__update_post_meta( $the_post_id, 'my-custom-field', 'my_custom_field_value' );
	
	// Set the post format once the post has been created 
	set_post_format( $the_post_id , $post_type);
	
	// If the post kinds plugin is installed, set the post kind
	if (function_exists('set_post_kind')){
 		set_post_kind( $the_post_id , $post_kind);
	}

	// Set the appropriate response tag for the feed reader item
	global $wpdb;
	// Start at post 0, show 15 posts per page
	//$table_following = $wpdb->prefix . "yarns_reader_posts";
	if ($response_type == "like"){
		$wpdb->update($wpdb->prefix . 'yarns_reader_posts', array('liked'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
	} else if ($response_type == "reply") {
		$wpdb->update($wpdb->prefix . 'yarns_reader_posts', array('replied'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
	} else if ($response_type == "rsvp") {
		$wpdb->update($wpdb->prefix . 'yarns_reader_posts', array('rsvped'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
	}
	
	echo get_permalink($the_post_id);
	wp_die(); // this is required to terminate immediately and return a proper response
}





/*
** Identify and return feeds at a given url 
*/
add_action( 'wp_ajax_yarns_reader_findFeeds', 'yarns_reader_findFeeds' );
//add_action( 'wp_ajax_read_me_later', array( $this, 'yarns_reader_new_subscription' ) );
function yarns_reader_findFeeds($siteurl){
	$siteurl = $_POST['siteurl'];
	yarns_reader_log("Searching for feeds and site title at ". $siteurl);

	$html = file_get_contents($siteurl); //get the html returned from the following url
	$dom = new DOMDocument();
	libxml_use_internal_errors(TRUE); //disable libxml errors
	if(!empty($html)){ //if any html is actually returned
		$dom->loadHTML($html);
		$thetitle = $dom->getElementsByTagName("title");
		$returnArray[] = array("type"=>"title", "data"=>$thetitle[0]->nodeValue);		
		
		$website_links = $dom->getElementsByTagName("link");
		// Check for feeds as <link> elements
		$found_hfeed = FALSE; 

		if($website_links->length > 0){
			foreach($website_links as $row){
				// Convert relative feed URL to absolute URL if needed
				$feedurl = phpUri::parse($siteurl)->join($row->getAttribute("href"));

				if (isRSS($row->getAttribute("type"))){ // Check for rss feeds first
					//Return the feed type and absolute feed url 
					$returnArray[] = array("type"=>$row->getAttribute("type"), "data"=>$feedurl);
				}
				elseif($row->getAttribute("type")=='text/html'){ // Check for h-feeds declared using a <link> tag
					$returnArray[] = array("type"=>"h-feed", "data"=>$feedurl);
					$found_hfeed = TRUE; // H-feed has been found, so we stop looking
				}
			}
		}

		// Also here check for h-feed in the actual html
			$mf = Mf2\parse($html,$siteurl);
			$output_log ="Output: <br>";

			foreach ($mf['items'] as $mf_item) {
				if ($found_hfeed == FALSE) {
					$output_log .= "A {$mf_item['type'][0]} called {$mf_item['properties']['name'][0]}<br>";
					if ("{$mf_item['type'][0]}"=="h-feed"||  // check 1
						"{$mf_item['type'][0]}"=="h-entry"){
						//Found an h-feed (probably)
						$returnArray[] = array("type"=>"h-feed", "data"=>$siteurl);
						$found_hfeed = TRUE; 
					} else {
						$output_log .="Searching children... <br>";

						foreach($mf_item['children'] as $child){
							if ($found_hfeed == FALSE) {
								$output_log .= "A CHILD {$child['type'][0]} called {$child['properties']['name'][0]}<br>";
								if ("{$child['type'][0]}"=="h-feed"|| // check 1
									"{$child['type'][0]}"=="h-entry"){
									//Found an h-feed (probably)
									$returnArray[] = array("type"=>"h-feed", "data"=>$siteurl);
									$found_hfeed = TRUE; 
								}
							}
						}
					}
				}

			}
		echo json_encode($returnArray);
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}


/*
** Unsubscribe from a feed
*/
add_action( 'wp_ajax_yarns_reader_unsubscribe', 'yarns_reader_unsubscribe' );
function yarns_reader_unsubscribe ($feed_id){	
//$wpdb->update($wpdb->prefix . 'yarns_reader_posts', array('liked'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
//$wpdb->delete( 'table', array( 'ID' => 1 ) );
	$feed_id = $_POST['feed_id'];
	global $wpdb;
	$unsubscribe = $wpdb->delete( $wpdb->prefix . "yarns_reader_following", array( 'ID' => $feed_id ) );
	echo $unsubscribe;
	wp_die();
}


/*
** Aggregator function (run using cron job or by refresh button)
*/
add_action( 'wp_ajax_yarns_reader_aggregator', 'yarns_reader_aggregator' );
function yarns_reader_aggregator() {
	yarns_reader_log("aggregator was run");
	global $wpdb;
	$table_following = $wpdb->prefix . "yarns_reader_following";
	//Iterate through each item in the 'following' table.
	foreach( $wpdb->get_results("SELECT * FROM ".$table_following.";") as $key => $row) {
		$feedurl = $row->feedurl;
		$feedtype = $row->feedtype;
		$sitetitle = $row->sitetitle;
		$siteurl = $row->siteurl;
		$feedid = $row->id;
		yarns_reader_log("checking for new posts in ". $feedurl);

		/****   RSS FEEDS ****/
		if (isRss($feedtype)){
			//yarns_reader_log ($feedurl . " is an rss feed (or atom, etc)");

			$feed = yarns_reader_fetch_feed($feedurl,$feedtype);
		
			if(is_wp_error($feed)){
				//yarns_reader_log($feed->get_error_message());
				trigger_error($feed->get_error_message());
				yarns_reader_log("Feed read Error: ".$feed->get_error_message());
			} else {
				//yarns_reader_log("Feed read success.");
			}
			$feed->enable_cache(false);
			$feed->strip_htmltags(false);   
			$items = $feed->get_items();
			usort($items,'date_sort');
			foreach ($items as $item){
				//yarns_reader_log("got ".$item->get_title()." from ". $item->get_feed()->get_title()."<br/>");
				$title = $item->get_title();


				$summary = html_entity_decode ($item->get_description());
				$content = html_entity_decode ($item->get_content());
				//ORIGINAL $published=$item->get_date("U");
				$published=$item->get_date('U');

				$updated=0;
				//Remove the title if it is equal to the post content (e.g. asides, notes, microblogs)
				$title = clean_the_title($title,$content);
				// Several fallback options to set author name/site title
				$authorname = $sitetitle;  // This uses the site title entered by the user (not the site title specified in the site feed)
				$avurl='';
				$permalink=$item->get_permalink();
				$location='';
				$photo='';
				$type='rss';
				try{
					yarns_reader_add_feeditem($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle);
				}catch(Exception $e){
					yarns_reader_log("Exception occured: ".$e->getMessage());
				}
			}

		} /****  H-FEEDS ****/ 
		elseif ($feedtype == "h-feed"){
			$feed = yarns_reader_fetch_hfeed($feedurl,$feedtype);
			foreach ($feed as $item){
				
				$title = $item['name'];
				$summary=$item['summary'];
				$content=$item['content'];
				$published = $item['published'];
				$updated = $item['updated'];
				$authorname = $item['author'];
				$authorurl = ""; // none for now — TO DO: Fetch avatar from h-card if possible, or just use siteurl
				$avurl = ""; // none for now — TO DO: Fetch avatar from h-card if possible
				$permalink = $item['url'];
				$location=$item['location'];
				$photo=$item['photo'];
				$syndication = $item['syndication'];
				$in_reply_to = $item['in-reply-to'];
				//$location = $item['location'];
				
				$siteurl=$item['siteurl'];
				$feedurl = $url;
				$type = $item['type'];
				

				try{
					yarns_reader_add_feeditem($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle,$syndication,$in_reply_to);
					//yarns_reader_add_feeditem($permalink, $title, $content, $authorname, $authorurl, $time, $avurl, $siteurl, $feedurl, $type);
				}catch(Exception $e){
					yarns_reader_log("Exception occured: ".$e->getMessage());
				}
			}
			//yarns_reader_log ($feedurl . " is an h-feed");
		}
		remove_filter( 'wp_feed_cache_transient_lifetime', 'yarns_reader_feed_time' );
	}
	// Store the time of the this update
	$update_time = date('Y-m-d H:i:s', time());
	yarns_reader_log("Aggregator finished at ". $update_time);
	update_option( 'yarns_reader_last_updated', $update_time);

	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Fetch an RSS FEED and return its content
*/
function yarns_reader_fetch_feed($url,$feedtype) {
	require_once (ABSPATH . WPINC . '/class-feed.php');
	$feed = new SimplePie();
	//yarns_reader_log("Url is fetchable");
	$feed->set_feed_url($url);
	$feed->set_cache_class('WP_Feed_Cache');
	$feed->set_file_class('WP_SimplePie_File');
	$feed->set_cache_duration(30);
	$feed->enable_cache(false);
	$feed->set_useragent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.77 Safari/535.7');//some people don't like us if we're not a real boy	
	$feed->init();
	$feed->handle_content_type();
	
	//yarns_reader_log("Feed:".print_r($feed,true));

	if ( $feed->error() )
		$errstring = implode("\n",$feed->error());
		//if(strlen($errstring) >0){ $errstring = $feed['data']['error'];}
		if(stristr($errstring,"XML error")){
			yarns_reader_log('simplepie-error-malfomed: '.$errstring.'<br/><code>'.htmlspecialchars ($url).'</code>');
		}elseif(strlen($errstring) >0){
			yarns_reader_log('simplepie-error: '.$errstring);
		}else{
			//yarns_reader_log('simplepie-error-empty: '.print_r($feed,true).'<br/><code>'.htmlspecialchars ($url).'</code>');
		}
	return $feed;
}

/*
** Fetch an H-FEED and return its content
*/
function yarns_reader_fetch_hfeed($url,$feedtype) {
	//Parse microformats at the feed-url
	$mf = Mf2\fetch($url);
	//Identify the h-feed within the parsed MF2
	$hfeed = "";
	$hfeed_path = "children"; // (default) in most cases, items within the h-feed will be 'children' 
	//Check if one of the top-level items is an h-feed
	foreach ($mf['items'] as $mf_item) {
		if ($hfeed == "") {
			if ("{$mf_item['type'][0]}"=="h-feed"){
				$hfeed = $mf_item;				
			} else {
				//If h-feed has not been found, check for a child-level h-feed
				foreach($mf_item['children'] as $child){
					if ($hfeed == "") {
						if ("{$child['type'][0]}"=="h-feed"){
							$hfeed = $child;	
						}
					}
				}
			}
		}
	}
	//If no h-feed was found, check for h-entries. If h-entry is found, then consider its parent the h-feed
	foreach ($mf['items'] as $mf_item) {
		if ($hfeed == "") {
			if ("{$mf_item['type'][0]}"=="h-entry"){
				$hfeed = $mf;		
				$hfeed_path	="items";
			} else {
				//If h-entries have not been found, check for a child-level h-entry
				foreach($mf_item['children'] as $child){
					if ($hfeed == "") {
						if ("{$child['type'][0]}"=="h-entry"){
							$hfeed = $mf_item;		
						}
					}
				}
			}
		}
	}

	//At this point, only proceed if h-feed has been found
	if ($hfeed == "") {
		//do nothing
		yarns_reader_log("no h-feed found");
	} else {
		//yarns_reader_log("Parsing h-feed at: ".$hpath);
		$site_url = $url;
		$counter = 0;

		foreach ($hfeed[$hfeed_path] as $item) {
			if ("{$item['type'][0]}" == 'h-entry' ||
				"{$item['type'][0]}" == 'h-event' )
			{
			// Only parse supported types (h-entry, h-event... more to come)
			
			$item_name = "{$item['properties']['name'][0]}";
			$item_type = "{$item['type'][0]}";
			$item_summary = "{$item['properties']['summary'][0]}";
			$item_published = strtotime("{$item['properties']['published'][0]}");
			$item_updated = strtotime("{$item['properties']['updated'][0]}");
			$item_location = "{$item['properties']['location'][0]['value']}";
				//Note that location can be an h-card, but this script just gets the string value
			$item_url = "{$item['properties']['url'][0]}";
			$item_uid = "{$item['properties']['uid'][0]}";
			if ("{$item['properties']['syndication']}"){
				$syndication =  array();
				foreach ($item['properties']['syndication'] as $syndication_item) {
					$syndication[] = $syndication_item;		
				}
				$item_syndication = json_encode($syndication);
			}
			//$item_syndication = json_encode("{$item['properties']['syndication']}");
			$item_photo = "{$item['properties']['photo'][0]}";
			$item_inreplyto = "{$item['properties']['in-reply-to'][0]}";
			$item_author = "{$item['properties']['author'][0]}";

			//handle h-entry
			if ("{$item['type'][0]}" == "h-entry"){
				//yarns_reader_log ("found h-entry");
				//yarns_reader_log("{$item['properties']['url'][0]}");
				$item_content = "{$item['properties']['content'][0]['html']}";
				$item_content_plain = "{$item['properties']['content'][0]['value']}";

				$log_entry .= "<li>item_content = ". $item_content ."</li>";
				$log_entry .= "<li>item_content_plain = ". $item_content_plain ."</li>";
			}

			//handle h-event
			if ("{$item['type'][0]}" == "h-event"){
				//yarns_reader_log ("found h-event");
				//yarns_reader_log("{$item['properties']['url'][0]}");

				$item_featured = "{$item['properties']['featured'][0]}";
				$item_content = "";
				//When
				//Where    //Note that location can be an h-card
				//Host
				//Summary
				//Image
			}

			// Log the parsed h-feed  for debugging
			//yarns_reader_log($log_entry);


			//Remove the title if it is equal to the post content (e.g. asides, notes, microblogs)
				$item_name = clean_the_title($item_name,$item_content,$item_content_plain);

			$hfeed_items [] = array (
				"name"=>$item_name,
				"type"=>$item_type,
				"summary"=>$item_summary,
				"content"=>$item_content,
				"location"=>$item_location,
				"photo"=>$item_photo,
				"published" =>$item_published,
				"updated" =>$item_updated,
				"url"=>$item_url,
				"uid"=>$item_url,
				"author"=>$item_author,
				"syndication"=>$item_syndication,
				"in-reply-to"=>$item_inreplyto,
				"author"=>$item_author,
				"featured"=>$item_featured,
				"siteurl"=>$site_url
			);

			}

			
		}

		//yarns_reader_log("hfeed items = " . json_decode($hfeed_items));

		return $hfeed_items;
	}
	return "h-feed found";
}



/*
**
**   Utility functions
**
*/


function findPhotos($html){
	//yarns_reader_log("Finding photos...");
	$dom = new DOMDocument;
	$dom->loadHTML($html);
	foreach ($dom->getElementsByTagName('img') as $node) {
		/*$src= $node->getAttribute( 'src' );
		$alt = $node->getAttribute( 'alt' );
		//yarns_reader_log("src = ." . $src . " | alt = " . $alt);
		$returnArray[] = array("src"=>$src, "alt"=>$alt);		
		*/
		// SImplying to just return url for image
		$returnArray[] = $node->getAttribute('src');
	}
	return $returnArray;
	//yarns_reader_log(serialize($returnArray));
} 

//Log changes to the database (adding sites, fetching posts, etc.)
function yarns_reader_log($message){
	global $wpdb;
	$table_name = $wpdb->prefix . 'yarns_reader_log';

	$wpdb->insert( 
		$table_name, 
		array( 
			'date' => current_time( 'mysql' ), 
			'log' => $message, 
		) 
	);

}

/* Remove titles for posts where the title is equal to the content (e.g. notes, asides, microblogs) */
//In many rss feeds and h-feeds, the only indication of whether a title is redunant is that it duplicates 
function clean_the_title($title,$content,$content_plain=''){
	$clean_title = html_entity_decode($title); // First convert html entities to text (to ensure consistent comparision)
	$clean_title = strip_tags(rtrim($clean_title,".")); // remove trailing "..."
	$clean_title = strip_tags(trim($clean_title)); // remove white space on either side
	$clean_title = htmlentities($clean_title, ENT_QUOTES); // Convert quotation marks to HTML entities
	$clean_title = str_replace(array("\r", "\n"), '', $clean_title); // remove line breaks from title
	$clean_title = str_replace("&nbsp;", "", $clean_title); // replace $nbsp; with a space character
	$clean_title = str_replace(array("\r", "\n"), '', $clean_title); // remove line breaks from title
	
	$clean_content = html_entity_decode($content); // First convert html entities to text (to ensure consistent comparision)
	$clean_content = strip_tags(rtrim($clean_content,".")); // remove trailing "..."
	$clean_content = strip_tags(trim($clean_content)); // remove white space on either side
	$clean_content = htmlentities($clean_content, ENT_QUOTES); // Convert quotation marks to HTML entities
	$clean_content = str_replace("&nbsp;", "", $clean_content); // replace $nbsp; with a space character
	$clean_content = str_replace(array("\r", "\n"), '', $clean_content); // remove line breaks from CONTENT
	if (strpos($clean_content,$clean_title)===0 ){
		$title="";
	} 
	// Also compare to content_plain if it exists.  ($content_plain is a plain text version, whereas $content has html)
	if ($content_plain != ''){
		$clean_content = html_entity_decode($content_plain); // First convert html entities to text (to ensure consistent comparision)
		$clean_content = strip_tags(rtrim($clean_content,".")); // remove trailing "..."
		$clean_content = strip_tags(trim($clean_content)); // remove white space on either side
		$clean_content = htmlentities($clean_content, ENT_QUOTES); // Convert quotation marks to HTML entities
		$clean_content = str_replace("&nbsp;", "", $clean_content); // replace $nbsp; with a space character
		$clean_content = str_replace(array("\r", "\n"), '', $clean_content); // remove line breaks from CONTENT
		//yarns_reader_log("COMPARISON #2: plain content: [". $clean_content . "] and title: [".$clean_title."]");
		if (strpos($clean_content,$clean_title)===0 ){
			$title="";
		} 
	}
	return $title;
}


/* 
** Returns true is the feed is of type rss 
*/

function isRSS($feedtype){
	$rssTypes = array ('application/rss+xml','application/atom+xml','application/rdf+xml','application/xml','text/xml','text/xml','text/rss+xml','text/atom+xml');
    if (in_array($feedtype,$rssTypes)){
    	return True;
    }
}


/* 
** Returns a datetime formatted with user's preferences (for timezone, date format, & time format)
*/

function User_datetime($datetime){
	$output_log = "Converting datetime... \n";
	$user_datetime_format = get_option('date_format') . " " . get_option('time_format');
	$user_datetime = get_date_from_gmt($datetime, $user_datetime_format);
	return $user_datetime ;
}



/*
**
**   Runs upon deactivating the plugin
**
*/
function yarns_reader_deactivate() {
	// on deactivation remove the cron job 
	if ( wp_next_scheduled( 'yarns_reader_generate_hook' ) ) {
		wp_clear_scheduled_hook( 'yarns_reader_generate_hook' );
	}
	yarns_reader_log("deactivated plugin");
}


/*
**
**   Hooks and filters
**
*/

/* Functions to run upon installation */ 
register_activation_hook(__FILE__,'yarns_reader_install');
register_activation_hook(__FILE__,'yarns_reader_create_tables');
add_filter('cron_schedules','yarns_reader_cron_definer');
add_action( 'yarns_reader_generate_hook', 'yarns_reader_aggregator' );

/* Functions to run upon deactivation */ 
register_deactivation_hook( __FILE__, 'yarns_reader_deactivate' );



/* Check if the database version has changed when plugin is updated */ 
add_action( 'plugins_loaded', 'yarns_reader_update_db_check' );

/* Hook to display admin notice */ 
/*
add_action( 'admin_notices', 'initial_setup_admin_notice' );
*/
	
?>