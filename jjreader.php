<?php
/**
 * Plugin Name: JJ Reader
 * Plugin URI: jackjamieson.net
 * Description: JJ Reader is a feed reader. You can subscribe to blogs and websites, view their updates in a feed, then post likes and replies directly to your WordPress site. Replies and likes are marked-up with microformats 2, so posts created with this plugin will support webmentions. 

 * Version: 0.1
 * Author: Jack Jamieson
 * Author URI: http://jackjamieson.net
 * Text Domain: jjreader
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
 *	(http://acegiak.machinespirit.net/2012/01/25/whisperfollow/).
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



 

global $jjreader_db_version;
$jjreader_db_version = "1.7"; // Updated database structure 
	//version 1.5 - added tags to 'following' table 
	// version 1.6 changed table text format to utf8mb4 (to support emojis and special characters)
	// version 1.7 add syndication and in_reply_to properties to feed  items




/* Enqueue scripts and styles for the reader page */ 
add_action( 'wp_enqueue_scripts', 'jjreader_enqueue_scripts' );
function jjreader_enqueue_scripts() {
	//register (not enqueue) scripts so they can be loaded later only if the jjreader_page shortcode is used
		//wp_enqueue_script( 'jquery-ui', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.js', array('jquery'), null, true);
		//wp_register_script( 'jquery-ui', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.js', array('jquery'), null, true);
	wp_register_script( 'jjreader_js', plugin_dir_url( __FILE__ ).'js/jjreader.js', array('jquery'), null, true);

	wp_enqueue_style( 'jjreader-style', plugin_dir_url( __FILE__ ).'css/jjreader.css' );
	//Add ajax support for the jjreader_js script
	wp_localize_script( 'jjreader_js', 'jjreader_ajax', array(
		'ajax_url' => admin_url( 'admin-ajax.php' )
	));
}

/* Define what page should be displayed when "JJ Reader Settings" is clicked in the dashboard*/
function jjreader_admin() {
    include('jjreader_admin.php');
}

/* Create the menu option "JJ Reader Settings" */ 
function jjreader_admin_actions() {
	/* add_options_page( $page_title, $menu_title, $capability, $menu_slug, $function);*/
	add_options_page("JJ Reader Settings", "JJ Reader Settings", 1, "jjreader_settings", "jjreader_admin");
}
/* Hook to run jjreader_admin_actions when WordPress generates the admin menu */ 
add_action('admin_menu', 'jjreader_admin_actions');


/* Display an admin notice to configure the plugin when newly installed */
/*
function initial_setup_admin_notice() {
    ?>
    <div class="notice notice-info is-dismissible">
        <p><?php _e( 'JJ Reader needs to be configured (Add a link to the settings page)', 'sample-text-domain' ); ?></p>
    </div>
    <?php
}
*/
	

/*
**
**   Installation/Setup functions
**
*/

function jjreader_install() {
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
	if ( !wp_next_scheduled( 'jjreader_generate_hook' ) ) {            
		wp_schedule_event( time(), 'sixtymins', 'jjreader_generate_hook' );
	}
	//Flush rewrite rules - see: https://codex.wordpress.org/Function_Reference/flush_rewrite_rules
	flush_rewrite_rules( false );
}
	

/* Create a new table for the reader settings */ 
function jjreader_create_tables() {
	global $wpdb;
	global $jjreader_db_version;
	
	// Create table to store log
	$table_log = $wpdb->prefix . "jjreader_log";

	$sql = "CREATE TABLE " . $table_log . " (
		id int NOT NULL AUTO_INCREMENT,
		date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		log text COLLATE utf8mb4_unicode_ci DEFAULT '' NOT NULL,
		PRIMARY KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	jjreader_log("Created jjreader_log table");
	
	// Create table to store list of sites to be followed
	$table_following = $wpdb->prefix . "jjreader_following";

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
	
	jjreader_log("Created jjreader_following table");
	// Create table to store posts from followed sites

	$table_posts = $wpdb->prefix . "jjreader_posts";

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
	jjreader_log("Created jjreader_posts table");
	jjreader_log("updating db version from ".get_site_option('jjreader_db_version')." to ". $jjreader_db_version);
	update_option( 'jjreader_db_version', $jjreader_db_version );
	jjreader_log("updated db version to ". get_site_option('jjreader_db_version'));
}


/* Check if the database version has changed, and update the database if so */
function jjreader_update_db_check() {
	global $jjreader_db_version;
	if (get_site_option('jjreader_db_version') != $jjreader_db_version) {
		jjreader_create_tables();
	}
}


/* Create the following page */ 
function create_following_page(){
	echo "Creating following page";
    $feedlocation = get_option('jjreader_feedlocation');

        if (!get_page_by_title( $feedlocation )){
        $current_user = wp_get_current_user();
         if ( !($current_user instanceof WP_User) )
             wp_die("Couldn't get current user to create follow page");
        $post = array(
                'comment_status' => 'closed', 
                'ping_status' => 'closed', 
                'post_author' => $current_user->ID,
                'post_content' => '[jjreader_page]', 
                'post_name' => $feedlocation, 
                'post_status' => 'publish', 
                'post_title' => 'JJ Reader', 
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
function jjreader_cron_definer($schedules){
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
**   Display feed reader page when [jjreader_page] shortcode is used
**
*/

// Function to make the jjreader_page shortcode work
function jjreader_page_shortcode() {
    jjreader_page();
}
add_shortcode('jjreader_page', 'jjreader_page_shortcode');


// The Following page, visible on the front end
function jjreader_page(){
	// enqueue the reader js (which was registered previously)
	wp_enqueue_script( 'jjreader_js');
	?> <div id="jjreader"> 
		<div id="jjreader_header">
			<?php echo '<img src="'. plugins_url('images/yarns_heading.png', __FILE__ ).'" alt="Yarns Indie Reader">'; ?> 

		</div>
		<?php 
	if (current_user_can('read')){  // Only logged in users can access this page
		// Show controls for visitors with permission
		if(current_user_can( 'edit_pages')){ // Only editors or admins can access the controls to manage subscriptions and refresh the feed
			?>
			<div class="jjreader-controls">
				
				<button id="jjreader-button-feed">View feed</button>
				<button id="jjreader-button-subscriptions" >Manage subscriptions</button>
				<button id="jjreader-button-refresh" >Update feed</button> 
				<time id="jjreader-last-updated"></time>
			</div><!--.jjreader-controls-->

			<?php jjreader_subscription_editor(); ?>


			<?php

		}
		
		// SHow the feed for logged in visitors
		?>
		<div id = "jjreader-feed-container"></div><!--#jjreader-feed-container-->


		<button  id="jjreader-load-more">Load more...</button> 
		<?php 
		// Add placeholder box for 'full' content 
		?>
		<div id="jjreader-full-box" class="jjreader-hidden">
  			<span id="jjreader-full-close" >&times;</span>
  			<div id="jjreader-full-content"></div>
  		</div><!--#jjreader-full-box-->
 
		<?php



	} else {
		// The visitor is not logged in
		?>
		<div id = "jjreader-feed-error">Sorry, you must be logged in to view this page.</div>
		<?php
	}
	?> </div><!--#jjreader--> <?php
}

/* Show interface for adding/removing/editing subscriptions */
function jjreader_subscription_editor(){
	?>
	<div id="jjreader-subscriptions" class="jjreader-hidden">
	<div id="jjreader-addSite-form"  method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<h2>Add a subscription</h2><br>
        <label for="jjreader-siteurl">Site URL </label><input type="text" name="jjreader-siteurl" value="" size="30"><br>
        <button id="jjreader-addSite-findFeeds" >Find feeds</button>
		<br><br>
		<div id="jjreader-choose-feed" class="jjreader-hidden">
		<form class="jjreader-feedpicker jjreader-hidden "></form>
		<label for="jjreader-feedurl">Feed URL </label><input type="text" name="jjreader-feedurl" value="" size="30"><br>
		<label for="jjreader-sitetitle">Site Title </label><input type="text" name="jjreader-sitetitle" value="" size="30"><br>
		<div>Feed type:<span class="jjreader-feed-type"></span></div>
		<button id="jjreader-addSite-submit" >Submit</button>
		</div><!--#jjreader-choose-feed-->
	</div>

	<h2> Manage subscriptions </h2><br>
	<div id="jjreader-subscription-list">
	</div><!--#jjreader-subscription-list-->
	
	</div><!--#jjreader-subscriptions-->
	<?php
}

// Displays a full list of the subscriptions 
add_action( 'wp_ajax_jjreader_subscription_list', 'jjreader_subscription_list' );
function jjreader_subscription_list(){
	// Start with a blank page
	$subscriptions_list = '';
	global $wpdb;
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'jjreader_following` 
		ORDER BY  `sitetitle`  ASC;'  
	);
	// Note: Currently this sorts the subscription list as case sensitive. It would be better to sort case insensitive. 

	// Generate HTML for each subscription item
	if ( !empty( $items ) ) { 			
		foreach ( $items as $item ) {
			$subscriptions_list .= '<div class="jjreader-subscription-item" data-id="'.$item->id.'">';
			$subscriptions_list .= '<span class="jjreader-subscription-title">'.$item->sitetitle.'</span>';
			$subscriptions_list .= '<button class="jjreader-button-edit-subscription jjreader-hidden" >Edit</button>';
			$subscriptions_list .= '<button class="jjreader-button-unsubscribe">Unsubscribe</button>';

			$subscriptions_list .= '<div class="jjreader-subscription-options jjreader-hidden">';
			$subscriptions_list .= '<h3>Edit this subscription</h3>';
			$subscriptions_list .='<label for="jjreader-sitetitle">Site Title </label><input type="text" name="jjreader-sitetitle" value="'.$item->sitetitle.'" size="30"><br>';
			$subscriptions_list .='<label for="jjreader-feedurl">Feed URL </label><input type="text" name="jjreader-feedurl" value="'.$item->feedurl.'" size="30"><br>';
			$subscriptions_list .='<label for="jjreader-siteurl">Site URL </label><input type="text" name="jjreader-siteurl" value="'.$item->siteurl.'" size="30"><br>';
			$subscriptions_list .='<label for="jjreader-siteurl">Site URL </label><input type="text" name="jjreader-feedtags" value="'.$item->tags.'" size="30"><br>';
			$subscriptions_list .= '<button class="jjreader-subscription-save">Save changes</button>';
			$subscriptions_list .= '</div><!--.jjreader-subscription-options-->';
			$subscriptions_list .= '</div><!--.jjreader-subscription-item-->'; 
		}
	} else {
		$subscriptions_list .= "You have not subscribed to any sites yet! Click 'Add Subscription' to do so.";
	}
	echo $subscriptions_list;
	wp_die();
}

add_action( 'wp_ajax_jjreader_get_lastupdated', 'jjreader_get_lastupdated' );
function jjreader_get_lastupdated(){
	echo user_datetime(get_option('jjreader_last_updated'));
	wp_die();
}



/* Return html for reply actions if the user has permission to create posts */
function jjreader_reply_actions($post_type, $liked, $replied, $rsvped){
	$the_reply_actions = '';
	if(current_user_can( 'publish_posts')){ // Reply actions are only available to users who can publish posts
		$the_reply_actions .= '<div class ="jjreader-response-controls"> ';
		if ($liked){
			//this post has been liked
			$the_reply_actions .= '<button class="jjreader-like jjreader-response-exists " data-link="'.$liked.'"></button>';
		} else {
			// this post has not been liked
			$the_reply_actions .= '<button class="jjreader-like " ></button>';
		}

		if ($replied){
			//this post has been liked
			$the_reply_actions .= '<button class="jjreader-reply jjreader-response-exists " data-link="'.$replied.'"></button>';
		} else {
			// this post has not been liked
			$the_reply_actions .= '<button class="jjreader-reply " ></button>';
		}

		// Disabling RSVP replies for now — Will add again later.
		/*
		if ($post_type=="h-event"){
		
			$the_reply_actions .= '<span class ="jjreader-rsvp-buttons">RSVP:';
			$the_reply_actions .= '<button class="jjreader-rsvp-yes ">Yes</button>';
			$the_reply_actions .= '<button class="jjreader-rsvp-no ">No</button>';
			$the_reply_actions .= '<button class="jjreader-rsvp-interested ">Interested</button>';
			$the_reply_actions .= '<button class="jjreader-rsvp-yes ">Maybe</button>';
			$the_reply_actions .= '</span>';
		}
		*/
		$the_reply_actions .= '<div class="jjreader-reply-input jjreader-hidden">';
			$the_reply_actions .= '<input class ="jjreader-reply-title" placeholder = "Enter a reply title (if desired)"></input>';
			$the_reply_actions .= '<textarea class ="jjreader-reply-text" placeholder="Enter your reply here" ></textarea>';
			$the_reply_actions .= '<button class="jjreader-reply-submit ">Submit</button>';
		$the_reply_actions .= '</div>';

		$the_reply_actions .= '</div><!--.jjreader-response-controls-->';
	}
	return $the_reply_actions;
}







/*
**
**   Major functions
		- jjreader_display_page (ajax)
		- jjreader_add_feeditem 
		- jjreader_new_subscription (ajax)
		- jjreader_response (ajax)
		- jjreader_findFeeds (ajax)
		- jjreader_aggregator 

		jjreader_fetch_feed
		jjreader_fetch_hfeed

**	
*/

/* Returns a single page for display */ 
add_action( 'wp_ajax_jjreader_display_page', 'jjreader_display_page' );
function jjreader_display_page($pagenum){
	// load a page into variable $the_page then echo it
	$pagenum = $_POST['pagenum'];

	// Access databsae
	global $wpdb;
	// Start at post 0, show 15 posts per page
	$length = 15;
	//$table_following = $wpdb->prefix . "jjreader_posts";
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'jjreader_posts` 
		ORDER BY  `published` DESC 
		LIMIT '.($pagenum*$length).' , '.$length.';'
	);

	//Iterate through all the posts in the database. Display the first 15 
	if ( !empty( $items ) ) { 
		//$the_page = '<div class="jjreader-page-'.$pagenum.'">';
		$the_page = '<div class="jjreader-test">';
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
			$the_page .= '<div class="jjreader-feed-item" data-id="'.$item->id.'">'; // container for each feed item
		
			$the_page .= '<div class="jjreader-item-meta">'; // container for meta 
			$the_page .= '<a class="jjreader-item-authorname" href="'.$item->siteurl.'">'.$item->sitetitle.'</a> '; // authorname
			$the_page .= '<a class="jjreader-item-date" href="'.$item->permalink.'">at '.user_datetime($item->published).'</a>'; // date/permalink
			//$the_page .= '<span class="jjreader-item-type">'.$display_type.'</span>'; // display type
			
			$the_page .= '</div><!--.jjreader-item-meta-->';
			if ($item->title !=""){
				$the_page .= '<a class="jjreader-item-title" href="'.$item->permalink.'">'.$item->title.'</a>';
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
					
					$the_page .='<div class="jjreader-item-photo">';
					$the_page .='<img src="'.$item->photo.'">';
					$the_page .='</div>'; 					
				}
				
			}
			
			$the_page .='<div class="jjreader-item-summary">';
			if (strlen($item->in_reply_to)>0){
				$the_page .= '<div class="jjreader-item-reply">reply to post at <a href = "'.$item->in_reply_to.'">'.parse_url($item->in_reply_to,PHP_URL_HOST).'</a></div>';
			}
			// Clean up the summary using tidy()

			
		

			$the_page .= $clean_summary;

			// Display 'read more' button if there is additional content beyond the summary)
			if (strlen($item->content)>0 ){
				$the_page .='<a class="jjreader-item-more">See more...</a><!--.jjreader-item-more-->'; 
				//$the_page .='<div class="jjreader-item-content jjreader-hidden">';
				//$the_page .= $item->content;
				//$the_page .= '</div><!--.jjreader-item-content-->';
			}

			$the_page .='</div><!--.jjreader-item-summary-->'; 

			$the_page .= '<div class="jjreader-item-meta2">'; // container for meta2
			if (strlen($item->location)>0){
				$the_page .= '<div class="jjreader-item-location">'.$item->location.'</div>'; // display type
			}

			if (strlen($item->syndication)>0){
				$syndication_items = json_decode($item->syndication);
				$the_page .= '<div class="jjreader-item-syndication">';
				foreach($syndication_items as $item){

					$the_page .= '<a href ="'.$item.'">'.parse_url($item,PHP_URL_HOST) .'</span>';
				}
				$the_page .= '</div>';
			}

			$the_page .= '</div><!--.jjreader-item-meta2-->';

			

			$the_page .= '<div class="jjreader-item-response">'.jjreader_reply_actions($item->posttype,$item->liked,$item->replied,$item->rsvped);
			$the_page .= '</div><!--.jjreader-item-response-->';

			$the_page .= '</div><!--.jjreader-feed-item-->';	
		}
		$the_page .= '</div><!--jjreader-page-'.$pagenum.'-->';
		echo $the_page;

	} else {
		// There are no more items!
		echo "finished";
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}


/* Returns FULL CONTENT for a single item display */ 
add_action( 'wp_ajax_jjreader_display_full_content', 'jjreader_display_full_content' );
function jjreader_display_full_content($id){
	$id = $_POST['id'];

	global $wpdb;

	$query = "SELECT * FROM ".$wpdb->prefix."jjreader_posts WHERE id = '".$id."'";
 

	$item = $wpdb->get_row($query);
		

	if ( !empty( $item ) ) { 
		$the_page .= '<div class="jjreader-feed-item" data-id="'.$item->id.'">'; // container for each feed item
			
		$the_page .= '<div class="jjreader-item-meta">'; // container for meta 
		$the_page .= '<a class="jjreader-item-authorname" href="'.$item->siteurl.'">'.$item->sitetitle.'</a> '; // authorname
		$the_page .= '<a class="jjreader-item-date" href="'.$item->permalink.'">at '.user_datetime($item->published).'</a>'; // date/permalink
		$the_page .= '<span class="jjreader-item-type">'.$display_type.'</span>'; // display type
		$the_page .= '</div><!--.jjreader-item-meta-->';
		if ($item->title !=""){
			$the_page .= '<a class="jjreader-item-title" href="'.$item->permalink.'">'.$item->title.'</a>';
		}

		$the_page .='<div class="jjreader-item-content">';
		if (strlen($item->in_reply_to)>0){
				$the_page .= '<div class="jjreader-item-reply">reply to post at <a href = "'.$item->in_reply_to.'">'.parse_url($item->in_reply_to,PHP_URL_HOST).'</a></div>';

			}
		$the_page .= $item->content;
		$the_page .= '</div><!--.jjreader-item-content-->';
		
		$the_page .= '<div class="jjreader-item-meta2">'; // container for meta2
		if (strlen($item->location)>0){
			$the_page .= '<div class="jjreader-item-location">'.$item->location.'</div>'; // display type
		}

		if (strlen($item->syndication)>0){
			$syndication_items = json_decode($item->syndication);
			$the_page .= '<div class="jjreader-item-syndication">';
			foreach($syndication_items as $item){

				$the_page .= '<a href ="'.$item.'">'.parse_url($item,PHP_URL_HOST) .'</span>';
			}
			$the_page .= '</div>';
		}

		$the_page .= '</div><!--.jjreader-item-meta2-->';		

		$the_page .= '<div class="jjreader-item-response">'.jjreader_reply_actions($item->posttype,$item->liked,$item->replied,$item->rsvped);

		echo $the_page;

		$the_page .= '</div><!--.jjreader-feed-item-->';
		} else {
			// something went wrong fetching the item
			
			$lastquery = $wpdb->last_query;
			$lasterror = $wpdb->last_error;
			jjreader_log("could not fetch post with id = " . $id);
			jjreader_log($lastquery);
			jjreader_log($lasterror);
			echo "error";
		}	

	wp_die();	
}

 
/* Add a post to the jjreader_posts table in the database */
function jjreader_add_feeditem($feedid,$title,$summary,$content,$published=0,$updated=0,$authorname='',$authorurl='',$avurl='',$permalink,$location,$photo,$type,$siteurl,$sitetitle,$syndication='',$in_reply_to=''){

	//jjreader_log("adding post: ".$permalink.": ".$title);
	global $wpdb;


	//jjreader_log("published = " . $published);
	if($published < 1){
		$published = time();
	}
	//jjreader_log("published2 = " . $published);
	$published = date('Y-m-d H:i:s',$published);
	//jjreader_log("published3 = " . $published);
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
	$table_name = $wpdb->prefix . "jjreader_posts";
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
			jjreader_log("could not insert post into database!");
			jjreader_log($lastquery);
			jjreader_log($lasterror);


			die("could not insert post into database!" .$permalink.": ".$title);
		}else{
			jjreader_log("added ".$permalink.": ".$title);
		}
	}else{
		//jjreader_log("post already exists: " .$permalink.": ".$title);
	}	

}

/*
** Add a new subscription
*/ 
add_action( 'wp_ajax_jjreader_new_subscription', 'jjreader_new_subscription' );
function jjreader_new_subscription($siteurl, $feedurl, $sitetitle, $feedtype){
	$siteurl = $_POST['siteurl'];
	$feedurl = $_POST['feedurl'];
	$sitetitle = $_POST['sitetitle'];
	$feedtype = $_POST['feedtype'];
	jjreader_log("adding subscription: ". $feedurl. " @ ". $sitetitle);
	
	global $wpdb;
	$table_name = $wpdb->prefix . "jjreader_following";
	// Check if the site is already subscribed
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE feedurl LIKE \"".$feedurl."\";")<1){
		//jjreader_log("no duplicate found");
		$rows_affected = $wpdb->insert( $table_name,
			array(
				'added' => current_time( 'mysql' ), 
				'siteurl' => $siteurl,
				'feedurl' => $feedurl,
				'sitetitle'=> $sitetitle,
				'feedtype' => $feedtype,
			 ) );
		if($rows_affected == false){
			jjreader_log("Could not insert subscription info into database.");
			echo "Could not insert subscription info into database.";
			die("Could not insert subscription info into database.");
		}else{
			jjreader_log("Success! Added subscription: ". $feedurl. " @ ". $sitetitle);
			echo "Success! Added subscription: ". $feedurl. " @ ". $sitetitle;
		}
	}else{
		jjreader_log("You are already subscribed to " . $feedurl);
		echo "You are already subscribed to " . $feedurl;
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Post a response to an item from the feed
*/
add_action( 'wp_ajax_jjreader_response', 'jjreader_response' );
function jjreader_response ($response_type, $in_reply_to, $reply_to_title, $reply_to_content, $title, $content, $feed_item_id){	
	$response_type = $_POST['response_type'];
	$in_reply_to = $_POST['in_reply_to'];
	$reply_to_title = $_POST['reply_to_title'];
	$reply_to_content = $_POST['reply_to_content'];
	$post_title = $_POST['title'];
	$post_content = $_POST['content'];
	$feed_item_id = $_POST['feed_item_id'];

	jjreader_log("Response: " . $response_type . " — " . $in_reply_to);

	//If the post has a title, then we will use the title for display. If not, use the url
	// for display
	if ($reply_to_title){
		$display_title = $reply_to_title;
	} else {
		$display_title = $in_reply_to;
	}
	$attribution = '<em><a href="'.$in_reply_to.'" rel="in-reply-to" class="u-in-reply-to h-cite">'.$display_title.'</a></em>';

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

	//jjreader_log("posting response");
	$my_post = array(
		'post_title' => $title,
		'post_content' => $content,
		'post_status' => 'draft',
	);
	$the_post_id = wp_insert_post( $my_post );
	jjreader_log(" response posted: " . $the_post_id);
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
	//$table_following = $wpdb->prefix . "jjreader_posts";
	if ($response_type == "like"){
		$wpdb->update($wpdb->prefix . 'jjreader_posts', array('liked'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
	} else if ($response_type == "reply") {
		$wpdb->update($wpdb->prefix . 'jjreader_posts', array('replied'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
	} else if ($response_type == "rsvp") {
		$wpdb->update($wpdb->prefix . 'jjreader_posts', array('rsvped'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
	}
	
	echo get_permalink($the_post_id);
	wp_die(); // this is required to terminate immediately and return a proper response
}





/*
** Identify and return feeds at a given url 
*/
add_action( 'wp_ajax_jjreader_findFeeds', 'jjreader_findFeeds' );
//add_action( 'wp_ajax_read_me_later', array( $this, 'jjreader_new_subscription' ) );
function jjreader_findFeeds($siteurl){
	$siteurl = $_POST['siteurl'];
	jjreader_log("Searching for feeds and site title at ". $siteurl);

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
add_action( 'wp_ajax_jjreader_unsubscribe', 'jjreader_unsubscribe' );
function jjreader_unsubscribe ($feed_id){	
//$wpdb->update($wpdb->prefix . 'jjreader_posts', array('liked'=>get_permalink($the_post_id)), array('id'=>$feed_item_id));
//$wpdb->delete( 'table', array( 'ID' => 1 ) );
	$feed_id = $_POST['feed_id'];
	global $wpdb;
	$unsubscribe = $wpdb->delete( $wpdb->prefix . "jjreader_following", array( 'ID' => $feed_id ) );
	echo $unsubscribe;
	wp_die();
}


/*
** Aggregator function (run using cron job or by refresh button)
*/
add_action( 'wp_ajax_jjreader_aggregator', 'jjreader_aggregator' );
function jjreader_aggregator() {
	jjreader_log("aggregator was run");
	global $wpdb;
	$table_following = $wpdb->prefix . "jjreader_following";
	//Iterate through each item in the 'following' table.
	foreach( $wpdb->get_results("SELECT * FROM ".$table_following.";") as $key => $row) {
		$feedurl = $row->feedurl;
		$feedtype = $row->feedtype;
		$sitetitle = $row->sitetitle;
		$siteurl = $row->siteurl;
		$feedid = $row->id;
		jjreader_log("checking for new posts in ". $feedurl);

		/****   RSS FEEDS ****/
		if (isRss($feedtype)){
			//jjreader_log ($feedurl . " is an rss feed (or atom, etc)");

			$feed = jjreader_fetch_feed($feedurl,$feedtype);
		
			if(is_wp_error($feed)){
				//jjreader_log($feed->get_error_message());
				trigger_error($feed->get_error_message());
				jjreader_log("Feed read Error: ".$feed->get_error_message());
			} else {
				//jjreader_log("Feed read success.");
			}
			$feed->enable_cache(false);
			$feed->strip_htmltags(false);   
			$items = $feed->get_items();
			usort($items,'date_sort');
			foreach ($items as $item){
				//jjreader_log("got ".$item->get_title()." from ". $item->get_feed()->get_title()."<br/>");
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
					jjreader_add_feeditem($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle);
				}catch(Exception $e){
					jjreader_log("Exception occured: ".$e->getMessage());
				}
			}

		} /****  H-FEEDS ****/ 
		elseif ($feedtype == "h-feed"){
			$feed = jjreader_fetch_hfeed($feedurl,$feedtype);
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
					jjreader_add_feeditem($feedid,$title,$summary,$content,$published,$updated,$authorname,$authorurl,$avurl,$permalink,$location,$photo,$type,$siteurl,$sitetitle,$syndication,$in_reply_to);
					//jjreader_add_feeditem($permalink, $title, $content, $authorname, $authorurl, $time, $avurl, $siteurl, $feedurl, $type);
				}catch(Exception $e){
					jjreader_log("Exception occured: ".$e->getMessage());
				}
			}
			//jjreader_log ($feedurl . " is an h-feed");
		}
		remove_filter( 'wp_feed_cache_transient_lifetime', 'jjreader_feed_time' );
	}
	// Store the time of the this update
	$update_time = date('Y-m-d H:i:s', time());
	jjreader_log("Aggregator finished at ". $update_time);
	update_option( 'jjreader_last_updated', $update_time);

	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Fetch an RSS FEED and return its content
*/
function jjreader_fetch_feed($url,$feedtype) {
	require_once (ABSPATH . WPINC . '/class-feed.php');
	$feed = new SimplePie();
	//jjreader_log("Url is fetchable");
	$feed->set_feed_url($url);
	$feed->set_cache_class('WP_Feed_Cache');
	$feed->set_file_class('WP_SimplePie_File');
	$feed->set_cache_duration(30);
	$feed->enable_cache(false);
	$feed->set_useragent('Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/535.7 (KHTML, like Gecko) Chrome/16.0.912.77 Safari/535.7');//some people don't like us if we're not a real boy	
	$feed->init();
	$feed->handle_content_type();
	
	//jjreader_log("Feed:".print_r($feed,true));

	if ( $feed->error() )
		$errstring = implode("\n",$feed->error());
		//if(strlen($errstring) >0){ $errstring = $feed['data']['error'];}
		if(stristr($errstring,"XML error")){
			jjreader_log('simplepie-error-malfomed: '.$errstring.'<br/><code>'.htmlspecialchars ($url).'</code>');
		}elseif(strlen($errstring) >0){
			jjreader_log('simplepie-error: '.$errstring);
		}else{
			//jjreader_log('simplepie-error-empty: '.print_r($feed,true).'<br/><code>'.htmlspecialchars ($url).'</code>');
		}
	return $feed;
}

/*
** Fetch an H-FEED and return its content
*/
function jjreader_fetch_hfeed($url,$feedtype) {
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
		jjreader_log("no h-feed found");
	} else {
		//jjreader_log("Parsing h-feed at: ".$hpath);
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
				//jjreader_log ("found h-entry");
				//jjreader_log("{$item['properties']['url'][0]}");
				$item_content = "{$item['properties']['content'][0]['html']}";
				$item_content_plain = "{$item['properties']['content'][0]['value']}";

				$log_entry .= "<li>item_content = ". $item_content ."</li>";
				$log_entry .= "<li>item_content_plain = ". $item_content_plain ."</li>";
			}

			//handle h-event
			if ("{$item['type'][0]}" == "h-event"){
				//jjreader_log ("found h-event");
				//jjreader_log("{$item['properties']['url'][0]}");

				$item_featured = "{$item['properties']['featured'][0]}";
				$item_content = "";
				//When
				//Where    //Note that location can be an h-card
				//Host
				//Summary
				//Image
			}

			// Log the parsed h-feed  for debugging
			//jjreader_log($log_entry);


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

		//jjreader_log("hfeed items = " . json_decode($hfeed_items));

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
	//jjreader_log("Finding photos...");
	$dom = new DOMDocument;
	$dom->loadHTML($html);
	foreach ($dom->getElementsByTagName('img') as $node) {
		/*$src= $node->getAttribute( 'src' );
		$alt = $node->getAttribute( 'alt' );
		//jjreader_log("src = ." . $src . " | alt = " . $alt);
		$returnArray[] = array("src"=>$src, "alt"=>$alt);		
		*/
		// SImplying to just return url for image
		$returnArray[] = $node->getAttribute('src');
	}
	return $returnArray;
	//jjreader_log(serialize($returnArray));
} 

//Log changes to the database (adding sites, fetching posts, etc.)
function jjreader_log($message){
	global $wpdb;
	$table_name = $wpdb->prefix . 'jjreader_log';

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
		//jjreader_log("COMPARISON #2: plain content: [". $clean_content . "] and title: [".$clean_title."]");
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
function jjreader_deactivate() {
	// on deactivation remove the cron job 
	if ( wp_next_scheduled( 'jjreader_generate_hook' ) ) {
		wp_clear_scheduled_hook( 'jjreader_generate_hook' );
	}
	jjreader_log("deactivated plugin");
}


/*
**
**   Hooks and filters
**
*/

/* Functions to run upon installation */ 
register_activation_hook(__FILE__,'jjreader_install');
register_activation_hook(__FILE__,'jjreader_create_tables');
add_filter('cron_schedules','jjreader_cron_definer');
add_action( 'jjreader_generate_hook', 'jjreader_aggregator' );

/* Functions to run upon deactivation */ 
register_deactivation_hook( __FILE__, 'jjreader_deactivate' );



/* Check if the database version has changed when plugin is updated */ 
add_action( 'plugins_loaded', 'jjreader_update_db_check' );

/* Hook to display admin notice */ 
/*
add_action( 'admin_notices', 'initial_setup_admin_notice' );
*/
	
?>