<?php
/**
 * Plugin Name: Jack Jamieson reader
 * Plugin URI: jackjamieson.net
 * Description: This is the beginning of my reader plugin.  
 * Version: 0.0
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
	Portions of code are modified from Ashton McAllan's WhisperFollow plugin. 
	(http://acegiak.machinespirit.net/2012/01/25/whisperfollow/).
*/ 

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

require_once('jjreader_responses.php');


global $jjreader_db_version;
$jjreader_db_version = "1.0a";

/* Enqueue scripts and styles for the reader page */ 
add_action( 'wp_enqueue_scripts', 'jjreader_enqueue_scripts' );
function jjreader_enqueue_scripts() {
	wp_enqueue_script( 'jquery-ui', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.js', array('jquery'), null, true);
	wp_enqueue_script( 'jjreader_js', plugin_dir_url( __FILE__ ).'js/jjreader.js', array('jquery'), null, true);
	

	wp_enqueue_style( 'jquery-ui-style', plugin_dir_url( __FILE__ ).'jqueryUI/jquery-ui.min.css' );
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
function initial_setup_admin_notice() {
    ?>
    <div class="notice notice-info is-dismissible">
        <p><?php _e( 'JJ Reader needs to be configured (Add a link to the settings page)', 'sample-text-domain' ); ?></p>
    </div>
    <?php
}
	
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
		wp_schedule_event( time(), 'fivemins', 'jjreader_generate_hook' );
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
		log text DEFAULT '' NOT NULL,
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
		siteurl text DEFAULT '' NOT NULL,
		feedurl text DEFAULT '' NOT NULL,
		sitetitle text DEFAULT '' NOT NULL,
		feedtype text DEFAULT '' NOT NULL,
		PRIMARY KEY id (id)
	);";

	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	
	jjreader_log("Created jjreader_following table");
	// Create table to store posts from followed sites

	$table_posts = $wpdb->prefix . "jjreader_posts";

	$sql = "CREATE TABLE " . $table_posts . " (
		id int NOT NULL AUTO_INCREMENT,
		date datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
		authorname text DEFAULT '' NOT NULL,
		authorurl text DEFAULT '' NOT NULL,
		authoravurl text DEFAULT '' NOT NULL,
		permalink text DEFAULT '' NOT NULL,
		title text DEFAULT '' NOT NULL,
		content mediumtext DEFAULT '' NOT NULL,
		posttype text DEFAULT '' NOT NULL,
		viewed boolean DEFAULT FALSE NOT NULL,
		liked text DEFAULT '' NOT NULL,
		replied text DEFAULT '' NOT NULL,
		reposted text DEFAULT '' NOT NULL,
		rsvped text DEFAULT '' NOT NULL,
		PRIMARY KEY id (id)
	);";
	
	require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
	dbDelta($sql);
	jjreader_log("Created jjreader_posts table");
		
	add_option( 'jjreader_db_version', $jjreader_db_version );
	jjreader_log("Updated jjreader_db_version field");
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


// Function to make the jjreader_page shortcode work
function jjreader_page_shortcode() {
    jjreader_page();
}
add_shortcode('jjreader_page', 'jjreader_page_shortcode');


// The Following page, visible on the front end
function jjreader_page(){
	?> 
	<div class="reader-settings">
	<?php
	
	//Check if the user is logged in with sufficient privileges to EDIT the page
	/* Only editors or admins can edit the list of subscribed sites */ 

	if(current_user_can( 'edit_pages')){
	 	// Only allow editors or above to edit the subscriptions
	 	// (Only editors or above can edit pages)
		jjreader_subscription_editor();
	}
	?>
	</div><!--.reader-settings-->
	<?php
	//Check if the user is logged in with sufficient privileges to VIEW the page
	/* Any logged in user can view the following page */
	jjreader_subscription_viewer();
		
}
// Fetch and display posts from subscribed sites
function jjreader_subscription_viewer(){
	// Check if post_kinds plugin is installed (https://wordpress.org/plugins/indieweb-post-kinds/)
	// If so, set post_kinds to true, if not, set to false
	if ( in_array( 'indieweb-post-kinds/indieweb-post-kinds.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
		$post_kinds = True;
	} else {
		$post_kinds = False;
	} 
	

	// Access databsae
	global $wpdb;
	// Start at post 0, show 15 posts per page
	$fpage=0;
	$length = 15;
	//$table_following = $wpdb->prefix . "jjreader_posts";
	$items = $wpdb->get_results(
		'SELECT * 
		FROM  `'.$wpdb->prefix . 'jjreader_posts` 
		ORDER BY  `date` DESC 
		LIMIT '.($fpage*$length).' , '.$length.';'
	);
	      
	?>
	<div id = "jjreader-feed-container">
	<?php
	//Iterate through all the posts in the database. Display the first 15 
	if ( !empty( $items ) ) { 
		foreach ( $items as $item ) {
			?>
			<div class="jjreader-feed-item">
				<div class="jjreader-item-meta">		
					<?php
					echo '<a class="jjreader-item-authorname" href="'.$item->authorurl.'">'.$item->authorname.'</a> ';
					echo '<a class="jjreader-item-date" href="'.$item->permalink.'">at '.$item->date.'</a>';
					?>
				</div>
				<?php
					echo '<a class="jjreader-item-title" href="'.$item->permalink.'">'.$item->title.'</a>';
				?>
			
				<div class="jjreader-item-content">
					<?php
					echo $item->content;
					?>
				</div>
				
				<div class="jjreader-item-reponse">
					<?php jjreader_reply_actions($post_kinds); ?>
				</div>

			</div>
			<?php
		}
	}
	?>
	
	
	</div>
	<?php

}

// Show interface for adding/removing/editing subscriptions
function jjreader_subscription_editor(){
	?>
	<button id="jjreader-button-refresh" class="ui-state-default ui-corner-all" title=".ui-icon-arrowrefresh-1-e"><span class="ui-icon ui-icon-arrowrefresh-1-e"></span></button>
	<div id="jjreader-addSite-form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
		<strong>Add a subscription</strong><br>
        <label for="jjreader-siteurl">Site URL </label><input type="text" name="jjreader-siteurl" value="" size="30"><br>
        <button id="jjreader-addSite-findFeeds" class="ui-button ui-corner-all ui-widget">Find feeds & title</button>
		<br><br>
		<form class="jjreader-feedpicker jjreader-hidden "></form
		<label for="jjreader-feedurl">Feed URL </label><input type="text" name="jjreader-feedurl" value="" size="30"><br>
		<label for="jjreader-sitetitle">Site Title </label><input type="text" name="jjreader-sitetitle" value="" size="30"><br>
		<button id="jjreader-addSite-submit" class="ui-button ui-corner-all ui-widget">Submit</button>
	</div>
	<button id="jjreader-button-addSite" class="ui-button ui-corner-all ui-widget">Add Site</button>
	<?php
}

// Show reply actions if the user has permission to create posts
function jjreader_reply_actions($post_kinds){
	if(current_user_can( 'publish_posts')){
		if ($post_kinds == true){
			//If post_kinds is true, display response buttons using post-kinds
			echo "Reply buttons with post kinds";
		} else {
			//If post_kinds is false, display reponse buttons without post-kinds	
			echo "Reply buttons with no post kinds";
		}
	}
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

/*
** Add a post to the jjreader_posts table in the database
*/
function add_reader_post($permalink,$title,$content,$authorname='',$authorurl='',$time=0,$avurl='',$siteurl){
	jjreader_log("adding post: ".$permalink.": ".$title);
	global $wpdb;
	if($time < 1){
		$time = time();
	}
	//If the author url is not known, then just use the site url
	
	if (empty($authorurl)){$authorurl = $siteurl;}
	// Add the post (if it doesn't already exist)
	$table_name = $wpdb->prefix . "jjreader_posts";
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE permalink LIKE \"".$permalink."\";")<1){
		//jjreader_log("no duplicate found");
		//jjreader_log("title->".$title." / permalink->".$permalink." / content->".$content." / authorname->".$authorname);
		$rows_affected = $wpdb->insert( $table_name,
			array(
				'permalink' => $permalink,
				'title' => $title,
				'content' => $content,
				'authorname' => $authorname,
				'authorurl' => $authorurl,
				'date' => date( 'Y-m-d H:i:s', $time),
				'authoravurl' => $avurl,
				'viewed' =>false,
				'posttype' => "post" // This will need to be changed for events and other special kinds of posts
			 ) );
		if($rows_affected == false){
			jjreader_log("could not insert post into database!");
			die("could not insert post into database!");
		}else{
			jjreader_log("added ".$title." from ".$authorurl);
		}
	}else{
		jjreader_log("duplicate detected");
	}
}

// Add a new subscription
add_action( 'wp_ajax_jjreader_new_subscription', 'jjreader_new_subscription' );
//add_action( 'wp_ajax_read_me_later', array( $this, 'jjreader_new_subscription' ) );
function jjreader_new_subscription($siteurl, $feedurl, $sitetitle, $feedtype){
	jjreader_log("adding a new subscription");
	$siteurl = $_POST['siteurl'];
	$feedurl = $_POST['feedurl'];
	$sitetitle = $_POST['sitetitle'];
	$feedtype = $_POST['feedtype'];
	jjreader_log("adding subscription: ". $feedurl. " @ ". $sitetitle);
	
	global $wpdb;
	$table_name = $wpdb->prefix . "jjreader_following";
	// Check if the site is already subscribed
	if($wpdb->get_var( "SELECT COUNT(*) FROM ".$table_name." WHERE feedurl LIKE \"".$feedurl."\";")<1){
		jjreader_log("no duplicate found");
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
		jjreader_log("This subscription already exists");
		echo "You are already subscribed to " . $feedurl;
	}
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
		//echo $thetitle[0]->nodeValue;
		$returnArray[] = array("type"=>"title", "data"=>$thetitle[0]->nodeValue);
		$website_links = $dom->getElementsByTagName("link");
		if($website_links->length > 0){
			foreach($website_links as $row){
				if ($row->getAttribute("type")=='application/rss+xml'||
					$row->getAttribute("type")=='application/atom+xml'||
					$row->getAttribute("type")=='text/xml') {
					$returnArray[] = array("type"=>$row->getAttribute("type"), "data"=>$row->getAttribute("href"));
				}
			}
			// Also here check for h-feed in the actual html
			// If h-feed is found return (type="h-feed", data= "site url?"
		}
		echo json_encode($returnArray);
	}
	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Defines the interval for the cron job (5 minutes) 
*/
function jjreader_cron_definer($schedules){
	$schedules['fivemins'] = array(
		'interval'=> 300,
		'display'=>  __('Once Every 5 Minutes')
	);
	return $schedules;
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
		$siteurl = $row->siteurl;
		jjreader_log("checking for new posts in ". $feedurl);
		$feed = jjreader_fetch_feed($feedurl);
		
		if(is_wp_error($feed)){
			jjreader_log($feed->get_error_message());
			trigger_error($feed->get_error_message());
			jjreader_log("Feed read Error: ".$feed->get_error_message());
		} else {
			jjreader_log("Feed read success.");
		}
		$feed->enable_cache(false);
		$feed->strip_htmltags(false);   
		//jjreader_log("<br/>Feed object:");
		//jjreader_log(print_r($feed,true));
		$items = $feed->get_items();

		//jjreader_log(substr(print_r($items,true),0,500));
		//jjreader_log("<br/>items object:");
		usort($items,'date_sort');
		
		foreach ($items as $item){
			try{
				jjreader_log("<br/>got ".$item->get_title()." from ". $item->get_feed()->get_title()."<br/>");
				add_reader_post($item->get_permalink(),$item->get_title(),html_entity_decode ($item->get_description()),$item->get_feed()->get_title(),$item->get_feed()->get_link(),$item->get_date("U"),$siteurl);
			}catch(Exception $e){
				jjreader_log("Exception occured: ".$e->getMessage());
			}
		}
		
		remove_filter( 'wp_feed_cache_transient_lifetime', 'jjreader_feed_time' );
	}
	jjreader_log('No feed defined');
		
	// TO DO: Clean up old posts
	
	wp_die(); // this is required to terminate immediately and return a proper response
}

/*
** Fetch a feed and return its content
*/
function jjreader_fetch_feed($url) {
	require_once (ABSPATH . WPINC . '/class-feed.php');

	$feed = new SimplePie();
	jjreader_log("Url is fetchable");
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
** Runs upon deactivating the plugin
*/
function jjreader_deactivate() {
	// on deactivation remove the cron job 
	if ( wp_next_scheduled( 'jjreader_generate_hook' ) ) {
		wp_clear_scheduled_hook( 'jjreader_generate_hook' );
	}
	jjreader_log("deactivated plugin");
}


/* Functions to run upon installation */ 
register_activation_hook(__FILE__,'jjreader_install');
register_activation_hook(__FILE__,'jjreader_create_tables');

/* Functions to run upon deactivation */ 
register_deactivation_hook( __FILE__, 'jjreader_deactivate' );

add_filter('cron_schedules','jjreader_cron_definer');
add_action( 'jjreader_generate_hook', 'jjreader_aggregator' );


/* Check if the database version has changed when plugin is updated */ 
add_action( 'plugins_loaded', 'jjreader_update_db_check' );

/* Hook to display admin notice */ 
add_action( 'admin_notices', 'initial_setup_admin_notice' );

	
?>